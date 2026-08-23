<?php

use App\Models\User;
use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Badges\Models\Badge;
use App\Modules\Badges\Models\UserBadge;
use Illuminate\Support\Facades\Event;

test('an unlocked achievement unlocks an eligible badge and fires BadgeUnlocked', function () {
    $badge = Badge::factory()->create(['required_achievement_count' => 1]);
    $user = User::factory()->create();

    Event::fake([BadgeUnlocked::class]);

    AchievementUnlocked::dispatch('First Purchase', $user, 1);

    Event::assertDispatched(
        BadgeUnlocked::class,
        fn (BadgeUnlocked $event) => $event->badge_name === $badge->name
            && $event->user->is($user)
    );

    expect(UserBadge::query()
        ->where('user_id', $user->id)
        ->where('badge_id', $badge->id)
        ->exists())->toBeTrue();
});

test('firing AchievementUnlocked twice for the same achievement does not double-unlock the badge', function () {
    Badge::factory()->create(['required_achievement_count' => 1]);
    $user = User::factory()->create();

    AchievementUnlocked::dispatch('First Purchase', $user, 1);
    AchievementUnlocked::dispatch('First Purchase', $user, 1);

    expect(UserBadge::query()->where('user_id', $user->id)->count())->toBe(1);
});
