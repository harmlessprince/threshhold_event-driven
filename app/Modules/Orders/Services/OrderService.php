<?php

namespace App\Modules\Orders\Services;

use App\Models\User;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Models\Order;

class OrderService
{
    public function completePurchase(User $user, int $amountInKobo): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'amount' => $amountInKobo,
            'status' => 'completed',
        ]);

        OrderCompleted::dispatch($user, $order);

        return $order;
    }
}
