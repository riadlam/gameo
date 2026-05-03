<?php

namespace App\Http\Resources;

use App\Models\GamePlatform;
use App\Models\User;
use App\Models\UserGame;
use App\Services\UserProfilePhotoService;
use App\Support\ApiPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-ish profile for another user (counts from DB via `withCount`).
 */
class UserPublicProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $user->loadMissing(['gameRanks.gameRankTier']);

        $photoSlots = UserProfilePhotoService::slotsFromUser($user);
        $main = UserProfilePhotoService::mainUrl($photoSlots) ?? $user->first_cover;

        $platformIdsByGameId = [];

        $viewer = $request->user();
        $viewerGameIdSet = [];
        if ($viewer) {
            if ((int) $viewer->id === (int) $user->id) {
                $viewerGameIdSet = $user->relationLoaded('userGames')
                    ? $user->userGames->pluck('game_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
                    : [];
            } else {
                $viewerGameIdSet = UserGame::query()
                    ->where('user_id', $viewer->id)
                    ->pluck('game_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        $sortedUserGames = $user->relationLoaded('userGames')
            ? $user->userGames->sortBy(function ($ug) use ($viewerGameIdSet) {
                $gid = (int) $ug->game_id;
                $common = in_array($gid, $viewerGameIdSet, true);

                return [$common ? 0 : 1, strtolower($ug->game?->name ?? ''), $gid];
            })->values()
            : collect();

        $commonGamesPayload = $sortedUserGames
            ->filter(fn ($ug) => in_array((int) $ug->game_id, $viewerGameIdSet, true))
            ->sortBy(fn ($ug) => strtolower($ug->game?->name ?? ''))
            ->values()
            ->map(fn ($ug) => [
                'game_id' => (int) $ug->game_id,
                'name' => $ug->game?->name ?? 'Unknown',
                'image' => ApiPublicUrl::rewrite($ug->game?->image, $request),
            ])->all();

        $gameIdsForPlatforms = $sortedUserGames
            ->pluck('game_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var array<int, list<string>> */
        $platformKeysByGameId = [];
        if ($gameIdsForPlatforms !== []) {
            $nameToKey = [
                'Mobile' => 'mobile',
                'PC' => 'pc',
                'Xbox' => 'xbox',
                'PlayStation' => 'playstation',
            ];
            $rows = GamePlatform::query()
                ->whereIn('game_id', $gameIdsForPlatforms)
                ->with('platform:id,name')
                ->orderBy('id')
                ->get();
            foreach ($rows as $gp) {
                $gid = (int) $gp->game_id;
                $name = (string) ($gp->platform?->name ?? '');
                $key = $nameToKey[$name] ?? null;
                if ($key === null) {
                    continue;
                }
                if (! isset($platformKeysByGameId[$gid])) {
                    $platformKeysByGameId[$gid] = [];
                }
                if (! in_array($key, $platformKeysByGameId[$gid], true)) {
                    $platformKeysByGameId[$gid][] = $key;
                }
            }
        }

        $profileUserPlatformKeys = $user->gamingPlatformKeys();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'firebase_uid' => $user->firebase_uid,
            'avatar' => ApiPublicUrl::rewrite($user->avatar, $request),
            'first_cover' => ApiPublicUrl::rewrite($main, $request),
            'profile_images' => ApiPublicUrl::rewriteProfileSlots($photoSlots, $request),
            'bio' => $user->bio,
            'gender' => $user->gender,
            'region' => $user->region,
            'is_online' => $user->isEffectivelyOnline(60),
            'last_seen' => $user->last_seen?->toIso8601String(),
            'followers_count' => (int) ($user->followers_count ?? 0),
            'following_count' => (int) ($user->following_count ?? 0),
            'games_count' => (int) ($user->user_games_count ?? 0),
            'common_games_count' => count($commonGamesPayload),
            'common_games' => $commonGamesPayload,
            'games' => $user->relationLoaded('userGames')
                ? $sortedUserGames->map(function ($ug) use ($request, $user, &$platformIdsByGameId, $viewerGameIdSet, $platformKeysByGameId, $profileUserPlatformKeys) {
                    $game = $ug->game;
                    $gameId = (int) $ug->game_id;
                    if (! array_key_exists($gameId, $platformIdsByGameId)) {
                        $platformIdsByGameId[$gameId] = GamePlatform::query()
                            ->where('game_id', $gameId)
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all();
                    }

                    return [
                        'user_game_id' => (int) $ug->id,
                        'game_id' => $gameId,
                        'name' => $game?->name ?? 'Unknown',
                        'image' => ApiPublicUrl::rewrite($game?->image, $request),
                        'play_time_hours' => $ug->play_time_hours !== null ? (int) $ug->play_time_hours : null,
                        'rank_label' => $user->rankLabelForGameId($gameId, $platformIdsByGameId[$gameId]),
                        'in_common' => in_array($gameId, $viewerGameIdSet, true),
                        'platform_key' => self::displayPlatformKeyForGame(
                            $gameId,
                            $platformKeysByGameId,
                            $profileUserPlatformKeys,
                        ),
                    ];
                })->values()->all()
                : [],
        ];
    }

    /**
     * Pick one UI platform key for a game: prefer a platform both the game supports and the user linked.
     *
     * @param  array<int, list<string>>  $platformKeysByGameId
     * @param  list<string>  $userPlatformKeys
     */
    private static function displayPlatformKeyForGame(
        int $gameId,
        array $platformKeysByGameId,
        array $userPlatformKeys,
    ): ?string {
        $available = $platformKeysByGameId[$gameId] ?? [];
        if ($available === []) {
            return null;
        }

        $userSet = array_flip($userPlatformKeys);
        $priority = ['pc', 'xbox', 'playstation', 'mobile'];

        foreach ($priority as $k) {
            if (isset($userSet[$k]) && in_array($k, $available, true)) {
                return $k;
            }
        }

        foreach ($priority as $k) {
            if (in_array($k, $available, true)) {
                return $k;
            }
        }

        return $available[0];
    }
}
