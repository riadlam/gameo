<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'follower_id' => $this->follower_id,
            'following_id' => $this->following_id,
            'is_blocked' => (bool) $this->is_blocked,
            'follower' => new UserSummaryResource($this->whenLoaded('follower')),
            'following' => new UserSummaryResource($this->whenLoaded('following')),
            'created_at' => $this->created_at,
        ];
    }
}
