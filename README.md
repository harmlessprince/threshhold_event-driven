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

### Docker (recommended)

```bash
cp .env.example .env
docker compose up --build
```

This starts four containers: `migrate` (runs once, then exits — `app` and `queue` both
wait for it so neither ever races the schema), `app` (serves on
[http://localhost:8000](http://localhost:8000)), `queue` (processes the queued cashback
listener), and `postgres` (exposed on host port `5439` to avoid clashing with a local
Postgres install).

`.env.example` ships with a real, pre-generated `APP_KEY` rather than a blank one — this
is a demo app with no production data behind it, and baking in a key means the one-liner
above works with no extra step (there's no bind mount into the containers, so a key
generated *after* the image is built wouldn't reach `queue`'s or `migrate`'s separate
containers anyway).

Seed some demo data and fire a purchase:

```bash
docker compose exec app php artisan db:seed
docker compose exec app php artisan orders:simulate 1
curl http://localhost:8000/users/1/achievements
```

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer run dev   # serves the app + a queue worker together
```

## Running tests

```bash
php artisan test
```

All feature/unit tests run against an in-memory SQLite database and the `sync` queue
driver (see `phpunit.xml`), so no external services are needed to run the suite.

Running this inside the `app` container needs one extra step: the container's real
environment already sets `DB_CONNECTION=pgsql` / `QUEUE_CONNECTION=database` for serving
the app, and — a genuine PHP gotcha — those win over `phpunit.xml`'s `<env>` block no
matter what, since real process environment variables are read from `$_SERVER`/`$_ENV`
(populated once at process start) while PHPUnit's overrides only affect `putenv()`/
`getenv()`, which Laravel's `env()` helper doesn't consult when `$_SERVER` already has a
value. Override them for that one command instead:

```bash
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e QUEUE_CONNECTION=sync \
  -e CACHE_STORE=array -e SESSION_DRIVER=array app php artisan test
```

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
