<?php

namespace Database\Seeders;

use App\Modules\Achievements\Models\AchievementGroup;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $purchases = AchievementGroup::query()->updateOrCreate(
            ['slug' => 'purchases'],
            ['name' => 'Purchases'],
        );

        $purchases->achievements()->updateOrCreate(
            ['slug' => 'first-purchase'],
            [
                'name' => 'First Purchase',
                'trigger_type' => 'purchase_count',
                'threshold' => 1,
                'sort_order' => 1,
            ],
        );

        $purchases->achievements()->updateOrCreate(
            ['slug' => 'five-purchases'],
            [
                'name' => '5 Purchases',
                'trigger_type' => 'purchase_count',
                'threshold' => 5,
                'sort_order' => 2,
            ],
        );
    }
}
