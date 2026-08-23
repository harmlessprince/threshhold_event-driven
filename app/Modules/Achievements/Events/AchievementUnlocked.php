<?php

namespace App\Modules\Achievements\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked
{
    use Dispatchable, SerializesModels;

    /**
     * Property names follow the assessment spec's literal payload shape
     * (achievement_name, user) rather than Laravel's usual camelCase.
     */
    public function __construct(
        public readonly string $achievement_name,
        public readonly User $user,
    ) {}
}
