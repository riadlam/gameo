<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

/**
 * Client-driven presence: foreground / heartbeat / background.
 */
class UserPresenceController extends BaseApiController
{
    public function setOnline(Request $request)
    {
        $user = $request->user();
        $user->forceFill([
            'is_online' => true,
            'last_seen' => now(),
        ])->save();

        return $this->respondSuccess([], 'Online.');
    }

    public function heartbeat(Request $request)
    {
        $user = $request->user();
        $user->forceFill([
            'is_online' => true,
            'last_seen' => now(),
        ])->save();

        return $this->respondSuccess([], 'Heartbeat ok.');
    }

    public function setOffline(Request $request)
    {
        $user = $request->user();
        $user->forceFill([
            'is_online' => false,
            'last_seen' => now(),
        ])->save();

        return $this->respondSuccess([], 'Offline.');
    }
}
