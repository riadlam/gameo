<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertFriendshipRequest;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FriendshipController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = Friendship::query()
            ->with(['userOne:id,username', 'userTwo:id,username'])
            ->where(function ($q) use ($authUserId) {
                $q->where('user_one_id', $authUserId)
                    ->orWhere('user_two_id', $authUserId);
            })
            ->latest('created_at')
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, FriendshipResource::class),
            'Friendships fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertFriendshipRequest $request)
    {
        $authUserId = (int) $request->user()->id;
        $userTwo = (int) $request->validated('user_two_id');

        $exists = Friendship::query()
            ->where(function ($q) use ($authUserId, $userTwo) {
                $q->where('user_one_id', $authUserId)
                    ->where('user_two_id', $userTwo);
            })
            ->orWhere(function ($q) use ($authUserId, $userTwo) {
                $q->where('user_one_id', $userTwo)
                    ->where('user_two_id', $authUserId);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_two_id' => ['A friendship already exists between these users.'],
            ]);
        }

        return $this->respondResource(
            new FriendshipResource(Friendship::create([
                'user_one_id' => $authUserId,
                'user_two_id' => $userTwo,
                'status' => $request->validated('status'),
            ])),
            'Friendship created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Friendship $friendship)
    {
        $authUserId = (int) request()->user()->id;
        if ((int) $friendship->user_one_id !== $authUserId && (int) $friendship->user_two_id !== $authUserId) {
            $this->ensureOwner((int) $friendship->user_one_id, $authUserId);
        }
        return $this->respondResource(
            new FriendshipResource($friendship->load(['userOne:id,username', 'userTwo:id,username'])),
            'Friendship fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertFriendshipRequest $request, Friendship $friendship)
    {
        $authUserId = (int) $request->user()->id;
        if ((int) $friendship->user_one_id !== $authUserId && (int) $friendship->user_two_id !== $authUserId) {
            $this->ensureOwner((int) $friendship->user_one_id, $authUserId);
        }

        $friendship->update([
            'status' => $request->validated('status'),
        ]);
        return $this->respondResource(
            new FriendshipResource($friendship->fresh()),
            'Friendship updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Friendship $friendship)
    {
        $authUserId = (int) request()->user()->id;
        if ((int) $friendship->user_one_id !== $authUserId && (int) $friendship->user_two_id !== $authUserId) {
            $this->ensureOwner((int) $friendship->user_one_id, $authUserId);
        }
        $friendship->delete();
        return $this->respondDeleted('Friendship deleted successfully.');
    }
}
