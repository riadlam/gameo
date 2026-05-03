<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserGameStatResource extends JsonResource
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
            'stat_definition_id' => $this->stat_definition_id,
            'season' => $this->season,
            'value_int' => $this->value_int,
            'value_decimal' => $this->value_decimal,
            'value_text' => $this->value_text,
            'value_rank_tier_id' => $this->value_rank_tier_id,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'game' => new GameResource($this->whenLoaded('game')),
            'definition' => new GameStatDefinitionResource($this->whenLoaded('definition')),
            'rank_tier' => new GameRankTierResource($this->whenLoaded('rankTier')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
