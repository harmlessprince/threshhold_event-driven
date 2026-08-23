# Threshold — Achievement & Badge Engine

A customer makes a purchase, that unlocks achievements, enough achievements unlock a badge, and unlocking a badge pays out a ₦300 cashback.

This shows three things: event-driven design done properly in
Laravel, module boundaries clean enough that a piece of this could become its own service
later, and a rules engine where adding a new achievement or badge is a database row, not
a code change.

Real ecommerce checkout, a real payment provider, and authentication are deliberately
stubbed — the spec asks for that, and building any of them for real would be solving a
different problem than the one being assessed.

## Table of Contents

- [Setup](#setup)
- [Running tests](#running-tests)
- [How it works](#how-it-works)
  - [Domain model](#domain-model)
  - [Event flow](#event-flow)
  - [Not doing anything twice by accident](#not-doing-anything-twice-by-accident)
  - [Module boundaries](#module-boundaries)
  - [Swapping in a real payment provider](#swapping-in-a-real-payment-provider)
  - [API endpoint](#api-endpoint)
- [What I Deliberately Did Not Build, and Why](#what-i-deliberately-did-not-build-and-why)
- [What I Would Do With Two More Weeks](#what-i-would-do-with-two-more-weeks)

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
```

This brings up five containers: the app itself (PHP-FPM), nginx on
[http://localhost:8000](http://localhost:8000), a queue worker for the cashback job,
Postgres (port `5439`, so it won't clash with a Postgres you might already have running),
and Adminer at [http://localhost:8201](http://localhost:8201) if you want to look at the
database directly (server `db`, user `threshold`, password `password`).

The queue worker won't start until the app is actually ready.

Try it out:

```bash
docker compose exec app php artisan orders:simulate 1
curl http://localhost:8000/users/1/achievements
```

`docker compose down` stops everything (add `-v` if you also want to drop the database).

### Without Docker

```bash
composer install
cp .env.example .env
```

Set `DB_CONNECTION=sqlite` in `.env` (or point it at your own Postgres), then:

```bash
php artisan key:generate
php artisan migrate --seed
composer run dev
```

## Running tests

```bash
docker compose exec app php artisan test
```

or just `php artisan test` if you're not using Docker. Tests run against an in-memory
SQLite database no matter what your `.env` says, so nothing external needs to be running.

---

## How it works

### Domain model

A purchase fires `OrderCompleted`. Achievements are rows in a table (name, threshold,
sort order) — adding a new one is a database insert, not a deploy. Badges are
count-based: a badge unlocks once a user's total number of unlocked achievements hits its
threshold, rather than needing a specific named set of achievements. The spec's own
example — "5 unlocked, 3 more for Advanced" — is exactly a running count against a
threshold, so that's what I modeled.

### Event flow

```
OrderCompleted
  → unlocks achievements → fires AchievementUnlocked
      → unlocks badges → fires BadgeUnlocked
          → (queued) pays out cashback
```

Unlocking achievements and badges happens right away — it's cheap, and I want the
achievements endpoint to reflect it immediately. The payment call is the one part that's
slow and can fail, so that's the only piece that runs in the background on a queue.

### Not doing anything twice by accident

If the same event gets sent twice — a retry, a repeated delivery, whatever — nothing here
processes it a second time. Unlocking an achievement or badge twice is blocked at the
database level: the database itself won't allow two rows for the same user and the same
achievement or badge. Purchase and achievement counts are stored as running totals rather
than counted live, and each one is paired with a small table that remembers "have I
already counted this," so a repeated delivery can't push the number up twice. The
cashback record is created as `pending` before the payment call goes out, so a repeated
payout attempt just gets blocked by the database instead of actually charging twice. And
if the payment call itself blows up (network error, provider down), that's caught and the
record is marked `failed` rather than left stuck on `pending` forever.

### Module boundaries

```
Orders        → fires OrderCompleted
Achievements  → listens for OrderCompleted, fires AchievementUnlocked
Badges        → listens for AchievementUnlocked, fires BadgeUnlocked
Payments      → listens for BadgeUnlocked, pays out
```

No module reaches into another module's database tables — they only react to events.
That's what would let any one of these become its own service later without a rewrite. I
also added a small hook, called `EventPublisher` (it just logs for now), so an outside
service could listen to these events without ever touching this app's database directly —
what those events look like is written down in [`docs/events.md`](docs/events.md).

### Swapping in a real payment provider

`FakePaystackProvider` just logs and returns success. Swapping it for a real Paystack (or
any other) client is a one-line config change (`PAYMENT_PROVIDER` in `.env`) —
`TriggerCashback` doesn't need to change at all.

### API endpoint

`GET /users/{user}/achievements`, in `routes/web.php` — the spec names that file
specifically, which is unusual for a JSON endpoint, but I matched it literally rather
than defaulting to `routes/api.php`.

```json
{
  "unlocked_achievements": ["First Purchase"],
  "next_available_achievements": ["5 Purchases"],
  "current_badge": "Beginner",
  "next_badge": "Advanced",
  "remaining_to_unlock_next_badge": 3
}
```

`next_available_achievements` is one per group — whichever the user hasn't unlocked yet —
and a group the user's already finished just doesn't show up. `current_badge` only
reflects a badge that's actually been awarded and paid out, not one that merely qualifies
by the numbers.

---

## What I Deliberately Did Not Build, and Why

- **A real payment provider** — the interface and the setting to swap it in are already
  there, I just didn't wire up an actual merchant account for a take-home.
- **Caching the achievements endpoint** — the queries are already fast at this scale. I'd
  reach for Redis the moment this endpoint saw real traffic, not before.
- **Telling apart "the payment was declined" from "we're not sure what happened"** —
  right now both end up `failed`. A timeout doesn't actually mean the charge failed, and
  the honest fix is checking back with the provider later, not guessing in code.
- **Curated achievement sets for badges** — count-based matched the spec's own example
  better, and it's a one-row insert to add a badge. A pivot table for curated sets is an
  easy add later if it's ever needed.
- **More than one achievement trigger type** — the column supports it, only
  `purchase_count` is wired up because that's all the spec asked for.
- **Auth, rate limiting, and API versioning on the endpoint** — the spec explicitly stubs
  auth, so this is an open lookup by user id today. Not something I'd ship like this for
  real.

## What I Would Do With Two More Weeks

1. Add Redis caching to the achievements endpoint, and actually measure it before and
   after instead of assuming it helps.
2. Build a job that checks back with the payment provider for anything that failed for an
   unclear reason, instead of just marking it failed and moving on.
3. Wire up a real payment provider, and support the case where it confirms a payout later
   through a webhook instead of right away in the response.
4. Add authentication so the achievements endpoint is scoped to whoever's logged in, plus
   versioning and rate limiting.
5. Add a second way to unlock achievements (not just purchases), to actually prove this
   can support more than one instead of just claiming it can.
6. Swap the logging-only event publisher for a real message queue (SQS or similar), and
   actually split one of these modules out into its own service instead of just leaving
   the option open.
