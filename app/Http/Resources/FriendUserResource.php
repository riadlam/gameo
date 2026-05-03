<?php

namespace App\Http\Resources;

use App\Services\UserProfilePhotoService;
use App\Support\ApiPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Users linked via `followers` (people you follow and people who follow you), for the friends tab.
 */
class FriendUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photoSlots = UserProfilePhotoService::slotsFromUser($this->resource);
        $main = UserProfilePhotoService::mainUrl($photoSlots) ?? $this->first_cover;

        return [
            'id' => $this->id,
            'username' => $this->username,
            /** Present when the friend has linked Firebase (required for in-app chat). */
            'firebase_uid' => $this->firebase_uid,
            'avatar' => ApiPublicUrl::rewrite($this->avatar, $request),
            'first_cover' => ApiPublicUrl::rewrite($main, $request),
            'profile_images' => ApiPublicUrl::rewriteProfileSlots($photoSlots, $request),
            'is_online' => $this->resource->isEffectivelyOnline(60),
            'last_seen' => $this->last_seen?->toIso8601String(),
            'first_game_name' => $this->first_game_name !== null && $this->first_game_name !== ''
                ? (string) $this->first_game_name
                : null,
        ];
    }
}
