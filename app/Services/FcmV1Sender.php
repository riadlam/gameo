<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmV1Sender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array<string, string>  $data  FCM data payload (all values must be strings).
     * @return array{ok: bool, status: int|null, body: string|null}
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data,
        string $androidChannelId = 'gameo_messages',
    ): array {
        $path = config('fcm.credentials_path');
        $projectId = config('fcm.project_id');

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            Log::warning('FCM: missing or unreadable FCM_GOOGLE_APPLICATION_CREDENTIALS.');

            return ['ok' => false, 'status' => null, 'body' => 'credentials_not_configured'];
        }

        try {
            $jsonKey = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('FCM: could not parse service account JSON.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'body' => 'invalid_credentials_json'];
        }

        if (! is_string($projectId) || $projectId === '') {
            $projectId = isset($jsonKey['project_id']) ? (string) $jsonKey['project_id'] : '';
        }
        if ($projectId === '') {
            Log::warning('FCM: project id missing (set FCM_PROJECT_ID or use JSON with project_id).');

            return ['ok' => false, 'status' => null, 'body' => 'project_id_missing'];
        }

        try {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $jsonKey);
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;
        } catch (Throwable $e) {
            Log::warning('FCM: OAuth token fetch failed.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'body' => 'oauth_failed'];
        }

        if (! is_string($accessToken) || $accessToken === '') {
            Log::warning('FCM: empty access token from service account.');

            return ['ok' => false, 'status' => null, 'body' => 'empty_access_token'];
        }

        $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId);

        $message = [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => $androidChannelId,
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
            'data' => $data,
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, ['message' => $message]);

        $ok = $response->successful();
        if (! $ok) {
            Log::warning('FCM: send failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return [
            'ok' => $ok,
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    public function responseIndicatesInvalidToken(?string $body): bool
    {
        if ($body === null || $body === '') {
            return false;
        }

        return str_contains($body, 'NOT_FOUND')
            || str_contains($body, 'UNREGISTERED');
    }
}
