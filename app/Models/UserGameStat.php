<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class UserGameStat extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'stat_definition_id',
        'season',
        'value_int',
        'value_decimal',
        'value_text',
        'value_rank_tier_id',
    ];

    protected function casts(): array
    {
        return [
            'value_decimal' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(GameStatDefinition::class, 'stat_definition_id');
    }

    public function rankTier(): BelongsTo
    {
        return $this->belongsTo(GameRankTier::class, 'value_rank_tier_id');
    }
}
