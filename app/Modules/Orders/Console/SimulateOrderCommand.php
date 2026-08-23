<?php

namespace App\Modules\Orders\Console;

use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Console\Command;

class SimulateOrderCommand extends Command
{
    protected $signature = 'orders:simulate {user : ID of the user completing the order} {amount=1000 : Order amount in kobo}';

    protected $description = 'Simulate a completed order for a user, firing OrderCompleted';

    public function handle(OrderService $orderService): int
    {
        $user = User::findOrFail($this->argument('user'));

        $order = $orderService->completePurchase($user, (int) $this->argument('amount'));

        $this->info("Order #{$order->id} completed for {$user->name}. OrderCompleted dispatched.");

        return self::SUCCESS;
    }
}
