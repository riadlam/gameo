<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
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
            'rawg_id' => $this->rawg_id,
            'name' => $this->name,
            'image' => $this->image,
            'is_populer' => (bool) $this->is_populer,
            'is_favourite' => (bool) $this->is_populer,
            'platforms' => $this->whenLoaded(
                'platforms',
                fn () => $this->platforms->map(fn ($platform) => [
                    'id' => $platform->id,
                    'name' => $platform->name,
                    'game_platform_id' => $platform->pivot->id,
                ])->values(),
            ),
            'rank_tiers' => GameRankTierResource::collection($this->whenLoaded('rankTiers')),
            'stat_definitions' => GameStatDefinitionResource::collection($this->whenLoaded('statDefinitions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
