<?php

namespace App\Http\Controllers;

use App\Http\Resources\AchievementsResource;
use App\Models\User;
use App\Modules\Achievements\Services\AchievementService;
use App\Modules\Badges\Services\BadgeService;

class AchievementsController extends Controller
{
    public function __construct(
        private readonly AchievementService $achievements,
        private readonly BadgeService $badges,
    ) {}

    public function show(User $user): AchievementsResource
    {
        return new AchievementsResource([
            ...$this->achievements->summaryFor($user),
            ...$this->badges->summaryFor($user),
        ]);
    }
}
