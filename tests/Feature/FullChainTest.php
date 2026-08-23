<?php

use App\Models\User;
use App\Modules\Badges\Models\Badge;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Enums\CashbackStatus;
use App\Modules\Payments\Models\CashbackTransaction;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;

test('completing enough orders unlocks an achievement, then a badge, then pays cashback', function () {
    $this->seed(AchievementSeeder::class);
    $this->seed(BadgeSeeder::class);

    $user = User::factory()->create();

    // Beginner needs 2 unlocked achievements; the seeded catalog unlocks both
    // "First Purchase" (threshold 1) and "5 Purchases" (threshold 5) within 5 orders.
    for ($i = 0; $i < 5; $i++) {
        $order = Order::factory()->for($user)->create();
        OrderCompleted::dispatch($user, $order);
    }

    $beginner = Badge::query()->where('slug', 'beginner')->firstOrFail();

    $transaction = CashbackTransaction::query()
        ->where('user_id', $user->id)
        ->where('badge_id', $beginner->id)
        ->firstOrFail();

    expect($transaction->status)->toBe(CashbackStatus::Success);
    expect($transaction->amount)->toBe((int) config('payments.badge_cashback_amount'));
});
