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
     * Resolve [FCM_GOOGLE_APPLICATION_CREDENTIALS]: relative paths are not read from CWD
     * (often wrong under Apache / shared hosting). Try Laravel base_path, public_path, storage_path.
     */
    private function resolveCredentialsPath(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }

        $norm = str_replace('\\', '/', $trim);
        $candidates = [];

        $push = function (?string $p) use (&$candidates): void {
            if (is_string($p) && $p !== '') {
                $candidates[] = $p;
            }
        };

        $push($trim);
        if ($this->isAbsolutePath($norm)) {
            $push($norm);
        } else {
            $push(base_path($trim));
            $push(base_path($norm));
            $push(base_path(ltrim($norm, '/')));
            if (str_starts_with($norm, 'public/')) {
                $push(public_path(substr($norm, strlen('public/'))));
            }
            if (str_starts_with($norm, 'storage/')) {
                $push(storage_path(substr($norm, strlen('storage/'))));
            }
            // Common layout: key only under storage/app (set env to storage/app/firebase/key.json)
            $push(storage_path('app/'.ltrim($norm, '/')));
        }

        foreach (array_unique($candidates) as $p) {
            if (is_readable($p)) {
                $real = realpath($p);

                return $real !== false ? $real : $p;
            }
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':';
    }

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
        $path = $this->resolveCredentialsPath(config('fcm.credentials_path'));
        $projectId = config('fcm.project_id');

        if ($path === null) {
            Log::warning('FCM: missing or unreadable FCM_GOOGLE_APPLICATION_CREDENTIALS.', [
                'hint' => 'Use an absolute filesystem path, or a path relative to the Laravel root '
                    .'(e.g. storage/app/firebase/key.json), not a URL. Relative paths are resolved via base_path(), '
                    .'public_path() for public/…, and storage_path() for storage/….',
                'configured_raw' => is_string(config('fcm.credentials_path')) ? 'set' : 'empty',
            ]);

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
