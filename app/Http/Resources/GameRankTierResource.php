<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameRankTierResource extends JsonResource
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
            'game_platform_id' => $this->game_platform_id,
            'code' => $this->code,
            'label' => $this->label,
            'order_index' => $this->order_index,
            'game' => new GameResource($this->whenLoaded('game')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
