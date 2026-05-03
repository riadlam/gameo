<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGameRank extends Model
{
    protected $fillable = [
        'user_id',
        'game_rank_tier_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameRankTier(): BelongsTo
    {
        return $this->belongsTo(GameRankTier::class);
    }
}

