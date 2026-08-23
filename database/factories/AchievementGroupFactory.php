<?php

namespace Database\Factories;

use App\Modules\Achievements\Models\AchievementGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AchievementGroup>
 */
class AchievementGroupFactory extends Factory
{
    protected $model = AchievementGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
        ];
    }
}
