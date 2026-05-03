<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
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
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'status' => $this->status,
            'user_one' => new UserSummaryResource($this->whenLoaded('userOne')),
            'user_two' => new UserSummaryResource($this->whenLoaded('userTwo')),
            'created_at' => $this->created_at,
        ];
    }
}
