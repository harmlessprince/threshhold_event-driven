<?php

use App\Modules\Achievements\Models\AchievementGroup;
use Database\Seeders\AchievementSeeder;

test('seeds the purchases group with first purchase and 5 purchases achievements', function () {
    $this->seed(AchievementSeeder::class);

    $group = AchievementGroup::query()->where('slug', 'purchases')->firstOrFail();

    expect($group->achievements)->toHaveCount(2);

    expect($group->achievements->pluck('threshold', 'name')->all())->toBe([
        'First Purchase' => 1,
        '5 Purchases' => 5,
    ]);
});
