<?php

namespace App\Modules\Achievements\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked
{
    use Dispatchable, SerializesModels;

    /**
     * achievement_name and user are the spec's required payload shape (literal
     * snake_case, not Laravel's usual camelCase). achievement_id rides along as an
     * extra field for subscribers (e.g. Badges' idempotency ledger) that need a stable
     * identifier — achievement name isn't guaranteed unique, only its slug is.
     */
    public function __construct(
        public readonly string $achievement_name,
        public readonly User $user,
        public readonly int $achievement_id,
    ) {}
}
