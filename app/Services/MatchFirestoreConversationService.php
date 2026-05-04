<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatchModel;
use App\Models\User;
use App\Support\ApiPublicUrl;
use Carbon\Carbon;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * When two players match, upsert a Firestore `chats/{doc}` row so both see the thread on the Messages tab
 * (same shape as the Flutter client: participantUids, profiles, lastText, lastAt, lastSenderUid).
 *
 * Uses the Firestore REST API + service account JWT (no PHP grpc extension).
 */
final class MatchFirestoreConversationService
{
    private const FIRESTORE_SCOPE = 'https://www.googleapis.com/auth/datastore';

    public function __construct(
        private readonly FcmV1Sender $fcm,
    ) {}

    public function bootstrapFromMatch(MatchModel $match, ?Request $request = null): void
    {
        try {
            $this->bootstrapFromMatchUnchecked($match, $request);
        } catch (Throwable $e) {
            Log::error('MatchFirestoreConversation: failed.', [
                'match_id' => $match->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send "it's a match" FCM to the peer (not the API caller). Safe to call even when Firestore
     * chat bootstrap was skipped (e.g. missing service account).
     */
    public function notifyPeerDeviceOfMutualMatch(User $actor, MatchModel $match, ?Request $request = null): void
    {
        try {
            $match->loadMissing(['gamePlatform.game:id,name']);
            $gameName = $match->gamePlatform?->game?->name;
            $gameName = is_string($gameName) ? trim($gameName) : '';
            $userA = User::query()->find((int) $match->user_id);
            $userB = User::query()->find((int) $match->target_user_id);
            if (! $userA || ! $userB) {
                return;
            }
            $this->sendMatchFoundFcmToPeerOtherThanActor($request, $actor, $userA, $userB, $gameName);
        } catch (Throwable $e) {
            Log::warning('MatchFCM: notifyPeerDeviceOfMutualMatch failed.', [
                'match_id' => $match->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function bootstrapFromMatchUnchecked(MatchModel $match, ?Request $request = null): void
    {
        $request ??= request();
        $projectId = (string) config('services.firebase.project_id', '');
        $credsPath = config('services.firebase.credentials');
        if ($projectId === '' || ! is_string($credsPath) || $credsPath === '' || ! is_file($credsPath)) {
            Log::warning('MatchFirestoreConversation: skipped — set FIREBASE_CREDENTIALS in .env to a Service Account JSON path (not google-services.json). Example: storage/app/private/firebase-adminsdk.json');

            return;
        }

        if (! $this->isServiceAccountJson($credsPath)) {
            Log::error('MatchFirestoreConversation: credentials file must be a Service Account JSON (must contain "type":"service_account" and "private_key"). google-services.json will not work.');

            return;
        }

        $userA = User::query()->find((int) $match->user_id);
        $userB = User::query()->find((int) $match->target_user_id);
        if (! $userA || ! $userB) {
            return;
        }

        $uidA = (string) ($userA->firebase_uid ?? '');
        $uidB = (string) ($userB->firebase_uid ?? '');
        if ($uidA === '' || $uidB === '' || $uidA === $uidB) {
            Log::warning('MatchFirestoreConversation: skipped (missing or duplicate firebase_uid).', [
                'user_id' => $userA->id,
                'target_user_id' => $userB->id,
            ]);

            return;
        }

        $chatDocId = $this->chatDocIdFromFirebaseUids($uidA, $uidB);
        $participantUids = strcmp($uidA, $uidB) <= 0 ? [$uidA, $uidB] : [$uidB, $uidA];

        $match->loadMissing(['gamePlatform.game:id,name']);
        $gameName = $match->gamePlatform?->game?->name;
        $gameName = is_string($gameName) ? trim($gameName) : '';
        $lastText = $gameName !== ''
            ? "It's a match on {$gameName}! Say hi."
            : "It's a match! Say hi.";

        $profilesPayload = [
            $uidA => $this->profileRow($userA, $request),
            $uidB => $this->profileRow($userB, $request),
        ];

        $fields = [
            'participantUids' => $this->encodeStringArray($participantUids),
            'profiles' => $this->encodeProfilesMap($profilesPayload),
            'lastText' => ['stringValue' => $lastText],
            'lastAt' => ['timestampValue' => Carbon::now()->utc()->toIso8601String()],
            'lastSenderUid' => ['stringValue' => (string) $participantUids[0]],
        ];

        $token = $this->accessToken($credsPath);
        if ($token === null) {
            Log::error('MatchFirestoreConversation: could not obtain access token.');

            return;
        }

        $mask = implode('&', array_map(
            static fn (string $p) => 'updateMask.fieldPaths='.rawurlencode($p),
            ['participantUids', 'profiles', 'lastText', 'lastAt', 'lastSenderUid'],
        ));

        $docUrl = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/chats/%s',
            rawurlencode($projectId),
            rawurlencode($chatDocId),
        );

        $createUrl = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/chats?documentId=%s',
            rawurlencode($projectId),
            rawurlencode($chatDocId),
        );

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($createUrl, ['fields' => $fields]);

        if ($response->successful()) {
            $this->createMatchNotifications(
                projectId: $projectId,
                token: $token,
                userA: $userA,
                userB: $userB,
                gameName: $gameName,
                matchId: (int) $match->id,
                request: $request,
            );
            Log::info('MatchFirestoreConversation: chat document created.', [
                'chat_doc_id' => $chatDocId,
            ]);

            return;
        }

        if ($response->status() === 409) {
            $patchUrl = $docUrl.'?'.$mask;
            $patch = Http::withToken($token)
                ->acceptJson()
                ->patch($patchUrl, ['fields' => $fields]);

            if (! $patch->successful()) {
                Log::error('MatchFirestoreConversation: Firestore PATCH after 409 failed.', [
                    'status' => $patch->status(),
                    'body' => $patch->body(),
                    'path' => $docUrl,
                ]);

                return;
            }

            Log::info('MatchFirestoreConversation: chat document updated (already existed).', [
                'chat_doc_id' => $chatDocId,
            ]);
            $this->createMatchNotifications(
                projectId: $projectId,
                token: $token,
                userA: $userA,
                userB: $userB,
                gameName: $gameName,
                matchId: (int) $match->id,
                request: $request,
            );

            return;
        }

        Log::error('MatchFirestoreConversation: Firestore create failed.', [
            'status' => $response->status(),
            'body' => $response->body(),
            'create_url' => $createUrl,
        ]);
    }

    private function isServiceAccountJson(string $credentialsPath): bool
    {
        try {
            $data = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return isset($data['type'], $data['private_key'])
            && $data['type'] === 'service_account'
            && is_string($data['private_key'])
            && str_contains($data['private_key'], 'BEGIN PRIVATE KEY');
    }

    /**
     * Same ordering as Flutter `chatDocIdFromFirebaseUids`.
     */
    public function chatDocIdFromFirebaseUids(string $uidA, string $uidB): string
    {
        $a = trim($uidA);
        $b = trim($uidB);
        if ($a === '' || $b === '') {
            throw new \InvalidArgumentException('Firebase UIDs must be non-empty.');
        }

        return strcmp($a, $b) <= 0 ? "{$a}_{$b}" : "{$b}_{$a}";
    }

    /**
     * @return array{username: string, avatarUrl: string, appUserId: int}
     */
    private function profileRow(User $user, ?Request $request): array
    {
        $slots = UserProfilePhotoService::slotsFromUser($user);
        $main = UserProfilePhotoService::mainUrl($slots) ?? $user->first_cover ?? $user->avatar;
        $url = is_string($main) ? ApiPublicUrl::rewrite($main, $request) : null;

        return [
            'username' => (string) ($user->username ?? 'Player'),
            'avatarUrl' => is_string($url) ? $url : '',
            'appUserId' => (int) $user->id,
        ];
    }

    /**
     * @param  list<string>  $strings
     */
    private function encodeStringArray(array $strings): array
    {
        return [
            'arrayValue' => [
                'values' => array_map(
                    static fn (string $s) => ['stringValue' => $s],
                    $strings,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, array{username: string, avatarUrl: string, appUserId: int}>  $uidToProfile
     */
    private function encodeProfilesMap(array $uidToProfile): array
    {
        $fields = [];
        foreach ($uidToProfile as $uid => $row) {
            $fields[$uid] = [
                'mapValue' => [
                    'fields' => [
                        'username' => ['stringValue' => $row['username']],
                        'avatarUrl' => ['stringValue' => $row['avatarUrl']],
                        'appUserId' => ['integerValue' => (string) $row['appUserId']],
                    ],
                ],
            ];
        }

        return ['mapValue' => ['fields' => $fields]];
    }

    private function accessToken(string $credentialsPath): ?string
    {
        try {
            $jsonKey = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        try {
            $creds = new ServiceAccountCredentials(self::FIRESTORE_SCOPE, $jsonKey);
            $token = $creds->fetchAuthToken();
        } catch (Throwable $e) {
            Log::error('MatchFirestoreConversation: fetchAuthToken failed.', ['message' => $e->getMessage()]);

            return null;
        }

        if (isset($token['error'])) {
            Log::error('MatchFirestoreConversation: token response error.', ['token' => $token]);

            return null;
        }

        return isset($token['access_token']) && is_string($token['access_token'])
            ? $token['access_token']
            : null;
    }

    /**
     * FCM to the **other** player when the authenticated user’s swipe completes a mutual match
     * (same payload shape as [ChatPushController::notifyMatchPeer], using [users.fcm_token]).
     */
    private function sendMatchFoundFcmToPeerOtherThanActor(
        ?Request $request,
        ?User $actor,
        User $userA,
        User $userB,
        string $gameName,
    ): void {
        if (! $actor instanceof User) {
            return;
        }

        $authId = (int) $actor->id;
        $recipient = (int) $userA->id === $authId ? $userB : $userA;
        if ((int) $recipient->id === $authId) {
            return;
        }

        $recipientFresh = User::query()->find((int) $recipient->id);
        if (! $recipientFresh instanceof User) {
            return;
        }
        $recipient = $recipientFresh;

        $deviceToken = $recipient->fcm_token;
        if (! is_string($deviceToken) || strlen($deviceToken) < 32) {
            Log::info('MatchFCM: peer has no FCM token on users row.', [
                'recipient_user_id' => $recipient->id,
                'match_actor_id' => $authId,
            ]);

            return;
        }

        $peerFirebaseUid = trim((string) ($actor->firebase_uid ?? ''));
        if ($peerFirebaseUid === '') {
            Log::warning('MatchFCM: actor has no firebase_uid; cannot build tap payload.', [
                'actor_user_id' => $authId,
            ]);

            return;
        }

        $game = trim($gameName);
        $title = 'Found a new teammate';
        $description = $game !== ''
            ? "Gameo found you a new {$game} teammate."
            : 'Gameo found you a new teammate.';

        $peerUsername = (string) ($actor->username ?? 'Player');
        $peerImageUrl = $this->peerImageUrlForFirestore($actor, $request);

        $data = [
            'type' => 'match_found',
            'title' => $title,
            'description' => $description,
            'body' => $description,
            'peerUsername' => $peerUsername,
            'peerImageUrl' => $peerImageUrl,
            'gameName' => $game,
            'peerFirebaseUid' => $peerFirebaseUid,
            'peerAppUserId' => (string) $authId,
        ];

        $result = $this->fcm->sendToDevice($deviceToken, $title, $description, $data, 'gameo_matches');

        if (! $result['ok'] && $this->fcm->responseIndicatesInvalidToken($result['body'])) {
            $recipient->forceFill([
                'fcm_token' => null,
                'fcm_token_updated_at' => null,
            ])->save();
            Log::info('MatchFCM: cleared stale token.', ['user_id' => $recipient->id]);

            return;
        }

        if (! $result['ok']) {
            Log::warning('MatchFCM: send failed.', [
                'recipient_user_id' => $recipient->id,
                'status' => $result['status'],
                'body' => $result['body'],
            ]);
        }
    }

    /**
     * In-app Firestore `notifications` rows for both players (Games tab).
     */
    private function createMatchNotifications(
        string $projectId,
        string $token,
        User $userA,
        User $userB,
        string $gameName,
        int $matchId,
        ?Request $request = null,
    ): void {
        $trimmedGame = trim($gameName);
        $title = 'Found a new teammate';
        $description = $trimmedGame !== ''
            ? "Gameo found you a new {$trimmedGame} teammate."
            : 'Gameo found you a new teammate.';

        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/notifications',
            rawurlencode($projectId),
        );

        $pairs = [
            [$userA, $userB],
            [$userB, $userA],
        ];

        foreach ($pairs as [$recipient, $peer]) {
            $uid = trim((string) ($recipient->firebase_uid ?? ''));
            if ($uid === '') {
                continue;
            }

            $peerUsername = (string) ($peer->username ?? 'Player');
            $peerImageUrl = $this->peerImageUrlForFirestore($peer, $request);

            $fields = [
                'recipientUid' => ['stringValue' => $uid],
                'type' => ['stringValue' => 'match_found'],
                'title' => ['stringValue' => $title],
                'description' => ['stringValue' => $description],
                'gameName' => ['stringValue' => $trimmedGame],
                'peerUsername' => ['stringValue' => $peerUsername],
                'peerImageUrl' => ['stringValue' => $peerImageUrl],
                'matchId' => ['integerValue' => (string) $matchId],
                'isRead' => ['booleanValue' => false],
                'createdAt' => ['timestampValue' => Carbon::now()->utc()->toIso8601String()],
            ];

            try {
                $res = Http::withToken($token)
                    ->acceptJson()
                    ->post($url, ['fields' => $fields]);
                if (! $res->successful()) {
                    Log::warning('MatchFirestoreConversation: notification create failed.', [
                        'status' => $res->status(),
                        'body' => $res->body(),
                        'recipient_uid' => $uid,
                        'match_id' => $matchId,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('MatchFirestoreConversation: notification request exception.', [
                    'message' => $e->getMessage(),
                    'recipient_uid' => $uid,
                    'match_id' => $matchId,
                ]);
            }
        }
    }

    private function peerImageUrlForFirestore(User $user, ?Request $request): string
    {
        $slots = UserProfilePhotoService::slotsFromUser($user);
        $main = UserProfilePhotoService::mainUrl($slots) ?? $user->first_cover ?? $user->avatar;
        $url = is_string($main) ? ApiPublicUrl::rewrite($main, $request) : null;

        return is_string($url) ? $url : '';
    }
}
