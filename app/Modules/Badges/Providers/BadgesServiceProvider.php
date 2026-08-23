<?php

namespace App\Modules\Badges\Providers;

use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Badges\Listeners\CheckBadgeUnlocks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BadgesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(AchievementUnlocked::class, CheckBadgeUnlocks::class);
    }
}
