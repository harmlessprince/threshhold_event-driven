<?php

use App\Models\User;
use App\Modules\Badges\Models\Badge;
use App\Modules\Badges\Models\UserBadge;
use App\Modules\Badges\Services\BadgeService;

beforeEach(function () {
    $this->service = app(BadgeService::class);
});

test('unlocks a badge exactly at its required achievement count', function () {
    $badge = Badge::factory()->create(['required_achievement_count' => 2]);
    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleBadges($user, 2);

    expect($unlocked)->toHaveCount(1);
    expect(UserBadge::query()
        ->where('user_id', $user->id)
        ->where('badge_id', $badge->id)
        ->exists())->toBeTrue();
});

test('does not unlock a badge below its required achievement count', function () {
    Badge::factory()->create(['required_achievement_count' => 2]);
    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleBadges($user, 1);

    expect($unlocked)->toHaveCount(0);
    expect(UserBadge::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('a user with zero unlocked achievements gets no badges', function () {
    Badge::factory()->create(['required_achievement_count' => 2]);
    $user = User::factory()->create();

    $unlocked = $this->service->unlockEligibleBadges($user, 0);

    expect($unlocked)->toHaveCount(0);
});

test('does not unlock an already-unlocked badge again', function () {
    Badge::factory()->create(['required_achievement_count' => 1]);
    $user = User::factory()->create();

    $first = $this->service->unlockEligibleBadges($user, 1);
    $second = $this->service->unlockEligibleBadges($user, 1);

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(0);
    expect(UserBadge::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('holding the highest badge already, further eligibility checks unlock nothing new', function () {
    $beginner = Badge::factory()->create(['required_achievement_count' => 2]);
    $advanced = Badge::factory()->create(['required_achievement_count' => 5]);
    $user = User::factory()->create();

    $this->service->unlockEligibleBadges($user, 5);
    $again = $this->service->unlockEligibleBadges($user, 5);

    expect($again)->toHaveCount(0);
    expect(UserBadge::query()->where('user_id', $user->id)->count())->toBe(2);
    expect(UserBadge::query()->where('badge_id', $advanced->id)->exists())->toBeTrue();
    expect(UserBadge::query()->where('badge_id', $beginner->id)->exists())->toBeTrue();
});

test('recording the same achievement twice does not inflate the achievement count', function () {
    $user = User::factory()->create();

    $first = $this->service->recordUnlockedAchievement($user, achievementId: 100);
    $second = $this->service->recordUnlockedAchievement($user, achievementId: 100);

    expect($first)->toBe(1);
    expect($second)->toBe(1);
});

test('recording different achievements increments the achievement count', function () {
    $user = User::factory()->create();

    $this->service->recordUnlockedAchievement($user, achievementId: 1);
    $count = $this->service->recordUnlockedAchievement($user, achievementId: 2);

    expect($count)->toBe(2);
});
