<?php

namespace App\Modules\Badges\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BadgeUnlocked
{
    use Dispatchable, SerializesModels;

    /**
     * Property names follow the assessment spec's literal payload shape
     * (badge_name, user) rather than Laravel's usual camelCase.
     */
    public function __construct(
        public readonly string $badge_name,
        public readonly User $user,
    ) {}
}
