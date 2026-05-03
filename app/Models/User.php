<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'firebase_uid',
        'firebase_id_token',
        'fcm_token',
        'fcm_token_updated_at',
        'password',
        'gender',
        'birth_date',
        'region',
        'bio',
        'avatar',
        'first_cover',
        'profile_images',
        'is_onboarding',
        'is_online',
        'last_seen',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'firebase_id_token',
        'fcm_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'birth_date' => 'date',
            'profile_images' => 'array',
            'is_onboarding' => 'boolean',
            'is_online' => 'boolean',
            'last_seen' => 'datetime',
            'fcm_token_updated_at' => 'datetime',
        ];
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'user_games')
            ->withPivot(['skill_level', 'play_time_hours', 'created_at']);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'user_platforms')
            ->withPivot(['username_on_platform', 'created_at', 'updated_at']);
    }

    public function userGames(): HasMany
    {
        return $this->hasMany(UserGame::class);
    }

    public function userPlatforms(): HasMany
    {
        return $this->hasMany(UserPlatform::class);
    }

    public function gameStats(): HasMany
    {
        return $this->hasMany(UserGameStat::class);
    }

    public function gameRanks(): HasMany
    {
        return $this->hasMany(UserGameRank::class);
    }

    /**
     * Rank label from `user_game_ranks` → `game_rank_tiers` for a library game (`user_games.game_id`).
     * Tiers may be keyed by `game_rank_tiers.game_id` or by `game_platform_id` rows whose `game_id` matches.
     *
     * @param  list<int>  $gamePlatformIdsForGame  ids from `game_platform` where `game_id` = $gameId
     */
    public function rankLabelForGameId(int $gameId, array $gamePlatformIdsForGame): ?string
    {
        $best = $this->gameRanks
            ->filter(function (UserGameRank $ur) use ($gameId, $gamePlatformIdsForGame) {
                $tier = $ur->gameRankTier;
                if ($tier === null) {
                    return false;
                }
                if ((int) $tier->game_id === $gameId) {
                    return true;
                }
                $gpId = $tier->game_platform_id;
                if ($gpId !== null && in_array((int) $gpId, $gamePlatformIdsForGame, true)) {
                    return true;
                }

                return false;
            })
            ->sortByDesc(fn (UserGameRank $ur) => (int) ($ur->gameRankTier?->order_index ?? 0))
            ->first();

        if ($best === null) {
            return null;
        }

        $tier = $best->gameRankTier;
        if ($tier === null) {
            return null;
        }

        $label = trim((string) $tier->label);
        if ($label !== '') {
            return $label;
        }

        $code = trim((string) $tier->code);

        return $code !== '' ? $code : null;
    }

    public function sentMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'user_id');
    }

    public function receivedMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'target_user_id');
    }

    public function following(): HasMany
    {
        return $this->hasMany(Follower::class, 'follower_id');
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class, 'following_id');
    }

    public function friendshipsAsUserOne(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_one_id');
    }

    public function friendshipsAsUserTwo(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_two_id');
    }

    /**
     * Distinct platform keys (mobile, pc, xbox, playstation) from `user_platforms`.
     *
     * @return list<string>
     */
    public function gamingPlatformKeys(): array
    {
        $nameToKey = [
            'Mobile' => 'mobile',
            'PC' => 'pc',
            'Xbox' => 'xbox',
            'PlayStation' => 'playstation',
        ];

        $names = $this->userPlatforms()
            ->join('platforms', 'platforms.id', '=', 'user_platforms.platform_id')
            ->distinct()
            ->pluck('platforms.name');

        $keys = [];
        foreach ($names as $name) {
            $key = $nameToKey[(string) $name] ?? null;
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Game ids from `user_games`.
     *
     * @return list<int>
     */
    public function gamingGameIds(): array
    {
        return $this->userGames()
            ->distinct()
            ->orderBy('game_id')
            ->pluck('game_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Presence for API/clients: must look recently active **and** not explicitly offline.
     * Crashes can leave [is_online] true with a stale [last_seen] → treated offline after N seconds.
     * [/set-offline] clears [is_online] so the user drops offline immediately even if [last_seen] is fresh.
     */
    public function isEffectivelyOnline(int $withinSeconds = 60): bool
    {
        if (! $this->is_online) {
            return false;
        }
        if ($this->last_seen === null) {
            return false;
        }

        return $this->last_seen->greaterThanOrEqualTo(now()->subSeconds($withinSeconds));
    }
}

