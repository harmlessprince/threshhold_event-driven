<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Badges\Models\Badge;
use App\Modules\Badges\Models\UserBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBadge>
 */
class UserBadgeFactory extends Factory
{
    protected $model = UserBadge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'badge_id' => Badge::factory(),
            'unlocked_at' => now(),
        ];
    }
}
