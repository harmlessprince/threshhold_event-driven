<?php

namespace App\Modules\Badges\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'count'])]
class UserAchievementCount extends Model
{
    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Atomically increment (and, on first unlock, create) this user's unlocked-achievement
     * count. Same row-lock-plus-SQL-increment pattern as UserPurchaseCount, for the same
     * reason: a single UPDATE ... SET count = count + 1 can't lose a concurrent update.
     */
    public static function incrementFor(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $row = self::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if (! $row) {
                $row = self::query()->create(['user_id' => $user->id, 'count' => 0]);
            }

            $row->increment('count');

            return $row->count;
        });
    }
}
