<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\Listeners\TriggerCashback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, config('payments.provider'));
    }

    public function boot(): void
    {
        Event::listen(BadgeUnlocked::class, TriggerCashback::class);
    }
}
