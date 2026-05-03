<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FriendUserResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FriendController extends BaseApiController
{
    /**
     * People you follow or who follow you (union of both sides of `followers`), deduped.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $followingIds = Follower::query()
            ->where('follower_id', $authUserId)
            ->pluck('following_id');
        $followerIds = Follower::query()
            ->where('following_id', $authUserId)
            ->pluck('follower_id');

        $ids = $followingIds
            ->merge($followerIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $id !== $authUserId)
            ->values();

        if ($ids->isEmpty()) {
            $paginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return $this->respondCollection(
                new PaginatedResourceCollection($paginator, FriendUserResource::class),
                'Friends fetched successfully.'
            );
        }

        $paginator = User::query()
            ->select('users.*')
            ->selectSub(function ($q) {
                $q->from('user_games')
                    ->join('games', 'games.id', '=', 'user_games.game_id')
                    ->whereColumn('user_games.user_id', 'users.id')
                    ->select('games.name')
                    ->orderBy('user_games.game_id')
                    ->limit(1);
            }, 'first_game_name')
            ->whereIn('users.id', $ids->all())
            ->orderByDesc('users.is_online')
            ->orderBy('users.username')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, FriendUserResource::class),
            'Friends fetched successfully.'
        );
    }
}
