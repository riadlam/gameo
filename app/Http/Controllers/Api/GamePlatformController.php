<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpsertGamePlatformRequest;
use App\Http\Resources\GamePlatformResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\GamePlatform;

class GamePlatformController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginator = GamePlatform::query()
            ->with(['game:id,name,image', 'platform:id,name'])
            ->latest()
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, GamePlatformResource::class),
            'Game-platform relations fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertGamePlatformRequest $request)
    {
        return $this->respondResource(
            new GamePlatformResource(GamePlatform::create($request->validated())),
            'Game-platform relation created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(GamePlatform $gamePlatform)
    {
        return $this->respondResource(
            new GamePlatformResource($gamePlatform->load(['game:id,name,image', 'platform:id,name'])),
            'Game-platform relation fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertGamePlatformRequest $request, GamePlatform $gamePlatform)
    {
        $gamePlatform->update($request->validated());
        return $this->respondResource(
            new GamePlatformResource($gamePlatform->fresh()),
            'Game-platform relation updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GamePlatform $gamePlatform)
    {
        $gamePlatform->delete();
        return $this->respondDeleted('Game-platform relation deleted successfully.');
    }
}
