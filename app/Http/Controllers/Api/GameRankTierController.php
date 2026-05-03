<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertGameRankTierRequest;
use App\Http\Resources\GameRankTierResource;
use App\Models\GameRankTier;

class GameRankTierController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginator = GameRankTier::query()
            ->with('game:id,name')
            ->orderBy('game_id')
            ->orderBy('order_index')
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, GameRankTierResource::class),
            'Game rank tiers fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertGameRankTierRequest $request)
    {
        return $this->respondResource(
            new GameRankTierResource(GameRankTier::create($request->validated())),
            'Game rank tier created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(GameRankTier $gameRankTier)
    {
        return $this->respondResource(
            new GameRankTierResource($gameRankTier->load('game:id,name')),
            'Game rank tier fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertGameRankTierRequest $request, GameRankTier $gameRankTier)
    {
        $gameRankTier->update($request->validated());
        return $this->respondResource(
            new GameRankTierResource($gameRankTier->fresh()),
            'Game rank tier updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameRankTier $gameRankTier)
    {
        $gameRankTier->delete();
        return $this->respondDeleted('Game rank tier deleted successfully.');
    }
}
