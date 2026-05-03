<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\MatchCandidatesRequest;
use App\Http\Requests\UpsertMatchRequest;
use App\Http\Resources\MatchResource;
use App\Models\Game;
use App\Services\MatchFirestoreConversationService;
use App\Models\GamePlatform;
use App\Models\MatchModel;
use App\Models\Platform;
use App\Models\User;
use App\Services\UserProfilePhotoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MatchController extends BaseApiController
{
    public function __construct(
        private readonly MatchFirestoreConversationService $matchFirestoreConversation,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUserId = (int) $request->user()->id;
        $paginator = MatchModel::query()
            ->with(['user:id,username', 'targetUser:id,username'])
            ->where(function ($q) use ($authUserId) {
                $q->where('user_id', $authUserId)
                    ->orWhere('target_user_id', $authUserId);
            })
            ->latest('created_at')
            ->paginate(30);
        return $this->respondCollection(
            new PaginatedResourceCollection($paginator, MatchResource::class),
            'Matches fetched successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertMatchRequest $request)
    {
        $authUserId = (int) $request->user()->id;
        $targetUserId = (int) $request->validated('target_user_id');
        $gamePlatformId = (int) $request->validated('game_platform_id');
        $newStatus = (string) $request->validated('status');

        if (! GamePlatform::query()->where('id', $gamePlatformId)->exists()) {
            throw ValidationException::withMessages([
                'game_platform_id' => ['Invalid game platform.'],
            ]);
        }

        [$match, $becameMatched] = DB::transaction(function () use ($authUserId, $targetUserId, $gamePlatformId, $newStatus) {
            // One row per unordered pair + game_platform_id.
            $pairRows = MatchModel::query()
                ->where('game_platform_id', $gamePlatformId)
                ->where(function ($q) use ($authUserId, $targetUserId) {
                    $q->where(function ($d1) use ($authUserId, $targetUserId) {
                        $d1->where('user_id', $authUserId)
                            ->where('target_user_id', $targetUserId);
                    })->orWhere(function ($d2) use ($authUserId, $targetUserId) {
                        $d2->where('user_id', $targetUserId)
                            ->where('target_user_id', $authUserId);
                    });
                })
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $existing = $pairRows->first();

            if (! $existing) {
                $created = MatchModel::create([
                    'user_id' => $authUserId,
                    'target_user_id' => $targetUserId,
                    'game_platform_id' => $gamePlatformId,
                    'status' => $newStatus,
                ]);

                return [$created, (string) $created->status === 'matched'];
            }

            // Cleanup duplicates if they exist historically; keep oldest row only.
            if ($pairRows->count() > 1) {
                MatchModel::query()
                    ->whereIn('id', $pairRows->slice(1)->pluck('id')->all())
                    ->delete();
            }

            $currentStatus = (string) $existing->status;
            $isReverseActor = (int) $existing->user_id === $targetUserId
                && (int) $existing->target_user_id === $authUserId;

            // Business rules:
            // - Rejected is final.
            // - If first is liked, other user decides:
            //   - liked => matched
            //   - rejected => rejected
            // - Same-side repeated liked keeps liked.
            $resolvedStatus = $currentStatus;
            if ($currentStatus === 'matched' || $currentStatus === 'rejected') {
                $resolvedStatus = $currentStatus;
            } elseif (in_array($currentStatus, ['liked', 'pending'], true)) {
                if ($newStatus === 'rejected') {
                    $resolvedStatus = 'rejected';
                } elseif (in_array($newStatus, ['liked', 'matched'], true)) {
                    $resolvedStatus = $isReverseActor ? 'matched' : 'liked';
                }
            } else {
                $resolvedStatus = $newStatus;
            }

            $existing->update([
                'status' => $resolvedStatus,
                'game_platform_id' => $gamePlatformId,
            ]);

            $fresh = $existing->fresh();
            $becameMatched = $resolvedStatus === 'matched' && $currentStatus !== 'matched';

            return [$fresh, $becameMatched];
        });

        if ($becameMatched) {
            $this->ensureMutualFollowEdges($match);
            $this->matchFirestoreConversation->bootstrapFromMatch($match, $request);
        }

        return $this->respondResource(
            new MatchResource($match->load([
                'user:id,username,avatar,first_cover,profile_images',
                'targetUser:id,username,avatar,first_cover,profile_images',
                'gamePlatform.game:id,name',
            ])),
            'Match updated successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(MatchModel $match)
    {
        $authUserId = (int) request()->user()->id;
        if ((int) $match->user_id !== $authUserId && (int) $match->target_user_id !== $authUserId) {
            $this->ensureOwner((int) $match->user_id, $authUserId);
        }
        return $this->respondResource(
            new MatchResource($match->load(['user:id,username', 'targetUser:id,username'])),
            'Match fetched successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertMatchRequest $request, MatchModel $match)
    {
        $this->ensureOwner((int) $match->user_id, (int) $request->user()->id);

        $previousStatus = (string) $match->status;
        $payload = [
            'status' => $request->validated('status'),
        ];
        if ($request->has('game_platform_id')) {
            $payload['game_platform_id'] = (int) $request->validated('game_platform_id');
        }

        $match->update($payload);
        $match = $match->fresh();
        if ($match->status === 'matched' && $previousStatus !== 'matched') {
            $this->ensureMutualFollowEdges($match);
            $this->matchFirestoreConversation->bootstrapFromMatch($match, $request);
        }

        return $this->respondResource(
            new MatchResource($match),
            'Match updated successfully.'
        );
    }

    /**
     * Insert A→B and B→A follow rows once; unique (follower_id, following_id) + insertOrIgnore avoids duplicates and races.
     */
    private function ensureMutualFollowEdges(MatchModel $match): void
    {
        $a = (int) $match->user_id;
        $b = (int) $match->target_user_id;
        if ($a === $b) {
            return;
        }

        $now = now();
        DB::table('followers')->insertOrIgnore([
            ['follower_id' => $a, 'following_id' => $b, 'created_at' => $now],
            ['follower_id' => $b, 'following_id' => $a, 'created_at' => $now],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MatchModel $match)
    {
        $this->ensureOwner((int) $match->user_id, (int) request()->user()->id);
        $match->delete();
        return $this->respondDeleted('Match deleted successfully.');
    }

    /**
     * Fast candidates query for swiping/matching.
     */
    public function candidates(MatchCandidatesRequest $request)
    {
        $validated = $request->validated();
        /** @var User $authUser */
        $authUser = $request->user();
        $authUserId = (int) $authUser->id;

        $limit = $validated['limit'] ?? 20;
        $rankRequired = (bool) ($validated['rank_required'] ?? false);
        $rankTierId = isset($validated['game_rank_tier_id']) ? (int) $validated['game_rank_tier_id'] : null;
        $gamePlatformId = (int) $validated['game_platform_id'];
        $requestedGameId = (int) $validated['game_id'];
        $requestedPlatformId = isset($validated['platform_id']) ? (int) $validated['platform_id'] : null;

        $selectedGamePlatform = GamePlatform::query()->find($gamePlatformId);
        if (! $selectedGamePlatform) {
            throw ValidationException::withMessages([
                'game_platform_id' => ['Selected game/platform pair is invalid.'],
            ]);
        }
        if ((int) $selectedGamePlatform->game_id !== $requestedGameId) {
            throw ValidationException::withMessages([
                'game_platform_id' => ['Selected game/platform pair does not match the selected game.'],
            ]);
        }
        if ($requestedPlatformId !== null && (int) $selectedGamePlatform->platform_id !== $requestedPlatformId) {
            throw ValidationException::withMessages([
                'game_platform_id' => ['Selected game/platform pair does not match the selected platform.'],
            ]);
        }
        $platformId = (int) $selectedGamePlatform->platform_id;
        $gameId = (int) $selectedGamePlatform->game_id;

        $authGameIds = $authUser->userGames()->pluck('game_id')->map(fn ($v) => (int) $v)->unique()->values();
        $authPlatformIds = $authUser->userPlatforms()->pluck('platform_id')->map(fn ($v) => (int) $v)->unique()->values();

        $users = User::query()
            ->select('users.*')
            ->join('user_games as ug', 'ug.user_id', '=', 'users.id')
            ->join('user_platforms as up', 'up.user_id', '=', 'users.id')
            ->when(
                $rankRequired,
                fn ($q) => $q->join('user_game_ranks as ugr', 'ugr.user_id', '=', 'users.id')
                    ->where('ugr.game_rank_tier_id', $rankTierId)
            )
            ->where('ug.game_id', $gameId)
            ->where('up.platform_id', $platformId)
            ->where('up.game_platform_id', $gamePlatformId)
            ->where('users.id', '!=', $authUserId)
            ->where(function ($q) use ($authUserId, $gamePlatformId) {
                // Hide terminal pairs (rejected/matched any direction) and rows where YOU
                // already swiped first (liked/pending as initiator). Still show users who
                // liked you first so you can respond (incoming pending handled below).
                $q->whereNotExists(function ($sub) use ($authUserId, $gamePlatformId) {
                    $sub->select(DB::raw(1))
                        ->from('matches as m')
                        ->where('m.game_platform_id', $gamePlatformId)
                        ->where(function ($pair) use ($authUserId) {
                            $pair->where(function ($d1) use ($authUserId) {
                                $d1->where('m.user_id', $authUserId)
                                    ->whereColumn('m.target_user_id', 'users.id');
                            })->orWhere(function ($d2) use ($authUserId) {
                                $d2->whereColumn('m.user_id', 'users.id')
                                    ->where('m.target_user_id', $authUserId);
                            });
                        })
                        ->where(function ($statusRule) use ($authUserId) {
                            $statusRule->whereIn('m.status', ['rejected', 'matched'])
                                ->orWhere(function ($acted) use ($authUserId) {
                                    $acted->where('m.user_id', $authUserId)
                                        ->whereIn('m.status', ['liked', 'pending']);
                                });
                        });
                })->orWhereExists(function ($sub) use ($authUserId, $gamePlatformId) {
                    $sub->select(DB::raw(1))
                        ->from('matches as m2')
                        ->where('m2.game_platform_id', $gamePlatformId)
                        ->whereColumn('m2.user_id', 'users.id')
                        ->where('m2.target_user_id', $authUserId)
                        ->where('m2.status', 'pending');
                });
            })
            ->distinct('users.id')
            ->orderByDesc('users.is_online')
            ->limit($limit)
            ->get();

        $platformNameById = Platform::query()->pluck('name', 'id');
        $commonGameIds = [];
        $commonPlatformIds = [];
        foreach ($users as $candidate) {
            $cId = (int) $candidate->id;
            $commonGameIds[$cId] = DB::table('user_games')
                ->where('user_id', $cId)
                ->whereIn('game_id', $authGameIds)
                ->pluck('game_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
            $commonPlatformIds[$cId] = DB::table('user_platforms')
                ->where('user_id', $cId)
                ->whereIn('platform_id', $authPlatformIds)
                ->pluck('platform_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }
        $allCommonGameIds = collect($commonGameIds)->flatten()->unique()->values();
        $gameById = Game::query()
            ->whereIn('id', $allCommonGameIds)
            ->get(['id', 'name', 'image'])
            ->keyBy('id');

        $data = $users->map(function (User $candidate) use (
            $gamePlatformId,
            $commonGameIds,
            $commonPlatformIds,
            $platformNameById,
            $gameById
        ) {
            $candidateId = (int) $candidate->id;
            $tier = DB::table('user_game_ranks as ugr')
                ->join('game_rank_tiers as grt', 'grt.id', '=', 'ugr.game_rank_tier_id')
                ->where('ugr.user_id', $candidateId)
                ->where('grt.game_platform_id', $gamePlatformId)
                ->select('grt.id', 'grt.label')
                ->first();

            $games = collect($commonGameIds[$candidateId] ?? [])
                ->map(function (int $id) use ($gameById) {
                    $g = $gameById->get($id);
                    if (! $g) return null;
                    return [
                        'id' => (int) $g->id,
                        'name' => (string) $g->name,
                        'image' => $g->image ? (string) $g->image : null,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $platforms = collect($commonPlatformIds[$candidateId] ?? [])
                ->map(fn (int $pid) => $platformNameById[$pid] ?? null)
                ->filter()
                ->values()
                ->all();

            $age = null;
            if ($candidate->birth_date) {
                try {
                    $age = Carbon::parse($candidate->birth_date)->age;
                } catch (\Throwable) {
                    $age = null;
                }
            }

            return [
                // Images for candidate carousel (main + gallery slots).
                'images' => collect(UserProfilePhotoService::slotsFromUser($candidate))
                    ->pluck('url')
                    ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                    ->map(fn ($v) => trim($v))
                    ->concat(collect([$candidate->first_cover, $candidate->avatar])->filter())
                    ->unique()
                    ->values()
                    ->all(),
                'id' => $candidateId,
                'username' => (string) $candidate->username,
                'age' => $age,
                'region' => $candidate->region ? (string) $candidate->region : '',
                'gender' => $candidate->gender ? (string) $candidate->gender : '',
                'is_online' => $candidate->isEffectivelyOnline(60),
                'image' => $candidate->first_cover ?: $candidate->avatar,
                'rank_tier_id' => $tier ? (int) $tier->id : null,
                'rank_label' => $tier ? (string) $tier->label : null,
                'common_games' => $games,
                'common_platforms' => $platforms,
            ];
        })->values()->all();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Match candidates fetched successfully.');
    }
}
