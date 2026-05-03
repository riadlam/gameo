<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class UserPlatform extends Model
{
    protected $fillable = [
        'user_id',
        'platform_id',
        'game_platform_id',
        'username_on_platform',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(GamePlatform::class);
    }
}
