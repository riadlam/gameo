<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Follower extends Model
{
    protected $table = 'followers';
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'follower_id',
        'following_id',
        'is_blocked',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'is_blocked' => 'boolean',
        ];
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
