<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserPublicProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserPublicProfileController extends BaseApiController
{
    /**
     * Profile card for another user (follower/following counts from `followers` table).
     */
    public function show(Request $request, User $user)
    {
        $profile = User::query()
            ->whereKey($user->getKey())
            ->withCount(['followers', 'following', 'userGames'])
            ->with([
                'userGames' => fn ($q) => $q->orderBy('game_id')->with('game:id,name,image'),
                'gameRanks.gameRankTier',
            ])
            ->firstOrFail();

        return $this->respondResource(
            new UserPublicProfileResource($profile),
            'User profile fetched successfully.'
        );
    }
}
