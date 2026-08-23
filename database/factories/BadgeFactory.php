<?php

namespace Database\Factories;

use App\Modules\Badges\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'required_achievement_count' => $this->faker->numberBetween(1, 10),
            'sort_order' => 0,
        ];
    }
}
