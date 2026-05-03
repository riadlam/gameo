<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatPushController;
use App\Http\Controllers\Api\FollowerController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GamePlatformController;
use App\Http\Controllers\Api\GameRankTierController;
use App\Http\Controllers\Api\GameStatDefinitionController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\UserGameController;
use App\Http\Controllers\Api\UserPresenceController;
use App\Http\Controllers\Api\UserPublicProfileController;
use App\Http\Controllers\Api\UserGameStatController;
use App\Http\Controllers\Api\UserPlatformController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/firebase-login', [AuthController::class, 'firebaseLogin']);
Route::get('auth/check-username', [AuthController::class, 'checkUsername']);

Route::get('platforms', [PlatformController::class, 'index']);
Route::get('platforms/{platform}', [PlatformController::class, 'show']);
Route::get('games', [GameController::class, 'index']);
Route::get('games/ids-for-platforms', [GameController::class, 'idsForPlatforms']);
Route::get('games/{game}', [GameController::class, 'show']);
Route::get('game-rank-tiers', [GameRankTierController::class, 'index']);
Route::get('game-rank-tiers/{game_rank_tier}', [GameRankTierController::class, 'show']);
Route::get('game-stat-definitions', [GameStatDefinitionController::class, 'index']);
Route::get('game-stat-definitions/{game_stat_definition}', [GameStatDefinitionController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::get('auth/meet-library-games', [AuthController::class, 'meetLibraryGames']);
    Route::get('auth/meet-game-ranks', [AuthController::class, 'meetGameRanks']);
    Route::post('auth/meet-game-ranks', [AuthController::class, 'saveMeetGameRank']);
    Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/presence/set-online', [UserPresenceController::class, 'setOnline']);
    Route::post('auth/presence/heartbeat', [UserPresenceController::class, 'heartbeat']);
    Route::post('auth/presence/set-offline', [UserPresenceController::class, 'setOffline']);
    Route::delete('auth/account', [AuthController::class, 'destroyAccount']);
    Route::post('auth/upload-profile-media', [AuthController::class, 'uploadProfileMedia']);
    Route::post('auth/profile-photo', [AuthController::class, 'storeProfilePhotoSlot']);
    Route::post('auth/profile-photo/remove', [AuthController::class, 'removeProfilePhoto']);
    Route::patch('auth/profile-photo/main', [AuthController::class, 'setProfilePhotoMain']);
    Route::post('auth/onboarding-complete', [AuthController::class, 'markOnboardingComplete']);
    Route::post('auth/sync-gaming', [AuthController::class, 'syncGamingLibrary']);
    Route::post('auth/fcm-token', [ChatPushController::class, 'registerFcmToken']);
    Route::post('chat/notify-peer', [ChatPushController::class, 'notifyPeer']);

    Route::apiResource('game-platforms', GamePlatformController::class)->except(['index', 'show']);
    Route::post('platforms', [PlatformController::class, 'store']);
    Route::put('platforms/{platform}', [PlatformController::class, 'update']);
    Route::patch('platforms/{platform}', [PlatformController::class, 'update']);
    Route::delete('platforms/{platform}', [PlatformController::class, 'destroy']);
    Route::post('games', [GameController::class, 'store']);
    Route::put('games/{game}', [GameController::class, 'update']);
    Route::patch('games/{game}', [GameController::class, 'update']);
    Route::delete('games/{game}', [GameController::class, 'destroy']);
    Route::apiResource('user-games', UserGameController::class);
    Route::apiResource('user-platforms', UserPlatformController::class);
    Route::post('game-rank-tiers', [GameRankTierController::class, 'store']);
    Route::put('game-rank-tiers/{game_rank_tier}', [GameRankTierController::class, 'update']);
    Route::patch('game-rank-tiers/{game_rank_tier}', [GameRankTierController::class, 'update']);
    Route::delete('game-rank-tiers/{game_rank_tier}', [GameRankTierController::class, 'destroy']);
    Route::post('game-stat-definitions', [GameStatDefinitionController::class, 'store']);
    Route::put('game-stat-definitions/{game_stat_definition}', [GameStatDefinitionController::class, 'update']);
    Route::patch('game-stat-definitions/{game_stat_definition}', [GameStatDefinitionController::class, 'update']);
    Route::delete('game-stat-definitions/{game_stat_definition}', [GameStatDefinitionController::class, 'destroy']);
    Route::apiResource('user-game-stats', UserGameStatController::class);
    Route::apiResource('matches', MatchController::class);
    Route::get('matches-candidates', [MatchController::class, 'candidates']);
    Route::get('friends', [FriendController::class, 'index']);
    Route::get('users/{user}', [UserPublicProfileController::class, 'show']);
    Route::get('followers/block-status/{otherUserId}', [FollowerController::class, 'blockStatus'])
        ->whereNumber('otherUserId');
    Route::post('followers/block/{otherUserId}', [FollowerController::class, 'block'])
        ->whereNumber('otherUserId');
    Route::post('followers/unblock/{otherUserId}', [FollowerController::class, 'unblock'])
        ->whereNumber('otherUserId');
    Route::apiResource('followers', FollowerController::class);
    Route::apiResource('friendships', FriendshipController::class);
});

