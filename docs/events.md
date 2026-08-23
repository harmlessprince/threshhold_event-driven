# Published Events

These are the domain events this service publishes for external consumers — a Product
service, a Payments service, or anything else that needs to react to a user unlocking an
achievement or badge without querying this service's database directly.

Today they're published through `App\Contracts\EventPublisher`, bound to
`App\Services\LogEventPublisher`, which just logs what would have gone out. Swapping that
one binding for a real implementation (an SQS/Kafka topic publisher, an outbound webhook
sender) is the entire migration to a real message broker — no application code changes.

Internally, these are also fired as regular Laravel events
(`App\Modules\Achievements\Events\AchievementUnlocked`,
`App\Modules\Badges\Events\BadgeUnlocked`) for other modules in this codebase to listen
to. The payloads below are the external, versioned contract — deliberately smaller than
the internal event objects (no full `User` model, just the id an external consumer would
use to look up its own record of that user).

## AchievementUnlocked v1

Published once per newly unlocked achievement, after `CheckAchievementUnlocks` finishes
processing an `OrderCompleted` event.

```json
{
  "achievement_name": "First Purchase",
  "user_id": 42
}
```

| Field              | Type   | Notes                                        |
| ------------------ | ------ | --------------------------------------------- |
| `achievement_name` | string | Matches the `achievements.name` column.       |
| `user_id`          | int    | Join key back to the consumer's own user data. |

## BadgeUnlocked v1

Published once per newly unlocked badge, after `CheckBadgeUnlocks` finishes processing an
`AchievementUnlocked` event.

```json
{
  "badge_name": "Beginner",
  "user_id": 42
}
```

| Field        | Type   | Notes                                          |
| ------------ | ------ | ----------------------------------------------- |
| `badge_name` | string | Matches the `badges.name` column.               |
| `user_id`    | int    | Join key back to the consumer's own user data.  |

A `BadgeUnlocked` publish also implies a ₦300 cashback was triggered for that user via the
Payments module — see `App\Modules\Payments\Listeners\TriggerCashback`.
