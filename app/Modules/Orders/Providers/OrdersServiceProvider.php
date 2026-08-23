<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Orders\Console\SimulateOrderCommand;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SimulateOrderCommand::class,
            ]);
        }
    }
}
