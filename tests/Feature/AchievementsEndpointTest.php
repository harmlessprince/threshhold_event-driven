<?php

use App\Models\User;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Models\AchievementGroup;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Models\Order;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;

beforeEach(function () {
    $this->seed(AchievementSeeder::class);
    $this->seed(BadgeSeeder::class);
});

test('a brand new user gets an empty, all-null response shape', function () {
    $user = User::factory()->create();

    $response = $this->getJson("/users/{$user->id}/achievements");

    $response->assertOk()->assertExactJson([
        'unlocked_achievements' => [],
        'next_available_achievements' => ['First Purchase'],
        'current_badge' => null,
        'next_badge' => 'Beginner',
        'remaining_to_unlock_next_badge' => 2,
    ]);
});

test('the response shape matches the contract after unlocking achievements and a badge', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $order = Order::factory()->for($user)->create();
        OrderCompleted::dispatch($user, $order);
    }

    $response = $this->getJson("/users/{$user->id}/achievements");

    $response->assertOk()->assertExactJson([
        'unlocked_achievements' => ['First Purchase', '5 Purchases'],
        'next_available_achievements' => [],
        'current_badge' => 'Beginner',
        'next_badge' => 'Advanced',
        'remaining_to_unlock_next_badge' => 3,
    ]);
});

test('holding the highest badge returns null next_badge and zero remaining', function () {
    $user = User::factory()->create();

    AchievementGroup::factory()
        ->has(Achievement::factory()->count(3)->sequence(
            ['name' => 'Extra 1', 'slug' => 'extra-1', 'trigger_type' => 'purchase_count', 'threshold' => 6, 'sort_order' => 1],
            ['name' => 'Extra 2', 'slug' => 'extra-2', 'trigger_type' => 'purchase_count', 'threshold' => 7, 'sort_order' => 2],
            ['name' => 'Extra 3', 'slug' => 'extra-3', 'trigger_type' => 'purchase_count', 'threshold' => 8, 'sort_order' => 3],
        ), 'achievements')
        ->create(['name' => 'Extras', 'slug' => 'extras']);

    for ($i = 0; $i < 8; $i++) {
        $order = Order::factory()->for($user)->create();
        OrderCompleted::dispatch($user, $order);
    }

    $response = $this->getJson("/users/{$user->id}/achievements");

    $response->assertOk()
        ->assertJsonPath('current_badge', 'Advanced')
        ->assertJsonPath('next_badge', null)
        ->assertJsonPath('remaining_to_unlock_next_badge', 0);
});

test('a fully-completed achievement group is omitted from next_available_achievements', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $order = Order::factory()->for($user)->create();
        OrderCompleted::dispatch($user, $order);
    }

    $response = $this->getJson("/users/{$user->id}/achievements");

    $response->assertOk()->assertJsonPath('next_available_achievements', []);
});

test('multiple achievement groups each return their own next achievement', function () {
    $user = User::factory()->create();

    $secondGroup = AchievementGroup::factory()->create(['name' => 'Reviews', 'slug' => 'reviews']);
    $secondGroup->achievements()->create([
        'name' => 'First Review',
        'slug' => 'first-review',
        'trigger_type' => 'review_count',
        'threshold' => 1,
        'sort_order' => 1,
    ]);

    $response = $this->getJson("/users/{$user->id}/achievements");

    $response->assertOk();
    expect($response->json('next_available_achievements'))
        ->toEqualCanonicalizing(['First Purchase', 'First Review']);
});

test('an unknown user returns 404, not an unhandled exception', function () {
    $response = $this->getJson('/users/999999/achievements');

    $response->assertNotFound();
});
