<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $fillable = ['name'];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_platform')
            ->withTimestamps();
    }

    public function userPlatforms(): HasMany
    {
        return $this->hasMany(UserPlatform::class);
    }
}
