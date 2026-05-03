<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GameStatDefinition extends Model
{
    protected $fillable = [
        'game_id',
        'key',
        'label',
        'value_type',
        'unit',
        'is_seasonal',
    ];

    protected function casts(): array
    {
        return [
            'is_seasonal' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(UserGameStat::class, 'stat_definition_id');
    }
}
