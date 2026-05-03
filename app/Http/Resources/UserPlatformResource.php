<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPlatformResource extends JsonResource
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
            'user_id' => $this->user_id,
            'platform_id' => $this->platform_id,
            'game_platform_id' => $this->game_platform_id,
            'username_on_platform' => $this->username_on_platform,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'platform' => new PlatformResource($this->whenLoaded('platform')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
