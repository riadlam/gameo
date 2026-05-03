<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GamePlatform;
use App\Models\Platform;
use Illuminate\Database\Seeder;

class GamePlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platformIds = Platform::query()
            ->whereIn('name', ['PC', 'PlayStation', 'Xbox', 'Mobile'])
            ->pluck('id', 'name');

        $map = [
            'League of Legends' => ['PC'],
            'Valorant' => ['PC'],
            'Fortnite' => ['PC', 'PlayStation', 'Xbox', 'Mobile'],
        ];

        foreach ($map as $gameName => $platformNames) {
            $game = Game::query()->where('name', $gameName)->first();
            if (! $game) {
                continue;
            }

            foreach ($platformNames as $platformName) {
                $platformId = $platformIds[$platformName] ?? null;
                if (! $platformId) {
                    continue;
                }

                GamePlatform::query()->updateOrCreate(
                    ['game_id' => $game->id, 'platform_id' => $platformId],
                    ['game_id' => $game->id, 'platform_id' => $platformId]
                );
            }
        }
    }
}

