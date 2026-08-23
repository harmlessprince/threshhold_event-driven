<?php

namespace App\Modules\Badges\Services;

use App\Models\User;
use App\Modules\Badges\Events\BadgeUnlocked;
use App\Modules\Badges\Models\Badge;
use App\Modules\Badges\Models\ProcessedAchievementUnlock;
use App\Modules\Badges\Models\UserAchievementCount;
use App\Modules\Badges\Models\UserBadge;
use Illuminate\Support\Collection;

class BadgeService
{
    /**
     * Record an unlocked achievement against the user's achievement count, exactly once
     * per achievement id, and return the (possibly unchanged) current count.
     * Redelivering AchievementUnlocked for the same achievement must not inflate it.
     */
    public function recordUnlockedAchievement(User $user, int $achievementId): int
    {
        $isNewAchievement = (bool) ProcessedAchievementUnlock::query()->insertOrIgnore([
            'user_id' => $user->id,
            'achievement_id' => $achievementId,
        ]);

        if ($isNewAchievement) {
            return UserAchievementCount::incrementFor($user);
        }

        return UserAchievementCount::query()->where('user_id', $user->id)->value('count') ?? 0;
    }

    /**
     * Unlock every badge the user is now eligible for and hasn't already been awarded,
     * firing BadgeUnlocked once per newly unlocked badge.
     *
     * @return Collection<int, Badge>
     */
    public function unlockEligibleBadges(User $user, int $achievementCount): Collection
    {
        $eligible = Badge::query()
            ->where('required_achievement_count', '<=', $achievementCount)
            ->get();

        if ($eligible->isEmpty()) {
            return collect();
        }

        $alreadyUnlockedIds = UserBadge::query()
            ->where('user_id', $user->id)
            ->whereIn('badge_id', $eligible->pluck('id'))
            ->pluck('badge_id');

        return $eligible
            ->reject(fn (Badge $badge) => $alreadyUnlockedIds->contains($badge->id))
            ->filter(function (Badge $badge) use ($user): bool {
                $userBadge = UserBadge::query()->firstOrCreate(
                    ['user_id' => $user->id, 'badge_id' => $badge->id],
                    ['unlocked_at' => now()],
                );

                return $userBadge->wasRecentlyCreated;
            })
            ->each(function (Badge $badge) use ($user): void {
                BadgeUnlocked::dispatch($badge->name, $user, $badge->id);
            })
            ->values();
    }
}
