<?php

namespace Database\Seeders;

use App\Modules\Badges\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        Badge::query()->updateOrCreate(
            ['slug' => 'beginner'],
            [
                'name' => 'Beginner',
                'required_achievement_count' => 2,
                'sort_order' => 1,
            ],
        );

        Badge::query()->updateOrCreate(
            ['slug' => 'advanced'],
            [
                'name' => 'Advanced',
                'required_achievement_count' => 5,
                'sort_order' => 2,
            ],
        );
    }
}
