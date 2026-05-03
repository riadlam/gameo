<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameStatDefinitionResource extends JsonResource
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
            'key' => $this->key,
            'label' => $this->label,
            'value_type' => $this->value_type,
            'unit' => $this->unit,
            'is_seasonal' => (bool) $this->is_seasonal,
            'game' => new GameResource($this->whenLoaded('game')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
