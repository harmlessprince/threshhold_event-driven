<?php

namespace App\Modules\Badges\Listeners;

use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Badges\Services\BadgeService;

class CheckBadgeUnlocks
{
    public function __construct(private readonly BadgeService $badges) {}

    public function handle(AchievementUnlocked $event): void
    {
        $achievementCount = $this->badges->recordUnlockedAchievement($event->user, $event->achievement_id);

        $this->badges->unlockEligibleBadges($event->user, $achievementCount);
    }
}
