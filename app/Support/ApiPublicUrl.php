<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Rewrites absolute URLs under `/storage/` to use the current HTTP request host.
 * Fixes profile images when APP_URL (e.g. localhost) differs from the host the app uses (e.g. LAN IP).
 */
final class ApiPublicUrl
{
    public static function rewrite(?string $url, ?Request $request = null): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $request ??= request();
        if ($request === null) {
            return $url;
        }

        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/storage/')) {
            return $url;
        }

        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        return rtrim($request->getSchemeAndHttpHost(), '/').$path.$query;
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $slots
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function rewriteProfileSlots(array $slots, ?Request $request = null): array
    {
        $out = [];
        foreach ($slots as $s) {
            $u = $s['url'] ?? null;
            $s['url'] = is_string($u) ? self::rewrite($u, $request) : $u;
            $out[] = $s;
        }

        return $out;
    }
}
