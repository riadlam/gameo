<?php

namespace App\Http\Resources;

use App\Services\UserProfilePhotoService;
use App\Support\ApiPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photoSlots = UserProfilePhotoService::slotsFromUser($this->resource);
        $main = UserProfilePhotoService::mainUrl($photoSlots) ?? $this->first_cover;

        return [
            'id' => $this->id,
            'username' => $this->username,
            /** Same identity as Firebase Auth `uid` when the user signed in with Firebase. */
            'firebase_uid' => $this->firebase_uid,
            'email' => $this->email,
            'avatar' => ApiPublicUrl::rewrite($this->avatar, $request),
            'first_cover' => ApiPublicUrl::rewrite($main, $request),
            'profile_images' => ApiPublicUrl::rewriteProfileSlots($photoSlots, $request),
            'bio' => $this->bio,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'region' => $this->region,
            'is_onboarding' => (bool) $this->is_onboarding,
            'is_online' => $this->resource->isEffectivelyOnline(60),
            'last_seen' => $this->last_seen,
            /** People who follow this user (`followers.following_id` = user). */
            'followers_count' => (int) ($this->followers_count ?? 0),
            /** People this user follows (`followers.follower_id` = user). */
            'following_count' => (int) ($this->following_count ?? 0),
            /** Keys: mobile, pc, xbox, playstation — from `user_platforms`. */
            'platform_keys' => $this->resource->gamingPlatformKeys(),
            /** Favourite game ids from `user_games`. */
            'game_ids' => $this->resource->gamingGameIds(),
        ];
    }
}
