<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GamePlatform extends Model
{
    protected $table = 'game_platform';

    protected $fillable = [
        'game_id',
        'platform_id',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function rankTiers(): HasMany
    {
        return $this->hasMany(GameRankTier::class);
    }
}
