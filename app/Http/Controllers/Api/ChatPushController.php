<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\FcmV1Sender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatPushController extends BaseApiController
{
    public function registerFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'min:32', 'max:8192'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'fcm_token' => $validated['fcm_token'],
            'fcm_token_updated_at' => now(),
        ])->save();

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
}
