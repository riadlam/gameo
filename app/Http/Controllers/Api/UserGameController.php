<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertUserGameRequest;
use App\Http\Resources\UserGameResource;
use App\Models\UserGame;
use Illuminate\Http\Request;

class UserGameController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = UserGame::query()
            ->with(['user:id,username,is_online,region', 'game:id,name,image'])
            ->where('user_id', $authUserId)
            ->latest('created_at')
            ->paginate(20);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, UserGameResource::class),
            'User games fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertUserGameRequest $request)
    {
        $payload = $request->validated();
        $payload['user_id'] = (int) $request->user()->id;

        return $this->respondResource(
            new UserGameResource(UserGame::create($payload)),
            'User game created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(UserGame $userGame)
    {
        $this->ensureOwner((int) $userGame->user_id, (int) request()->user()->id);

        return $this->respondResource(
            new UserGameResource($userGame->load(['user:id,username', 'game:id,name,image'])),
            'User game fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertUserGameRequest $request, UserGame $userGame)
    {
        $this->ensureOwner((int) $userGame->user_id, (int) $request->user()->id);

        $update = $request->validated();
        unset($update['user_id'], $update['game_id']);
        $userGame->update($update);
        return $this->respondResource(
            new UserGameResource($userGame->fresh()),
            'User game updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserGame $userGame)
    {
        $this->ensureOwner((int) $userGame->user_id, (int) request()->user()->id);
        $userGame->delete();
        return $this->respondDeleted('User game deleted successfully.');
    }
}
