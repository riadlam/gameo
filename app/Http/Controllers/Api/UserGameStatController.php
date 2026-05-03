<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertUserGameStatRequest;
use App\Http\Resources\UserGameStatResource;
use App\Models\UserGameStat;
use Illuminate\Http\Request;

class UserGameStatController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = UserGameStat::query()
            ->with([
                'user:id,username',
                'game:id,name',
                'definition:id,key,label,value_type',
                'rankTier:id,game_id,label',
            ])
            ->where('user_id', $authUserId)
            ->latest()
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, UserGameStatResource::class),
            'User game stats fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertUserGameStatRequest $request)
    {
        $payload = $request->validated();
        $payload['user_id'] = (int) $request->user()->id;

        return $this->respondResource(
            new UserGameStatResource(UserGameStat::create($payload)),
            'User game stat created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(UserGameStat $userGameStat)
    {
        $this->ensureOwner((int) $userGameStat->user_id, (int) request()->user()->id);
        return $this->respondResource(
            new UserGameStatResource(
                $userGameStat->load(['user:id,username', 'game:id,name', 'definition', 'rankTier'])
            ),
            'User game stat fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertUserGameStatRequest $request, UserGameStat $userGameStat)
    {
        $this->ensureOwner((int) $userGameStat->user_id, (int) $request->user()->id);

        $update = $request->validated();
        unset($update['user_id'], $update['game_id'], $update['stat_definition_id']);
        $userGameStat->update($update);
        return $this->respondResource(
            new UserGameStatResource($userGameStat->fresh()),
            'User game stat updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserGameStat $userGameStat)
    {
        $this->ensureOwner((int) $userGameStat->user_id, (int) request()->user()->id);
        $userGameStat->delete();
        return $this->respondDeleted('User game stat deleted successfully.');
    }
}
