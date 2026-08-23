# Threshold — Achievement & Badge Engine

An event-driven Laravel service that turns customer purchase activity into unlocked
achievements, rolls achievements up into badges, and triggers an automated ₦300 cashback
payout when a badge is earned.

Built to demonstrate three things: event-driven architecture in Laravel, module
boundaries that could be peeled into separate services, and a rules engine that's
config-driven rather than hardcoded.

Out of scope, stubbed deliberately: real ecommerce checkout, real payment provider,
authentication. Each is stubbed behind a clean interface so the *pattern* is provable
without building a full store.

---

## Setup

### Dependencies

- [Docker](https://docs.docker.com/desktop/)

### Docker (recommended)

```bash
cp .env.example .env
docker compose build app
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose restart queue
```

This starts five containers: `app` (PHP-FPM), `webserver` (nginx, serving on
[http://localhost:8000](http://localhost:8000)), `queue` (processes the queued cashback
listener), `db` (Postgres, exposed on host port `5439` to avoid clashing with a local
Postgres install), and `adminer` (a database UI at
[http://localhost:8201](http://localhost:8201) — server `db`, username `threshold`,
password `password`).

`queue` starts before `vendor/` exists (composer hasn't run yet), so it crash-loops
briefly on boot — that's expected. The final `restart` above just makes sure it's picked
up the dependencies once they're there, since a crashed container doesn't always notice a
now-populated bind mount on its own retry.

The whole project directory is bind-mounted into `app`/`queue`/`webserver`, and
`.env.example` already has the Docker network's Postgres host (`DB_HOST=db`) baked in —
no environment-variable overrides in `docker-compose.yml` to keep in sync with it.

Seed some demo data and fire a purchase:

```bash
docker compose exec app php artisan orders:simulate 1
curl http://localhost:8000/users/1/achievements
```

Stop the stack with `docker compose down` (add `-v` to also drop the Postgres volume).

### Without Docker

```bash
composer install
cp .env.example .env
```

Then edit `.env` and set `DB_CONNECTION=sqlite` (or point `DB_HOST`/`DB_PORT` at a
Postgres instance you're running yourself) before continuing:

```bash
php artisan key:generate
php artisan migrate --seed
composer run dev   # serves the app + a queue worker together
```

## Running tests

```bash
docker compose exec app php artisan test
```

or, without Docker:

```bash
php artisan test
```

All feature/unit tests run against an in-memory SQLite database and the `sync` queue
driver (see `phpunit.xml`), regardless of what `.env` points at — no external services are
needed to run the suite.

---

## Design decisions

### Domain model

For every purchase, `OrderCompleted` is fired. Achievements are config-driven rows
(`achievement_group`, `name`, `trigger_type`, `threshold`, `sort_order`) — adding one is a
database insert, not a code change. Badges are **count-based**: a badge unlocks once a
user's *total* unlocked-achievement count reaches its `required_achievement_count`, not
because a specific named set of achievements was completed. The spec's own worked example
("5 unlocked → 3 more for Advanced") is a running total against a threshold, which is what
this models directly.

### Event flow

```
OrderCompleted (user, order)
  └─ CheckAchievementUnlocks → writes UserAchievement rows → fires AchievementUnlocked
       └─ CheckBadgeUnlocks → writes UserBadge rows → fires BadgeUnlocked  [queued below]
            └─ TriggerCashback (queued) → calls the payment provider → CashbackTransaction
```

Achievement/badge unlocking is synchronous — it's cheap, in-process, and the customer's
achievements endpoint should reflect it immediately. The payment call is the one thing
that's slow and can fail, so `TriggerCashback` is queued and retried by Laravel's built-in
queue rather than blocking the request that unlocked the badge.

### Idempotency

Every unlock rule is safe to redeliver:

- `user_achievements` / `user_badges` have unique constraints on `(user_id, achievement_id)`
  / `(user_id, badge_id)`, so re-processing the same unlock never double-writes.
- Purchase/achievement counts are **denormalized counters**
  (`user_purchase_counts`, `user_achievement_counts`) rather than live `COUNT(*)` queries
  into another module's table — but an atomic `increment()` only stops a *lost* update, not
  a *duplicate* one, so each counter is paired with an idempotency ledger
  (`processed_orders`, `processed_achievement_unlocks`) keyed by the id the triggering
  event already carries. A redelivered event increments the ledger's `insertOrIgnore` and
  nothing else — the counter only moves on a genuinely new row.
- `CashbackTransaction` inserts its row as `pending` **before** calling the payment
  provider, catching a unique-constraint violation as "already handled." Calling the
  provider first and inserting after would let a queue retry pay out twice while the
  second insert silently failed — ordering here is what makes the constraint an actual
  guarantee, not just a race.

### Module boundaries

```
app/Modules/
  Orders/        Order model, OrderService, fires OrderCompleted
  Achievements/  Achievement, AchievementGroup, UserAchievement, listens for
                 OrderCompleted, fires AchievementUnlocked
  Badges/        Badge, UserBadge, listens for AchievementUnlocked, fires BadgeUnlocked
  Payments/      PaymentProviderInterface, FakePaystackProvider, CashbackTransaction,
                 listens for BadgeUnlocked
```

A module never queries another module's tables directly — it only reacts to the payload
of an event it's subscribed to. Plain PSR-4 namespacing under `app/Modules`, no package
(`nwidart/laravel-modules`): adding a dependency to demonstrate modularity would be the
wrong flex for something this size. The folder + namespace discipline is the
demonstration.

### Swapping in a real payment provider

`FakePaystackProvider` logs the call and returns a synthetic success. To go live, bind
`App\Modules\Payments\Contracts\PaymentProviderInterface` to a real client — the binding
lives in `config/payments.php` (`PAYMENT_PROVIDER` env var), so this is a one-line change,
not a rewrite of `TriggerCashback`. The cashback amount is likewise config-driven
(`BADGE_CASHBACK_AMOUNT_KOBO`, defaulting to 30,000 kobo = ₦300 per the spec).

### Extraction seam: could this become a separate service?

The module boundaries above prove the *modules* are decoupled from each other inside one
process — they only talk through event payloads, never each other's tables. That's a
necessary condition for "this could be its own service," but not sufficient on its own,
since everything still runs through Laravel's synchronous, in-process `Event` facade.

`App\Contracts\EventPublisher` (bound to `App\Services\LogEventPublisher`, which just logs
today) is the seam an external message broker would implement — swapping that one binding
for a real SQS/Kafka publisher is the entire migration to let another service subscribe.
`CheckAchievementUnlocks` and `CheckBadgeUnlocks` both call it after their local unlock
logic runs. The versioned payload contracts a Product or Payments service would actually
integrate against are written down in [`docs/events.md`](docs/events.md) — deliberately
smaller than the internal Laravel event objects (an id, not a full `User` model).

### API endpoint

`GET /users/{user}/achievements` — defined in `routes/web.php`, matching the assessment
spec's explicit instruction (an unusual choice for a JSON endpoint, since Laravel's
convention is `routes/api.php`, but the spec names the file directly).

```json
{
  "unlocked_achievements": ["First Purchase"],
  "next_available_achievements": ["5 Purchases"],
  "current_badge": "Beginner",
  "next_badge": "Advanced",
  "remaining_to_unlock_next_badge": 3
}
```

`next_available_achievements` returns one achievement per group — the lowest `sort_order`
one the user hasn't unlocked yet — and omits any group the user has fully completed.
`current_badge` reflects the highest badge actually awarded (a real `UserBadge` row, and
therefore a triggered cashback), not a live recomputation against the achievement count,
so it can't report a badge as "current" a beat before its payout has actually gone through
the event chain.
