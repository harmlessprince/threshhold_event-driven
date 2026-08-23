<?php

namespace App\Modules\Achievements\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'count'])]
class UserPurchaseCount extends Model
{
    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Atomically increment (and, on first purchase, create) this user's completed-order
     * count. Uses a row lock inside a transaction plus a SQL-level increment so
     * concurrent OrderCompleted events for the same user can't lose an update.
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
