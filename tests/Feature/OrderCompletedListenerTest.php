<?php

use App\Models\User;
use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Models\UserAchievement;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Event;

test('completing an order unlocks eligible achievements and fires AchievementUnlocked', function () {
    $achievement = Achievement::factory()->create([
        'trigger_type' => 'purchase_count',
        'threshold' => 1,
    ]);
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    Event::fake([AchievementUnlocked::class]);

    OrderCompleted::dispatch($user, $order);

    Event::assertDispatched(
        AchievementUnlocked::class,
        fn (AchievementUnlocked $event) => $event->achievement_name === $achievement->name
            && $event->user->is($user)
    );

    expect(UserAchievement::query()
        ->where('user_id', $user->id)
        ->where('achievement_id', $achievement->id)
        ->exists())->toBeTrue();
});

test('firing OrderCompleted twice for the same order does not double-unlock', function () {
    Achievement::factory()->create([
        'trigger_type' => 'purchase_count',
        'threshold' => 1,
    ]);
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    OrderCompleted::dispatch($user, $order);
    OrderCompleted::dispatch($user, $order);

    expect(UserAchievement::query()->where('user_id', $user->id)->count())->toBe(1);
});
