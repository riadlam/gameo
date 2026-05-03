<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Rewrites absolute URLs under `/storage/` to use the current request origin.
 * Uses the request base path when the app is served under a subdirectory (e.g. /public/).
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

        $origin = rtrim($request->getSchemeAndHttpHost(), '/');
        $basePath = $request->getBasePath();
        if (is_string($basePath) && $basePath !== '' && $basePath !== '/') {
            $origin .= rtrim($basePath, '/');
        }

        return $origin.$path.$query;
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
