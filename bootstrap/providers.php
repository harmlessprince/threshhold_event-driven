<?php

use App\Modules\Achievements\Providers\AchievementsServiceProvider;
use App\Modules\Badges\Providers\BadgesServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    OrdersServiceProvider::class,
    AchievementsServiceProvider::class,
    BadgesServiceProvider::class,
    PaymentsServiceProvider::class,
];
