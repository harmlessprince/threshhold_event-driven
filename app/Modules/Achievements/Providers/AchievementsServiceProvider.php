<?php

namespace App\Modules\Achievements\Providers;

use App\Modules\Achievements\Listeners\CheckAchievementUnlocks;
use App\Modules\Orders\Events\OrderCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AchievementsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderCompleted::class, CheckAchievementUnlocks::class);
    }
}
