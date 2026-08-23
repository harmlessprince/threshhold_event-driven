<?php

namespace App\Modules\Badges\Listeners;

use App\Contracts\EventPublisher;
use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Badges\Models\Badge;
use App\Modules\Badges\Services\BadgeService;

class CheckBadgeUnlocks
{
    public function __construct(
        private readonly BadgeService $badges,
        private readonly EventPublisher $publisher,
    ) {}

    public function handle(AchievementUnlocked $event): void
    {
        $achievementCount = $this->badges->recordUnlockedAchievement($event->user, $event->achievement_id);

        $newlyUnlocked = $this->badges->unlockEligibleBadges($event->user, $achievementCount);

        $newlyUnlocked->each(function (Badge $badge) use ($event): void {
            $this->publisher->publish('BadgeUnlocked', [
                'badge_name' => $badge->name,
                'user_id' => $event->user->id,
            ]);
        });
    }
}
