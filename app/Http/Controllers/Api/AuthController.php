<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthFirebaseLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\GamePlatform;
use App\Models\GameRankTier;
use App\Http\Resources\UserSummaryResource;
use App\Models\Platform;
use App\Models\User;
use App\Models\UserGameRank;
use App\Models\UserPlatform;
use App\Services\UserProfilePhotoService;
use App\Support\ApiPublicUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    private const DEFAULT_PASSWORD_LENGTH = 40;

    public function register(AuthRegisterRequest $request)
    {
        $user = User::create([
            ...$request->validated(),
            'password' => Hash::make($request->validated('password')),
            'is_online' => true,
            'last_seen' => now(),
        ]);

        $token = $user->createToken($request->userAgent() ?? 'mobile')->plainTextToken;

        return $this->respondSuccess([
            'data' => (new UserSummaryResource($this->userWithFollowCounts($user)))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registered successfully.', 201);
    }

    public function login(AuthLoginRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user->forceFill([
            'is_online' => true,
            'last_seen' => now(),
        ])->save();

        $token = $user->createToken($request->validated('device_name') ?? 'mobile')->plainTextToken;

        return $this->respondSuccess([
            'data' => (new UserSummaryResource($this->userWithFollowCounts($user)))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Logged in successfully.');
    }

    public function firebaseLogin(AuthFirebaseLoginRequest $request)
    {
        $validated = $request->validated();
        $firebaseUid = $validated['firebase_uid'];
        $email = $validated['email'];
        $displayName = trim((string) ($validated['display_name'] ?? ''));

        $user = User::query()
            ->where('firebase_uid', $firebaseUid)
            ->orWhere('email', $email)
            ->first();

        $isNewUser = false;
        if (! $user) {
            $isNewUser = true;
            $user = User::create([
                'username' => $this->uniqueUsernameFor($displayName, $email),
                'email' => $email,
                'firebase_uid' => $firebaseUid,
                'firebase_id_token' => $validated['id_token'] ?? null,
                'password' => Hash::make(Str::random(self::DEFAULT_PASSWORD_LENGTH)),
                'gender' => 'other',
                'birth_date' => now()->subYears(18)->toDateString(),
                'region' => 'Unknown',
                'bio' => null,
                'avatar' => null,
                'is_onboarding' => true,
                'is_online' => true,
                'last_seen' => now(),
            ]);
        } else {
            // Always persist the UID from this Google/Firebase sign-in. Keeping a stale
            // `firebase_uid` (e.g. user matched by email first) makes Firestore rules fail:
            // backend writes `recipientUid` / `participantUids` with the wrong id while
            // the app sends `request.auth.uid` from the current Firebase session.
            $user->forceFill([
                'firebase_uid' => $firebaseUid,
                'firebase_id_token' => $validated['id_token'] ?? $user->firebase_id_token,
                'is_online' => true,
                'last_seen' => now(),
            ])->save();
        }

        $token = $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

        return $this->respondSuccess([
            'data' => (new UserSummaryResource($this->userWithFollowCounts($user->fresh())))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
            'is_new_user' => $isNewUser,
        ], $isNewUser ? 'Firebase user registered successfully.' : 'Firebase user logged in successfully.');
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $this->userWithFollowCounts($request->user());

        return $this->respondResource(
            new UserSummaryResource($user),
            'Current user fetched successfully.'
        );
    }

    /**
     * Update profile fields shown on the edit screen (username, bio).
     */
    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:5000'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $bioRaw = $validated['bio'] ?? null;
        $bio = is_string($bioRaw) ? trim($bioRaw) : '';
        $bio = $bio === '' ? null : $bio;

        $fill = [
            'username' => $validated['username'],
            'bio' => $bio,
        ];

        if (array_key_exists('region', $validated)) {
            $regionRaw = $validated['region'];
            if ($regionRaw === null) {
                $fill['region'] = null;
            } else {
                $r = trim((string) $regionRaw);
                $fill['region'] = $r === '' ? null : $r;
            }
        }

        $user->forceFill($fill)->save();

        return $this->respondSuccess([
            'data' => (new UserSummaryResource($this->userWithFollowCounts($user->fresh())))->resolve(),
        ], 'Profile updated.');
    }

    /**
     * Games the user linked during onboarding for one platform (`user_platforms` → `game_platform`).
     */
    public function meetLibraryGames(Request $request)
    {
        $validated = $request->validate([
            'platform_key' => ['required', 'string', 'in:mobile,pc,xbox,playstation'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $key = $validated['platform_key'];

        $nameByKey = [
            'mobile' => 'Mobile',
            'pc' => 'PC',
            'xbox' => 'Xbox',
            'playstation' => 'PlayStation',
        ];
        $platformName = $nameByKey[$key];
        $platformId = Platform::query()->where('name', $platformName)->value('id');
        if (! $platformId) {
            return $this->respondSuccess(['data' => []], 'Unknown platform.');
        }

        $gpIds = UserPlatform::query()
            ->where('user_id', $user->id)
            ->where('platform_id', $platformId)
            ->whereNotNull('game_platform_id')
            ->pluck('game_platform_id');

        if ($gpIds->isEmpty()) {
            return $this->respondSuccess(['data' => []], 'No games for this platform yet.');
        }

        $gameIds = GamePlatform::query()
            ->whereIn('id', $gpIds)
            ->pluck('game_id')
            ->unique()
            ->values();

        $games = Game::query()
            ->whereIn('id', $gameIds)
            ->with('platforms')
            ->orderByDesc('is_populer')
            ->orderBy('name')
            ->get();

        $rows = GameResource::collection($games)->resolve();
        $data = [];
        foreach ($rows as $row) {
            $platforms = $row['platforms'] ?? [];
            $gamePlatformId = null;
            foreach ($platforms as $platformRow) {
                if (($platformRow['id'] ?? null) === (int) $platformId) {
                    $gamePlatformId = (int) ($platformRow['game_platform_id'] ?? 0);
                    break;
                }
            }
            $data[] = [
                ...$row,
                'game_platform_id' => $gamePlatformId,
            ];
        }

        return $this->respondSuccess([
            'data' => $data,
        ], 'Games fetched successfully.');
    }

    /**
     * Rank tiers for one game on one platform (Meet flow), plus current user selection.
     */
    public function meetGameRanks(Request $request)
    {
        $validated = $request->validate([
            'platform_key' => ['required', 'string', 'in:mobile,pc,xbox,playstation'],
            'game_id' => ['required', 'integer', 'exists:games,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $gameId = (int) $validated['game_id'];
        $platformId = $this->platformIdByKey($validated['platform_key']);
        if (! $platformId) {
            return $this->respondSuccess(['data' => [
                'tiers' => [],
                'selected_tier_id' => null,
            ]], 'Unknown platform.');
        }

        $gamePlatformId = GamePlatform::query()
            ->where('game_id', $gameId)
            ->where('platform_id', $platformId)
            ->value('id');

        if (! $gamePlatformId) {
            return $this->respondSuccess(['data' => [
                'tiers' => [],
                'selected_tier_id' => null,
            ]], 'No rank tiers for this game on this platform.');
        }

        $tiers = GameRankTier::query()
            ->where('game_platform_id', $gamePlatformId)
            ->orderBy('order_index')
            ->get(['id', 'game_id', 'game_platform_id', 'code', 'label', 'order_index']);

        $selectedTierId = UserGameRank::query()
            ->where('user_id', $user->id)
            ->whereIn('game_rank_tier_id', $tiers->pluck('id'))
            ->value('game_rank_tier_id');

        return $this->respondSuccess(['data' => [
            'tiers' => $tiers->map(fn ($tier) => [
                'id' => (int) $tier->id,
                'game_id' => (int) $tier->game_id,
                'game_platform_id' => (int) $tier->game_platform_id,
                'code' => (string) $tier->code,
                'label' => (string) $tier->label,
                'order_index' => (int) $tier->order_index,
            ])->values()->all(),
            'selected_tier_id' => $selectedTierId ? (int) $selectedTierId : null,
        ]], 'Game ranks fetched successfully.');
    }

    /**
     * Save/replace current user's rank for one game on one platform (Meet flow).
     */
    public function saveMeetGameRank(Request $request)
    {
        $validated = $request->validate([
            'platform_key' => ['required', 'string', 'in:mobile,pc,xbox,playstation'],
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'game_rank_tier_id' => ['required', 'integer', 'exists:game_rank_tiers,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $gameId = (int) $validated['game_id'];
        $tierId = (int) $validated['game_rank_tier_id'];
        $platformId = $this->platformIdByKey($validated['platform_key']);
        if (! $platformId) {
            throw ValidationException::withMessages([
                'platform_key' => ['Unknown platform.'],
            ]);
        }

        $gamePlatformId = GamePlatform::query()
            ->where('game_id', $gameId)
            ->where('platform_id', $platformId)
            ->value('id');
        if (! $gamePlatformId) {
            throw ValidationException::withMessages([
                'game_id' => ['This game is not linked to the selected platform.'],
            ]);
        }

        $tier = GameRankTier::query()
            ->where('id', $tierId)
            ->where('game_platform_id', $gamePlatformId)
            ->first();
        if (! $tier) {
            throw ValidationException::withMessages([
                'game_rank_tier_id' => ['Selected rank does not belong to this game/platform.'],
            ]);
        }

        DB::transaction(function () use ($user, $gamePlatformId, $tierId) {
            $tierIdsForGamePlatform = GameRankTier::query()
                ->where('game_platform_id', $gamePlatformId)
                ->pluck('id');

            UserGameRank::query()
                ->where('user_id', $user->id)
                ->whereIn('game_rank_tier_id', $tierIdsForGamePlatform)
                ->delete();

            UserGameRank::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'game_rank_tier_id' => $tierId,
                ],
                [
                    'user_id' => $user->id,
                    'game_rank_tier_id' => $tierId,
                ]
            );
        });

        return $this->respondSuccess([
            'data' => [
                'game_rank_tier_id' => $tierId,
            ],
        ], 'Game rank saved successfully.');
    }

    public function logout(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()?->delete();
        $user->forceFill([
            'is_online' => false,
            'last_seen' => now(),
        ])->save();

        return $this->respondSuccess([], 'Logged out successfully.');
    }

    /**
     * Permanently delete the authenticated user and all related rows (DB cascades),
     * revoke every Sanctum token, and remove uploaded profile media from the public disk.
     */
    public function destroyAccount(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->id;

        DB::transaction(function () use ($user, $userId): void {
            $user->tokens()->delete();

            Storage::disk('public')->deleteDirectory('profile_media/'.$userId);

            $user->delete();
        });

        return $this->respondSuccess([], 'Account deleted successfully.');
    }

    /**
     * One-shot replace of user_games + user_platforms (same rules as onboarding gaming step).
     * Used by the app instead of many sequential user-games / user-platforms API calls.
     */
    public function syncGamingLibrary(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'platform_keys' => ['required', 'array', 'min:1'],
            'platform_keys.*' => ['string', 'in:mobile,pc,xbox,playstation'],
            'game_ids' => ['required', 'array', 'min:1'],
            'game_ids.*' => ['integer', 'exists:games,id'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $this->replaceUserGamingSelections(
                $user,
                $validated['platform_keys'],
                $validated['game_ids'],
            );
        });

        return $this->respondSuccess([], 'Gaming library updated successfully.');
    }

    /**
     * @param  list<string>  $platformKeys
     * @param  list<int>  $gameIds
     */
    private function replaceUserGamingSelections(User $user, array $platformKeys, array $gameIds): void
    {
        $platformNameMap = [
            'mobile' => 'Mobile',
            'pc' => 'PC',
            'xbox' => 'Xbox',
            'playstation' => 'PlayStation',
        ];
        $platformNames = collect($platformKeys)
            ->map(fn ($key) => $platformNameMap[$key] ?? null)
            ->filter()
            ->values();

        $platformIds = Platform::query()
            ->whereIn('name', $platformNames)
            ->pluck('id');

        if ($platformIds->isEmpty()) {
            throw ValidationException::withMessages([
                'platform_keys' => ['No matching platforms found.'],
            ]);
        }

        $gameIdsCollection = collect($gameIds)->map(fn ($id) => (int) $id)->unique()->values();

        $user->userGames()->delete();
        $user->userGames()->createMany(
            $gameIdsCollection->map(fn ($gameId) => [
                'game_id' => $gameId,
                'skill_level' => 1,
                'play_time_hours' => null,
                'created_at' => now(),
            ])->all()
        );

        $gamePlatformRows = GamePlatform::query()
            ->whereIn('game_id', $gameIdsCollection)
            ->whereIn('platform_id', $platformIds)
            ->get(['id', 'platform_id']);

        $user->userPlatforms()->delete();
        $user->userPlatforms()->createMany(
            $gamePlatformRows->map(fn ($row) => [
                'platform_id' => (int) $row->platform_id,
                'game_platform_id' => (int) $row->id,
                'username_on_platform' => null,
            ])->all()
        );
    }

    public function markOnboardingComplete(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'gender' => ['required', 'in:male,female'],
            'birthday' => ['required', 'date', 'before:today'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'region' => ['nullable', 'string', 'max:255'],
            'first_cover' => ['nullable', 'string', 'max:2048'],
            'profile_images' => ['nullable', 'array', 'max:20'],
            'profile_images.*' => ['string', 'max:2048'],
            'platform_keys' => ['required', 'array', 'min:1'],
            'platform_keys.*' => ['string', 'in:mobile,pc,xbox,playstation'],
            'game_ids' => ['required', 'array', 'min:1'],
            'game_ids.*' => ['integer', 'exists:games,id'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $slots = UserProfilePhotoService::slotsFromOnboardingRequest(
                $validated['first_cover'] ?? null,
                $validated['profile_images'] ?? null
            );
            $photoAttrs = UserProfilePhotoService::fillAttributesForSlots($user, $slots);

            $bioRaw = $validated['bio'] ?? null;
            $bio = is_string($bioRaw) ? trim($bioRaw) : '';
            $bio = $bio === '' ? null : $bio;
            $regionRaw = $validated['region'] ?? null;
            $region = is_string($regionRaw) ? trim($regionRaw) : '';
            $region = $region === '' ? $user->region : $region;

            $user->forceFill([
                'username' => $validated['username'],
                'gender' => $validated['gender'],
                'birth_date' => $validated['birthday'],
                'bio' => $bio,
                'region' => $region,
                'profile_images' => $photoAttrs['profile_images'],
                'first_cover' => $photoAttrs['first_cover'],
                'avatar' => $photoAttrs['avatar'],
                'is_onboarding' => false,
            ])->save();

            $this->replaceUserGamingSelections(
                $user,
                $validated['platform_keys'],
                $validated['game_ids'],
            );
        });

        return $this->respondSuccess([
            'data' => (new UserSummaryResource($this->userWithFollowCounts($user->fresh())))->resolve(),
        ], 'Onboarding completed successfully.');
    }

    /**
     * Store profile images on the public disk and return absolute URLs.
     */
    public function uploadProfileMedia(Request $request)
    {
        $request->validate([
            'first_cover' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'profile_images' => ['nullable', 'array', 'max:20'],
            'profile_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $base = 'profile_media/'.$user->id;

        $data = [
            'first_cover' => null,
            'profile_images' => [],
        ];

        if ($request->hasFile('first_cover')) {
            $path = $request->file('first_cover')->store($base, 'public');
            $data['first_cover'] = ApiPublicUrl::rewrite(
                Storage::disk('public')->url($path),
                $request
            );
        }

        $galleryFiles = $request->file('profile_images', []);
        if (! is_array($galleryFiles)) {
            $galleryFiles = $galleryFiles ? [$galleryFiles] : [];
        }
        foreach ($galleryFiles as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store($base, 'public');
                $data['profile_images'][] = ApiPublicUrl::rewrite(
                    Storage::disk('public')->url($path),
                    $request
                );
            }
        }

        return $this->respondSuccess([
            'data' => $data,
        ], 'Profile media uploaded.');
    }

    /**
     * Upload one profile photo to slot 0–2 and persist immediately.
     */
    public function storeProfilePhotoSlot(Request $request)
    {
        $validated = $request->validate([
            'slot' => ['required', 'integer', 'min:0', 'max:'.(UserProfilePhotoService::SLOT_COUNT - 1)],
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $slot = (int) $validated['slot'];

        $slots = UserProfilePhotoService::slotsFromUser($user);
        $oldUrl = $slots[$slot]['url'] ?? null;
        if (is_string($oldUrl) && $oldUrl !== '' && UserProfilePhotoService::urlBelongsToUser($oldUrl, $user->id)) {
            UserProfilePhotoService::deletePublicFileForUrl($oldUrl);
        }

        $url = UserProfilePhotoService::storeUploadedFile($user, $slot, $request->file('photo'));
        $slots[$slot]['url'] = $url;
        $slots = UserProfilePhotoService::ensureSingleMain($slots);
        UserProfilePhotoService::saveSlotsToUser($user, $slots);
        $user->refresh();

        $freshSlots = UserProfilePhotoService::slotsFromUser($user);

        return $this->respondSuccess([
            'data' => $this->profilePhotoPayloadForRequest($request, $freshSlots),
        ], 'Profile photo saved.');
    }

    /**
     * Remove a profile photo by URL (deletes file when stored under this user).
     */
    public function removeProfilePhoto(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $url = $validated['url'];

        if (! UserProfilePhotoService::urlBelongsToUser($url, $user->id)) {
            throw ValidationException::withMessages([
                'url' => ['This image cannot be removed from here.'],
            ]);
        }

        $slots = UserProfilePhotoService::slotsFromUser($user);
        $found = false;
        foreach ($slots as $i => $s) {
            if (UserProfilePhotoService::urlsPointToSamePublicFile($s['url'] ?? null, $url)) {
                $slots[$i]['url'] = null;
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw ValidationException::withMessages([
                'url' => ['Image not found on your profile.'],
            ]);
        }

        UserProfilePhotoService::deletePublicFileForUrl($url);
        $slots = UserProfilePhotoService::ensureSingleMain($slots);
        UserProfilePhotoService::saveSlotsToUser($user, $slots);
        $user->refresh();

        $freshSlots = UserProfilePhotoService::slotsFromUser($user);

        return $this->respondSuccess([
            'data' => [
                'profile_images' => $freshSlots,
                'first_cover' => UserProfilePhotoService::mainUrl($freshSlots),
            ],
        ], 'Profile photo removed.');
    }

    /**
     * Mark slot 0–2 as the main profile photo (must have a URL).
     */
    public function setProfilePhotoMain(Request $request)
    {
        $validated = $request->validate([
            'slot' => ['required', 'integer', 'min:0', 'max:'.(UserProfilePhotoService::SLOT_COUNT - 1)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $slot = (int) $validated['slot'];

        $slots = UserProfilePhotoService::slotsFromUser($user);
        if (empty($slots[$slot]['url'])) {
            throw ValidationException::withMessages([
                'slot' => ['That slot is empty.'],
            ]);
        }

        foreach ($slots as $i => $_) {
            $slots[$i]['main'] = $i === $slot;
        }

        UserProfilePhotoService::saveSlotsToUser($user, $slots);
        $user->refresh();

        $freshSlots = UserProfilePhotoService::slotsFromUser($user);

        return $this->respondSuccess([
            'data' => $this->profilePhotoPayloadForRequest($request, $freshSlots),
        ], 'Main photo updated.');
    }

    public function checkUsername(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/'],
        ]);
        $username = Str::lower($validated['username']);
        $exists = User::query()->whereRaw('LOWER(username) = ?', [$username])->exists();

        return $this->respondSuccess([
            'available' => ! $exists,
        ], ! $exists ? 'Username is available.' : 'Username is already taken.');
    }

    private function userWithFollowCounts(User $user): User
    {
        return $user->loadCount(['followers', 'following']);
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $freshSlots
     * @return array{profile_images: list<array{slot: int, url: ?string, main: bool}>, first_cover: ?string}
     */
    private function profilePhotoPayloadForRequest(Request $request, array $freshSlots): array
    {
        return [
            'profile_images' => ApiPublicUrl::rewriteProfileSlots($freshSlots, $request),
            'first_cover' => ApiPublicUrl::rewrite(
                UserProfilePhotoService::mainUrl($freshSlots),
                $request
            ),
        ];
    }

    private function uniqueUsernameFor(string $displayName, string $email): string
    {
        $baseRaw = $displayName !== '' ? $displayName : Str::before($email, '@');
        $base = Str::lower(Str::slug($baseRaw, '_'));
        if ($base === '') {
            $base = 'player';
        }
        $base = Str::limit($base, 20, '');
        $candidate = $base;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = Str::limit($base, 16, '').'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function platformIdByKey(string $key): ?int
    {
        $nameByKey = [
            'mobile' => 'Mobile',
            'pc' => 'PC',
            'xbox' => 'Xbox',
            'playstation' => 'PlayStation',
        ];
        $platformName = $nameByKey[$key] ?? null;
        if (! $platformName) {
            return null;
        }

        $id = Platform::query()->where('name', $platformName)->value('id');
        return $id ? (int) $id : null;
    }
}

