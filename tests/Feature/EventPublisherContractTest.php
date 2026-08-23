<?php

use App\Contracts\EventPublisher;
use App\Models\User;
use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Badges\Models\Badge;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Models\Order;
use Database\Seeders\AchievementSeeder;

test('unlocking an achievement publishes AchievementUnlocked v1 with the documented payload shape', function () {
    $this->seed(AchievementSeeder::class);

    $user = User::factory()->create();

    $publisher = Mockery::mock(EventPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->with('AchievementUnlocked', ['achievement_name' => 'First Purchase', 'user_id' => $user->id]);

    $this->app->instance(EventPublisher::class, $publisher);

    $order = Order::factory()->for($user)->create();

    OrderCompleted::dispatch($user, $order);
});

test('unlocking a badge publishes BadgeUnlocked v1 with the documented payload shape', function () {
    $user = User::factory()->create();
    $badge = Badge::factory()->create(['required_achievement_count' => 1]);

    $publisher = Mockery::mock(EventPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->with('BadgeUnlocked', ['badge_name' => $badge->name, 'user_id' => $user->id]);

    $this->app->instance(EventPublisher::class, $publisher);

    AchievementUnlocked::dispatch('First Purchase', $user, 1);
});
