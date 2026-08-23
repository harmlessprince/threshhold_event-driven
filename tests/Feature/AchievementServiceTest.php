<?php

use App\Models\User;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Models\AchievementGroup;
use App\Modules\Achievements\Models\UserAchievement;
use App\Modules\Achievements\Services\AchievementService;

beforeEach(function () {
    $this->service = app(AchievementService::class);
    $this->group = AchievementGroup::factory()->create();
});

test('unlocks an achievement exactly at its threshold', function () {
    $achievement = Achievement::factory()->create([
        'achievement_group_id' => $this->group->id,
        'threshold' => 5,
    ]);
    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleAchievements($user, 5);

    expect($unlocked)->toHaveCount(1);
    expect(UserAchievement::query()
        ->where('user_id', $user->id)
        ->where('achievement_id', $achievement->id)
        ->exists())->toBeTrue();
});

test('does not unlock an achievement below its threshold', function () {
    Achievement::factory()->create([
        'achievement_group_id' => $this->group->id,
        'threshold' => 5,
    ]);
    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleAchievements($user, 4);

    expect($unlocked)->toHaveCount(0);
    expect(UserAchievement::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('does not unlock an already-unlocked achievement again', function () {
    Achievement::factory()->create([
        'achievement_group_id' => $this->group->id,
        'threshold' => 1,
    ]);
    $user = User::factory()->create();

    $first = $this->service->unlockEligibleAchievements($user, 1);
    $second = $this->service->unlockEligibleAchievements($user, 1);

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(0);
    expect(UserAchievement::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('unlocks achievements across multiple groups independently', function () {
    $otherGroup = AchievementGroup::factory()->create();

    $purchaseAchievement = Achievement::factory()->create([
        'achievement_group_id' => $this->group->id,
        'threshold' => 1,
    ]);
    $otherAchievement = Achievement::factory()->create([
        'achievement_group_id' => $otherGroup->id,
        'threshold' => 1,
    ]);

    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleAchievements($user, 1);

    expect($unlocked->pluck('id')->sort()->values()->all())
        ->toBe(collect([$purchaseAchievement->id, $otherAchievement->id])->sort()->values()->all());
});

test('recording the same order twice does not inflate the purchase count', function () {
    $user = User::factory()->create();

    $first = $this->service->recordCompletedOrder($user, orderId: 100);
    $second = $this->service->recordCompletedOrder($user, orderId: 100);

    expect($first)->toBe(1);
    expect($second)->toBe(1);
});

test('recording different orders increments the purchase count', function () {
    $user = User::factory()->create();

    $this->service->recordCompletedOrder($user, orderId: 1);
    $count = $this->service->recordCompletedOrder($user, orderId: 2);

    expect($count)->toBe(2);
});
