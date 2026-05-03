<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $games = [
            ['name' => 'League of Legends', 'image' => 'https://example.com/lol.jpg'],
            ['name' => 'Valorant', 'image' => 'https://example.com/valorant.jpg'],
            ['name' => 'Fortnite', 'image' => 'https://example.com/fortnite.jpg'],
        ];

        foreach ($games as $game) {
            Game::query()->updateOrCreate(
                ['name' => $game['name']],
                $game
            );
        }
    }
}

