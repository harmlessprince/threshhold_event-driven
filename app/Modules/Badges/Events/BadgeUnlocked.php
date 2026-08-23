<?php

namespace App\Modules\Badges\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BadgeUnlocked
{
    use Dispatchable, SerializesModels;

    /**
     * badge_name and user are the spec's required payload shape (literal snake_case,
     * not Laravel's usual camelCase). badge_id rides along as an extra field for
     * subscribers (e.g. Payments' cashback idempotency guard) that need a stable
     * identifier — badge name isn't guaranteed unique, only its slug is.
     */
    public function __construct(
        public readonly string $badge_name,
        public readonly User $user,
        public readonly int $badge_id,
    ) {}
}
