<?php

namespace Database\Factories;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Models\AchievementGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'achievement_group_id' => AchievementGroup::factory(),
            'name' => ucfirst($name),
            'slug' => str($name)->slug(),
            'trigger_type' => 'purchase_count',
            'threshold' => $this->faker->numberBetween(1, 10),
            'sort_order' => 0,
        ];
    }
}
