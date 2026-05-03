<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertGameRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\Request;

class GameController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $platformIds = collect(explode(',', (string) $request->query('platform_ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $platformNames = collect(explode(',', (string) $request->query('platform_names')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();

        $paginator = Game::query()
            ->with('platforms')
            ->when(
                !empty($platformIds),
                fn ($query) => $query->whereHas(
                    'platforms',
                    fn ($platformQuery) => $platformQuery->whereIn('platforms.id', $platformIds)
                )
            )
            ->when(
                !empty($platformNames),
                fn ($query) => $query->whereHas(
                    'platforms',
                    fn ($platformQuery) => $platformQuery->whereIn('platforms.name', $platformNames)
                )
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where('name', 'like', "%{$search}%")
            )
            ->orderByDesc('is_populer')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 10));

        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, GameResource::class),
            'Games fetched successfully.'
        );
    }

    /**
     * All game ids linked to at least one of the given platform names (OR).
     * Used by the client to trim selections without walking every catalog page.
     */
    public function idsForPlatforms(Request $request)
    {
        $platformNames = collect(explode(',', (string) $request->query('platform_names', '')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();

        if ($platformNames === []) {
            return $this->respondSuccess(['data' => []], 'No platforms provided.');
        }

        $ids = Game::query()
            ->whereHas('platforms', function ($query) use ($platformNames) {
                $query->whereIn('platforms.name', $platformNames);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $this->respondSuccess(
            ['data' => $ids],
            'Game ids for platforms fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertGameRequest $request)
    {
        return $this->respondResource(
            new GameResource(Game::create($request->validated())),
            'Game created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Game $game)
    {
        return $this->respondResource(
            new GameResource($game->load(['platforms', 'rankTiers', 'statDefinitions'])),
            'Game fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertGameRequest $request, Game $game)
    {
        $game->update($request->validated());
        return $this->respondResource(
            new GameResource($game->fresh()),
            'Game updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Game $game)
    {
        $game->delete();
        return $this->respondDeleted('Game deleted successfully.');
    }
}
