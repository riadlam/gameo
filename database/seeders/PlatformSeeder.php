<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = ['PC', 'PlayStation', 'Xbox', 'Mobile'];

        foreach ($names as $name) {
            Platform::query()->updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}

