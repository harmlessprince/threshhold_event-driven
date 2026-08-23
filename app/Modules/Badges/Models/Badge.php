<?php

namespace App\Modules\Badges\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'required_achievement_count', 'sort_order'])]
#[UseFactory(BadgeFactory::class)]
class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_achievement_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}
