<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\UpsertFollowerRequest;
use App\Http\Resources\FollowerResource;
use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FollowerController extends BaseApiController
{
    /**
     * Whether the authenticated user has a blocked relationship with [otherUserId]
     * (either direction on the followers row, including synthetic block-only rows).
     */
    public function blockStatus(Request $request, int $otherUserId)
    {
        $authId = (int) $request->user()->id;
        if ($otherUserId === $authId) {
            return $this->respondSuccess(
                ['data' => ['is_blocked' => false]],
                'Block status fetched.'
            );
        }

        $row = Follower::query()
            ->where(function ($q) use ($authId, $otherUserId) {
                $q->where(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $authId)->where('following_id', $otherUserId);
                })->orWhere(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $otherUserId)->where('following_id', $authId);
                });
            })
            ->first();

        return $this->respondSuccess(
            ['data' => ['is_blocked' => $row ? (bool) $row->is_blocked : false]],
            'Block status fetched.'
        );
    }

    /**
     * Mark the relationship between the authenticated user and [otherUserId] as blocked
     * (updates any follower row in either direction, or creates a block-only row).
     */
    public function block(Request $request, int $otherUserId)
    {
        $authId = (int) $request->user()->id;
        if ($otherUserId === $authId) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot block yourself.'],
            ]);
        }

        User::query()->whereKey($otherUserId)->firstOrFail();

        $affected = Follower::query()
            ->where(function ($q) use ($authId, $otherUserId) {
                $q->where(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $authId)->where('following_id', $otherUserId);
                })->orWhere(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $otherUserId)->where('following_id', $authId);
                });
            })
            ->update(['is_blocked' => true]);

        if ($affected === 0) {
            Follower::query()->create([
                'follower_id' => $authId,
                'following_id' => $otherUserId,
                'is_blocked' => true,
            ]);
        }

        return $this->respondSuccess(
            ['data' => ['is_blocked' => true]],
            'User blocked.'
        );
    }

    /**
     * Clear blocked state for any follower row between the authenticated user and [otherUserId].
     */
    public function unblock(Request $request, int $otherUserId)
    {
        $authId = (int) $request->user()->id;
        if ($otherUserId === $authId) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot unblock yourself.'],
            ]);
        }

        Follower::query()
            ->where(function ($q) use ($authId, $otherUserId) {
                $q->where(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $authId)->where('following_id', $otherUserId);
                })->orWhere(function ($x) use ($authId, $otherUserId) {
                    $x->where('follower_id', $otherUserId)->where('following_id', $authId);
                });
            })
            ->update(['is_blocked' => false]);

        return $this->respondSuccess(
            ['data' => ['is_blocked' => false]],
            'User unblocked.'
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = Follower::query()
            ->with(['follower:id,username', 'following:id,username'])
            ->where(function ($q) use ($authUserId) {
                $q->where('follower_id', $authUserId)
                    ->orWhere('following_id', $authUserId);
            })
            ->latest('created_at')
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, FollowerResource::class),
            'Followers fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertFollowerRequest $request)
    {
        $authUserId = (int) $request->user()->id;
        $followingId = (int) $request->validated('following_id');

        $exists = Follower::query()
            ->where('follower_id', $authUserId)
            ->where('following_id', $followingId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'following_id' => ['You already follow this user.'],
            ]);
        }

        return $this->respondResource(
            new FollowerResource(Follower::create([
                'follower_id' => $authUserId,
                'following_id' => $followingId,
                'is_blocked' => false,
            ])),
            'Follower relation created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Follower $follower)
    {
        $authUserId = (int) request()->user()->id;
        if ((int) $follower->follower_id !== $authUserId && (int) $follower->following_id !== $authUserId) {
            $this->ensureOwner((int) $follower->follower_id, $authUserId);
        }
        return $this->respondResource(
            new FollowerResource($follower->load(['follower:id,username', 'following:id,username'])),
            'Follower relation fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertFollowerRequest $request, Follower $follower)
    {
        $this->ensureOwner((int) $follower->follower_id, (int) $request->user()->id);
        $follower->update([
            'following_id' => (int) $request->validated('following_id'),
        ]);
        return $this->respondResource(
            new FollowerResource($follower->fresh()),
            'Follower relation updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Follower $follower)
    {
        $this->ensureOwner((int) $follower->follower_id, (int) request()->user()->id);
        $follower->delete();
        return $this->respondDeleted('Follower relation deleted successfully.');
    }
}
