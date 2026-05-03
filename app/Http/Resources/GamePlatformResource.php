<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamePlatformResource extends JsonResource
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
            'game_id' => $this->game_id,
            'platform_id' => $this->platform_id,
            'game' => new GameResource($this->whenLoaded('game')),
            'platform' => new PlatformResource($this->whenLoaded('platform')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
