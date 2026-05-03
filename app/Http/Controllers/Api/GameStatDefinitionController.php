<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertGameStatDefinitionRequest;
use App\Http\Resources\GameStatDefinitionResource;
use App\Models\GameStatDefinition;

class GameStatDefinitionController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginator = GameStatDefinition::query()
            ->with('game:id,name')
            ->orderBy('game_id')
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, GameStatDefinitionResource::class),
            'Game stat definitions fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertGameStatDefinitionRequest $request)
    {
        return $this->respondResource(
            new GameStatDefinitionResource(GameStatDefinition::create($request->validated())),
            'Game stat definition created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(GameStatDefinition $gameStatDefinition)
    {
        return $this->respondResource(
            new GameStatDefinitionResource($gameStatDefinition->load('game:id,name')),
            'Game stat definition fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertGameStatDefinitionRequest $request, GameStatDefinition $gameStatDefinition)
    {
        $gameStatDefinition->update($request->validated());
        return $this->respondResource(
            new GameStatDefinitionResource($gameStatDefinition->fresh()),
            'Game stat definition updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameStatDefinition $gameStatDefinition)
    {
        $gameStatDefinition->delete();
        return $this->respondDeleted('Game stat definition deleted successfully.');
    }
}
