<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile photos: exactly 3 slots (0–2). Each slot is
 * ['slot' => int, 'url' => ?string, 'main' => bool]. Exactly one slot with a URL may be main.
 */
class UserProfilePhotoService
{
    public const SLOT_COUNT = 3;

    /**
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function defaultSlots(): array
    {
        $out = [];
        for ($i = 0; $i < self::SLOT_COUNT; $i++) {
            $out[] = [
                'slot' => $i,
                'url' => null,
                'main' => $i === 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function slotsFromUser(User $user): array
    {
        $slots = self::defaultSlots();
        $raw = $user->profile_images;

        if (! is_array($raw) || $raw === []) {
            if ($user->first_cover) {
                $slots[0]['url'] = $user->first_cover;
                $slots[0]['main'] = true;
                $slots[1]['main'] = false;
                $slots[2]['main'] = false;
            }

            return self::ensureSingleMain($slots);
        }

        if (isset($raw[0]) && is_string($raw[0])) {
            foreach ($raw as $i => $u) {
                if ($i >= self::SLOT_COUNT) {
                    break;
                }
                if (is_string($u) && $u !== '') {
                    $slots[$i]['url'] = $u;
                    $slots[$i]['main'] = $i === 0;
                }
            }

            return self::ensureSingleMain($slots);
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $slot = (int) ($item['slot'] ?? -1);
            if ($slot < 0 || $slot >= self::SLOT_COUNT) {
                continue;
            }
            $url = $item['url'] ?? null;
            $slots[$slot]['url'] = is_string($url) && $url !== '' ? $url : null;
            $slots[$slot]['main'] = (bool) ($item['main'] ?? false);
        }

        return self::ensureSingleMain($slots);
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $slots
     * @return array{profile_images: list<array{slot: int, url: ?string, main: bool}>, first_cover: ?string, avatar: ?string}
     */
    public static function fillAttributesForSlots(User $user, array $slots): array
    {
        $slots = self::ensureSingleMain($slots);
        $mainUrl = self::mainUrl($slots);

        return [
            'profile_images' => $slots,
            'first_cover' => $mainUrl,
            'avatar' => $mainUrl ?? $user->avatar,
        ];
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $slots
     */
    public static function saveSlotsToUser(User $user, array $slots): void
    {
        $user->forceFill(self::fillAttributesForSlots($user, $slots))->save();
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $slots
     */
    public static function mainUrl(array $slots): ?string
    {
        foreach ($slots as $s) {
            if (! empty($s['url']) && ! empty($s['main'])) {
                return $s['url'];
            }
        }

        $slot0 = $slots[0]['url'] ?? null;
        if (! empty($slot0)) {
            return $slot0;
        }

        foreach ($slots as $s) {
            if (! empty($s['url'])) {
                return $s['url'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{slot: int, url: ?string, main: bool}>  $slots
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function ensureSingleMain(array $slots): array
    {
        $hasUrl = false;
        foreach ($slots as $s) {
            if (! empty($s['url'])) {
                $hasUrl = true;
                break;
            }
        }
        if (! $hasUrl) {
            for ($i = 0; $i < self::SLOT_COUNT; $i++) {
                $slots[$i]['main'] = $i === 0;
            }

            return $slots;
        }

        $mainIndex = null;
        foreach ($slots as $i => $s) {
            if (! empty($s['main']) && ! empty($s['url'])) {
                $mainIndex = $i;
                break;
            }
        }
        if ($mainIndex === null) {
            if (! empty($slots[0]['url'])) {
                $mainIndex = 0;
            } else {
                foreach ($slots as $i => $s) {
                    if (! empty($s['url'])) {
                        $mainIndex = $i;
                        break;
                    }
                }
            }
        }

        for ($i = 0; $i < self::SLOT_COUNT; $i++) {
            $slots[$i]['main'] = $i === $mainIndex;
        }

        return $slots;
    }

    public static function deletePublicFileForUrl(string $url): void
    {
        $path = self::publicDiskPathFromUrl($url);
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Deletes the underlying public-disk file only when it lives under this user's `profile_media` folder.
     */
    public static function deletePublicFileForUrlIfOwned(string $url, int $userId): void
    {
        if (! self::urlBelongsToUser($url, $userId)) {
            return;
        }
        self::deletePublicFileForUrl($url);
    }

    public static function publicDiskPathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'storage/')) {
            return Str::after($path, 'storage/');
        }

        return null;
    }

    public static function urlBelongsToUser(string $url, int $userId): bool
    {
        return str_contains($url, '/profile_media/'.$userId.'/');
    }

    /**
     * Whether [candidate] refers to the same image as stored in a slot (exact string or same public file path).
     */
    public static function profileSlotContainsUrl(?string $slotUrl, string $candidate): bool
    {
        if (! is_string($slotUrl) || $slotUrl === '') {
            return false;
        }
        if ($slotUrl === $candidate) {
            return true;
        }

        return self::urlsPointToSamePublicFile($slotUrl, $candidate);
    }

    /**
     * Same file on the public disk (ignores scheme/host — needed when API rewrites URLs).
     */
    public static function urlsPointToSamePublicFile(?string $a, ?string $b): bool
    {
        if (! is_string($a) || ! is_string($b) || $a === '' || $b === '') {
            return false;
        }
        $pa = self::publicDiskPathFromUrl($a);
        $pb = self::publicDiskPathFromUrl($b);

        return $pa !== null && $pb !== null && $pa === $pb;
    }

    /**
     * @param  list<string>  $galleryUrls  additional URLs after cover for slots 1–2
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function slotsFromOnboardingStrings(?string $firstCover, array $galleryUrls): array
    {
        $slots = self::defaultSlots();
        if ($firstCover) {
            $slots[0]['url'] = $firstCover;
            $slots[0]['main'] = true;
            $slots[1]['main'] = false;
            $slots[2]['main'] = false;
        }
        $gi = 0;
        for ($slot = 1; $slot < self::SLOT_COUNT; $slot++) {
            if ($gi >= count($galleryUrls)) {
                break;
            }
            $u = $galleryUrls[$gi++];
            if ($u !== '') {
                $slots[$slot]['url'] = $u;
            }
        }

        return self::ensureSingleMain($slots);
    }

    /**
     * @param  list<mixed>|null  $raw  Legacy string URLs, or objects with slot/url/main
     * @return list<array{slot: int, url: ?string, main: bool}>
     */
    public static function slotsFromOnboardingRequest(?string $firstCover, ?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return self::slotsFromOnboardingStrings($firstCover, []);
        }

        $first = $raw[0] ?? null;
        if (is_array($first) && (isset($first['slot']) || array_key_exists('url', $first))) {
            $slots = self::defaultSlots();
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $slot = (int) ($item['slot'] ?? -1);
                if ($slot < 0 || $slot >= self::SLOT_COUNT) {
                    continue;
                }
                $url = $item['url'] ?? null;
                $slots[$slot]['url'] = is_string($url) && $url !== '' ? $url : null;
                $slots[$slot]['main'] = (bool) ($item['main'] ?? false);
            }

            return self::ensureSingleMain($slots);
        }

        $strings = array_values(array_filter(
            $raw,
            fn ($u) => is_string($u) && $u !== ''
        ));

        return self::slotsFromOnboardingStrings($firstCover, $strings);
    }

    public static function storeUploadedFile(User $user, int $slot, UploadedFile $file): string
    {
        $base = 'profile_media/'.$user->id;
        $path = $file->store($base, 'public');

        return Storage::disk('public')->url($path);
    }
}
