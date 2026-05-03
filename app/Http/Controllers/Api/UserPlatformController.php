<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertUserPlatformRequest;
use App\Http\Resources\UserPlatformResource;
use App\Models\UserPlatform;
use Illuminate\Http\Request;

class UserPlatformController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = UserPlatform::query()
            ->with(['user:id,username', 'platform:id,name'])
            ->where('user_id', $authUserId)
            ->latest()
            ->paginate(20);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, UserPlatformResource::class),
            'User platforms fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertUserPlatformRequest $request)
    {
        $payload = $request->validated();
        $payload['user_id'] = (int) $request->user()->id;

        return $this->respondResource(
            new UserPlatformResource(UserPlatform::create($payload)),
            'User platform created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(UserPlatform $userPlatform)
    {
        $this->ensureOwner((int) $userPlatform->user_id, (int) request()->user()->id);

        return $this->respondResource(
            new UserPlatformResource($userPlatform->load(['user:id,username', 'platform:id,name'])),
            'User platform fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertUserPlatformRequest $request, UserPlatform $userPlatform)
    {
        $this->ensureOwner((int) $userPlatform->user_id, (int) $request->user()->id);

        $update = $request->validated();
        unset($update['user_id'], $update['platform_id']);
        $userPlatform->update($update);
        return $this->respondResource(
            new UserPlatformResource($userPlatform->fresh()),
            'User platform updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserPlatform $userPlatform)
    {
        $this->ensureOwner((int) $userPlatform->user_id, (int) request()->user()->id);
        $userPlatform->delete();
        return $this->respondDeleted('User platform deleted successfully.');
    }
}
