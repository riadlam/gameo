<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GamePlatform;
use App\Models\User;
use App\Models\UserGame;
use App\Models\UserPlatform;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BotUserSeeder extends Seeder
{
    private const BASE_URL = 'https://himayati.diaszone.com/public/storage/profile_preselect';

    private const ALGERIAN_REGIONS = [
        'Algeria,Algiers',
        'Algeria,Oran',
        'Algeria,Constantine',
        'Algeria,Biskra',
        'Algeria,Annaba',
        'Algeria,Tlemcen',
        'Algeria,Setif',
        'Algeria,Batna',
        'Algeria,Blida',
        'Algeria,Djelfa',
    ];

    private const MALE_USERNAMES = [
        'AmirRocket_DZ', 'YanisBoost_DZ', 'RedouaneAerial',
        'SamiProDZ', 'LyesDZ_RL', 'FaresStriker',
        'NadirDZ_27', 'JalelRocket',
    ];

    private const FEMALE_USERNAMES = [
        'LinaBoost_DZ', 'MariaRocket_DZ', 'NesrineAerial',
        'YasminProDZ', 'RaniaDZ_RL', 'ChaimaStriker',
        'AichaDZ_27', 'DjamilaRocket',
    ];

    private const BIOS = [
        'Just here to game and chill 🎮 Add me if you wanna squad up!',
        'Love competitive gaming and making new friends 👾 VC preferred!',
        'Looking for teammates who actually communicate 🎧 HMU if you\'re down',
        'FPS & racing games are my thing but I play everything 🚀 Let\'s play!',
        'Chill gamer looking for fun people to play with 🎯 No toxicity pls',
        'Add me if you wanna grind ranked together 💪 Let\'s get that W',
        'I play daily after work 🕹️ Always down for some matches',
        'Casual but competitive when it counts 😎 Friends welcome!',
        'New to this platform, looking for cool people to game with 🎮',
        'Gamer since childhood, still going strong 🔥 Add me up!',
    ];

    public function run(): void
    {
        $game = Game::find(27);

        if ($game === null) {
            $this->command->warn('Game ID 27 (Rocket League) not found. Skipping bot seeding.');

            return;
        }

        $gamePlatforms = GamePlatform::where('game_id', 27)->get();

        if ($gamePlatforms->isEmpty()) {
            $this->command->warn('No game_platform entries found for game_id 27. Skipping bot seeding.');

            return;
        }

        $this->command->info('Seeding 5 bot users for game: ' . $game->name);

        for ($i = 0; $i < 5; $i++) {
            $gender = $i % 2 === 0 ? 'male' : 'female';
            $username = $this->randomUsername($gender);
            $age = rand(18, 25);
            $birthDate = Carbon::now()->subYears($age)->subDays(rand(0, 365));
            $region = self::ALGERIAN_REGIONS[array_rand(self::ALGERIAN_REGIONS)];
            $city = explode(',', $region)[1];
            $avatarIndex = rand(1, 4);
            $avatarUrl = $this->avatarUrl($gender, $avatarIndex);
            $bio = $this->randomBio();

            $user = User::create([
                'username' => $username,
                'email' => strtolower($username) . '@gameo.bot',
                'password' => Hash::make('password123'),
                'gender' => $gender,
                'birth_date' => $birthDate,
                'region' => $region,
                'bio' => $bio,
                'avatar' => $avatarUrl,
                'first_cover' => $avatarUrl,
                'profile_images' => json_encode([
                    ['slot' => 0, 'url' => $avatarUrl, 'main' => true],
                    ['slot' => 1, 'url' => null, 'main' => false],
                    ['slot' => 2, 'url' => null, 'main' => false],
                ]),
                'is_onboarding' => false,
                'is_online' => false,
                'is_bot' => true,
                'last_seen' => Carbon::now(),
            ]);

            UserGame::create([
                'user_id' => $user->id,
                'game_id' => 27,
                'skill_level' => rand(1, 5),
                'play_time_hours' => rand(10, 500),
                'created_at' => Carbon::now(),
            ]);

            $gp = $gamePlatforms->random();
            UserPlatform::create([
                'user_id' => $user->id,
                'platform_id' => $gp->platform_id,
                'game_platform_id' => $gp->id,
                'username_on_platform' => $username,
            ]);

            $this->command->info("  Created bot: {$username} ({$gender}, {$age} yrs, {$region})");
        }
    }

    private function randomUsername(string $gender): string
    {
        $pool = $gender === 'male' ? self::MALE_USERNAMES : self::FEMALE_USERNAMES;
        $base = $pool[array_rand($pool)];
        $suffix = rand(10, 99);

        return "{$base}{$suffix}";
    }

    private function avatarUrl(string $gender, int $index): string
    {
        return self::BASE_URL . "/{$gender}/{$gender}_{$index}.jpg";
    }

    private function randomBio(): string
    {
        return self::BIOS[array_rand(self::BIOS)];
    }
}
