<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keep seed set intentionally minimal/easy to delete later.
        $this->call([
            PlatformSeeder::class,
            GameSeeder::class,
            GamePlatformSeeder::class,
            GameRankTierSeeder::class,
        ]);
    }
}
