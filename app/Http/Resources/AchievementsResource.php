<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementsResource extends JsonResource
{
    /**
     * The spec's contract has these fields at the top level, not wrapped in "data".
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unlocked_achievements' => $this->resource['unlocked_achievements'],
            'next_available_achievements' => $this->resource['next_available_achievements'],
            'current_badge' => $this->resource['current_badge'],
            'next_badge' => $this->resource['next_badge'],
            'remaining_to_unlock_next_badge' => $this->resource['remaining_to_unlock_next_badge'],
        ];
    }
}
