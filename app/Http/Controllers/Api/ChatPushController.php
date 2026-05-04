<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\FcmV1Sender;
use App\Support\MatchNotificationCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatPushController extends BaseApiController
{
    public function registerFcmToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fcm_token' => ['required', 'string', 'min:32', 'max:8192'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('fcm_token.register.validation_failed', [
                'user_id' => $request->user()?->id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        /** @var User $user */
        $user = $request->user();
        $tokenLen = strlen($validated['fcm_token']);
        $user->forceFill([
            'fcm_token' => $validated['fcm_token'],
            'fcm_token_updated_at' => now(),
        ])->save();

        Log::info('fcm_token.register.saved', [
            'user_id' => $user->id,
            'firebase_uid' => $user->firebase_uid,
            'token_length' => $tokenLen,
        ]);

        return $this->respondSuccess([], 'FCM token saved.');
    }

    /**
     * Called by the Flutter app after a Firestore chat message is written.
     * Sends FCM to the recipient using the token stored on their `users` row.
     */
    public function notifyPeer(Request $request, FcmV1Sender $fcm): JsonResponse
    {
        $validated = $request->validate([
            'recipient_firebase_uid' => ['required', 'string', 'max:128'],
            'sender_firebase_uid' => ['required', 'string', 'max:128'],
            'notification_title' => ['required', 'string', 'max:255'],
            'notification_body' => ['nullable', 'string', 'max:500'],
            'message_type' => ['nullable', 'string', 'max:64'],
            'peer_app_user_id' => ['nullable', 'integer', 'min:0'],
            'peer_username' => ['nullable', 'string', 'max:255'],
            'peer_avatar_url' => ['nullable', 'string', 'max:2048'],
        ]);

        /** @var User $sender */
        $sender = $request->user();

        if ($sender->firebase_uid === null || $sender->firebase_uid === '') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has no firebase_uid; cannot verify sender.',
            ], 422);
        }

        if ($sender->firebase_uid !== $validated['sender_firebase_uid']) {
            return response()->json([
                'success' => false,
                'message' => 'sender_firebase_uid does not match the authenticated user.',
            ], 403);
        }

        if ($sender->firebase_uid === $validated['recipient_firebase_uid']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot notify yourself.',
            ], 422);
        }

        $recipient = User::query()
            ->where('firebase_uid', $validated['recipient_firebase_uid'])
            ->first();

        if ($recipient === null) {
            return $this->respondSuccess([
                'sent' => false,
                'reason' => 'recipient_not_found',
            ], 'Recipient is not registered on this backend.');
        }

        $token = $recipient->fcm_token;
        if (! is_string($token) || strlen($token) < 32) {
            return $this->respondSuccess([
                'sent' => false,
                'reason' => 'recipient_has_no_fcm_token',
            ], 'Recipient has not registered an FCM token yet.');
        }

        $title = $validated['notification_title'];
        $bodyRaw = $validated['notification_body'] ?? '';
        $body = is_string($bodyRaw) ? trim($bodyRaw) : '';
        if ($body === '') {
            $body = 'New message';
        }

        $messageType = isset($validated['message_type']) ? (string) $validated['message_type'] : '';
        $peerAppUserId = isset($validated['peer_app_user_id']) ? (int) $validated['peer_app_user_id'] : 0;
        $peerUsername = isset($validated['peer_username']) ? trim((string) $validated['peer_username']) : '';
        $peerAvatarUrl = isset($validated['peer_avatar_url']) ? trim((string) $validated['peer_avatar_url']) : '';

        $data = [
            'type' => 'chat_message',
            'peerFirebaseUid' => (string) $validated['sender_firebase_uid'],
            'peerAppUserId' => (string) $peerAppUserId,
            'peerUsername' => $peerUsername,
            'peerAvatarUrl' => $peerAvatarUrl,
            'messageType' => $messageType,
            'title' => $title,
            'body' => $body,
        ];

        $result = $fcm->sendToDevice($token, $title, $body, $data, 'gameo_messages');

        if (! $result['ok'] && $fcm->responseIndicatesInvalidToken($result['body'])) {
            $recipient->forceFill([
                'fcm_token' => null,
                'fcm_token_updated_at' => null,
            ])->save();
            Log::info('FCM: cleared stale token for user.', ['user_id' => $recipient->id]);
        }

        return $this->respondSuccess([
            'sent' => $result['ok'],
            'fcm_status' => $result['status'],
        ], $result['ok'] ? 'Push sent.' : 'Push could not be sent (check server logs / FCM config).');
    }

    /**
     * Called by the Flutter app after [POST api/matches] returns `matched`, mirroring [notifyPeer].
     * Sends `match_found` FCM to the peer using `users.fcm_token`.
     */
    public function notifyMatchPeer(Request $request, FcmV1Sender $fcm): JsonResponse
    {
        $validated = $request->validate([
            'recipient_firebase_uid' => ['required', 'string', 'max:128'],
            'sender_firebase_uid' => ['required', 'string', 'max:128'],
            'peer_username' => ['nullable', 'string', 'max:255'],
            'peer_image_url' => ['nullable', 'string', 'max:2048'],
            'game_name' => ['nullable', 'string', 'max:255'],
            'peer_app_user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var User $sender */
        $sender = $request->user();

        if ($sender->firebase_uid === null || $sender->firebase_uid === '') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has no firebase_uid; cannot verify sender.',
            ], 422);
        }

        if ($sender->firebase_uid !== $validated['sender_firebase_uid']) {
            return response()->json([
                'success' => false,
                'message' => 'sender_firebase_uid does not match the authenticated user.',
            ], 403);
        }

        if ($sender->firebase_uid === $validated['recipient_firebase_uid']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot notify yourself.',
            ], 422);
        }

        $recipient = User::query()
            ->where('firebase_uid', $validated['recipient_firebase_uid'])
            ->first();

        if ($recipient === null) {
            return $this->respondSuccess([
                'sent' => false,
                'reason' => 'recipient_not_found',
            ], 'Recipient is not registered on this backend.');
        }

        $token = $recipient->fcm_token;
        if (! is_string($token) || strlen($token) < 32) {
            return $this->respondSuccess([
                'sent' => false,
                'reason' => 'recipient_has_no_fcm_token',
            ], 'Recipient has not registered an FCM token yet.');
        }

        $game = isset($validated['game_name']) ? trim((string) $validated['game_name']) : '';
        $title = MatchNotificationCopy::title();
        $description = MatchNotificationCopy::description($game);

        $peerUsername = isset($validated['peer_username']) ? trim((string) $validated['peer_username']) : '';
        $peerImageUrl = isset($validated['peer_image_url']) ? trim((string) $validated['peer_image_url']) : '';
        $peerAppUserId = isset($validated['peer_app_user_id']) ? (int) $validated['peer_app_user_id'] : 0;

        $data = [
            'type' => 'match_found',
            'title' => $title,
            'description' => $description,
            'body' => $description,
            'peerUsername' => $peerUsername,
            'peerImageUrl' => $peerImageUrl,
            'gameName' => $game,
            'peerFirebaseUid' => (string) $validated['sender_firebase_uid'],
            'peerAppUserId' => (string) max(0, $peerAppUserId),
        ];

        $result = $fcm->sendToDevice($token, $title, $description, $data, 'gameo_matches');

        if (! $result['ok'] && $fcm->responseIndicatesInvalidToken($result['body'])) {
            $recipient->forceFill([
                'fcm_token' => null,
                'fcm_token_updated_at' => null,
            ])->save();
            Log::info('FCM: cleared stale token for user (match).', ['user_id' => $recipient->id]);
        }

        return $this->respondSuccess([
            'sent' => $result['ok'],
            'fcm_status' => $result['status'],
        ], $result['ok'] ? 'Match push sent.' : 'Match push could not be sent (check server logs / FCM config).');
    }
}
