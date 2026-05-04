<?php

namespace App\Http\Resources;

use App\Support\MatchNotificationCopy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gameNameForCopy = '';
        if ($this->relationLoaded('gamePlatform')) {
            $n = $this->gamePlatform?->game?->name;
            $gameNameForCopy = is_string($n) ? trim($n) : '';
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'target_user_id' => $this->target_user_id,
            'game_platform_id' => $this->game_platform_id,
            'status' => $this->status,
            'game_name' => $this->whenLoaded('gamePlatform', function () {
                return $this->gamePlatform?->game?->name;
            }),
            /** From Laravel `.env` (`MATCH_TITLE`, `MATCH_DESCRIPTION`); change server-side without app update. */
            'match_title' => MatchNotificationCopy::title(),
            'match_description' => MatchNotificationCopy::description($gameNameForCopy),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'target_user' => new UserSummaryResource($this->whenLoaded('targetUser')),
            'created_at' => $this->created_at,
        ];
    }
}
