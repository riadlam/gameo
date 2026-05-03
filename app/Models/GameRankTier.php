<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GameRankTier extends Model
{
    protected $fillable = [
        'game_id',
        'game_platform_id',
        'code',
        'label',
        'order_index',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(GamePlatform::class);
    }

    public function statsUsingTier(): HasMany
    {
        return $this->hasMany(UserGameStat::class, 'value_rank_tier_id');
    }

    public function userRanks(): HasMany
    {
        return $this->hasMany(UserGameRank::class);
    }
}
