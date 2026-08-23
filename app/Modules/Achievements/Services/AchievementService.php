<?php

namespace App\Modules\Achievements\Services;

use App\Models\User;
use App\Modules\Achievements\Events\AchievementUnlocked;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Models\AchievementGroup;
use App\Modules\Achievements\Models\ProcessedOrder;
use App\Modules\Achievements\Models\UserAchievement;
use App\Modules\Achievements\Models\UserPurchaseCount;
use Illuminate\Support\Collection;

class AchievementService
{
    /**
     * Record a completed order against the user's purchase count, exactly once per
     * order id, and return the (possibly unchanged) current count. Redelivering
     * OrderCompleted for the same order must not inflate the count.
     */
    public function recordCompletedOrder(User $user, int $orderId): int
    {
        $isNewOrder = (bool) ProcessedOrder::query()->insertOrIgnore([
            'user_id' => $user->id,
            'order_id' => $orderId,
        ]);

        if ($isNewOrder) {
            return UserPurchaseCount::incrementFor($user);
        }

        return UserPurchaseCount::query()->where('user_id', $user->id)->value('count') ?? 0;
    }

    /**
     * Unlock every purchase-count achievement the user is now eligible for and hasn't
     * already unlocked, firing AchievementUnlocked once per newly unlocked achievement.
     *
     * @return Collection<int, Achievement>
     */
    public function unlockEligibleAchievements(User $user, int $purchaseCount): Collection
    {
        $eligible = Achievement::query()
            ->where('trigger_type', 'purchase_count')
            ->where('threshold', '<=', $purchaseCount)
            ->get();

        if ($eligible->isEmpty()) {
            return collect();
        }

        $alreadyUnlockedIds = UserAchievement::query()
            ->where('user_id', $user->id)
            ->whereIn('achievement_id', $eligible->pluck('id'))
            ->pluck('achievement_id');

        return $eligible
            ->reject(fn (Achievement $achievement) => $alreadyUnlockedIds->contains($achievement->id))
            ->filter(function (Achievement $achievement) use ($user): bool {
                $userAchievement = UserAchievement::query()->firstOrCreate(
                    ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                    ['unlocked_at' => now()],
                );

                return $userAchievement->wasRecentlyCreated;
            })
            ->each(function (Achievement $achievement) use ($user): void {
                AchievementUnlocked::dispatch($achievement->name, $user, $achievement->id);
            })
            ->values();
    }

    /**
     * Names of the user's unlocked achievements, and the next achievement (by
     * sort_order) still available in each group. Groups the user has fully
     * completed are omitted from "next".
     *
     * @return array{unlocked_achievements: array<int, string>, next_available_achievements: array<int, string>}
     */
    public function summaryFor(User $user): array
    {
        $unlockedIds = UserAchievement::query()
            ->where('user_id', $user->id)
            ->pluck('achievement_id');

        $unlockedAchievements = UserAchievement::query()
            ->where('user_id', $user->id)
            ->orderBy('unlocked_at')
            ->with('achievement')
            ->get()
            ->pluck('achievement.name');

        $nextAvailableAchievements = AchievementGroup::query()
            ->with(['achievements' => fn ($query) => $query->orderBy('sort_order')])
            ->get()
            ->map(fn (AchievementGroup $group) => $group->achievements->first(
                fn (Achievement $achievement) => ! $unlockedIds->contains($achievement->id)
            ))
            ->filter()
            ->pluck('name');

        return [
            'unlocked_achievements' => $unlockedAchievements->values()->all(),
            'next_available_achievements' => $nextAvailableAchievements->values()->all(),
        ];
    }
}
