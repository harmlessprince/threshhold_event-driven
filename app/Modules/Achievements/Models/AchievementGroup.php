<?php

namespace App\Modules\Achievements\Models;

use Database\Factories\AchievementGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
#[UseFactory(AchievementGroupFactory::class)]
class AchievementGroup extends Model
{
    /** @use HasFactory<AchievementGroupFactory> */
    use HasFactory;

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class)->orderBy('sort_order');
    }
}
