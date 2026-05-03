<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserGameResource extends JsonResource
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
            'game_id' => $this->game_id,
            'skill_level' => $this->skill_level,
            'play_time_hours' => $this->play_time_hours,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'game' => new GameResource($this->whenLoaded('game')),
            'created_at' => $this->created_at,
        ];
    }
}
