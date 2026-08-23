<?php

namespace App\Modules\Achievements\Listeners;

use App\Contracts\EventPublisher;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Services\AchievementService;
use App\Modules\Orders\Events\OrderCompleted;

class CheckAchievementUnlocks
{
    public function __construct(
        private readonly AchievementService $achievements,
        private readonly EventPublisher $publisher,
    ) {}

    public function handle(OrderCompleted $event): void
    {
        $purchaseCount = $this->achievements->recordCompletedOrder($event->user, $event->order->id);

        $newlyUnlocked = $this->achievements->unlockEligibleAchievements($event->user, $purchaseCount);

        $newlyUnlocked->each(function (Achievement $achievement) use ($event): void {
            $this->publisher->publish('AchievementUnlocked', [
                'achievement_name' => $achievement->name,
                'user_id' => $event->user->id,
            ]);
        });
    }
}
