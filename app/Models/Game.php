<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = ['rawg_id', 'name', 'image', 'is_populer'];

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'game_platform')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function userGames(): HasMany
    {
        return $this->hasMany(UserGame::class);
    }

    public function rankTiers(): HasMany
    {
        return $this->hasMany(GameRankTier::class);
    }

    public function statDefinitions(): HasMany
    {
        return $this->hasMany(GameStatDefinition::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(UserGameStat::class);
    }
}
