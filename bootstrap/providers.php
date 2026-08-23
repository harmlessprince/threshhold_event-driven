<?php

use App\Modules\Achievements\Providers\AchievementsServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    OrdersServiceProvider::class,
    AchievementsServiceProvider::class,
];
