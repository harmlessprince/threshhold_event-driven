<?php

namespace App\Modules\Achievements\Listeners;

use App\Modules\Achievements\Services\AchievementService;
use App\Modules\Orders\Events\OrderCompleted;

class CheckAchievementUnlocks
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function handle(OrderCompleted $event): void
    {
        $purchaseCount = $this->achievements->recordCompletedOrder($event->user, $event->order->id);

        $this->achievements->unlockEligibleAchievements($event->user, $purchaseCount);
    }
}
