<?php

namespace App\Http\Resources;

use App\Models\User;
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
            /** Other participant’s Firebase uid for the authenticated user (1:1 chat). */
            'chat_peer_firebase_uid' => $this->peerFirebaseUidForRequest($request),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'target_user' => new UserSummaryResource($this->whenLoaded('targetUser')),
            'created_at' => $this->created_at,
        ];
    }

    private function peerFirebaseUidForRequest(Request $request): string
    {
        $auth = $request->user();
        if (! $auth instanceof User) {
            return '';
        }

        $authId = (int) $auth->id;
        $userId = (int) $this->resource->user_id;
        $targetId = (int) $this->resource->target_user_id;

        $peer = null;
        if ($authId === $userId && $this->relationLoaded('targetUser')) {
            $peer = $this->resource->targetUser;
        } elseif ($authId === $targetId && $this->relationLoaded('user')) {
            $peer = $this->resource->user;
        }

        if ($peer === null) {
            return '';
        }

        $raw = $peer->firebase_uid ?? '';

        return is_string($raw) ? trim($raw) : '';
    }
}
