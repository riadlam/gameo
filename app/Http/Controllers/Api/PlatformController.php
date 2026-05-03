<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertPlatformRequest;
use App\Http\Resources\PlatformResource;
use App\Models\Platform;

class PlatformController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginator = Platform::query()->orderBy('name')->paginate(20);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, PlatformResource::class),
            'Platforms fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertPlatformRequest $request)
    {
        return $this->respondResource(
            new PlatformResource(Platform::create($request->validated())),
            'Platform created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Platform $platform)
    {
        return $this->respondResource(
            new PlatformResource($platform),
            'Platform fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertPlatformRequest $request, Platform $platform)
    {
        $platform->update($request->validated());
        return $this->respondResource(
            new PlatformResource($platform->fresh()),
            'Platform updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Platform $platform)
    {
        $platform->delete();
        return $this->respondDeleted('Platform deleted successfully.');
    }
}
