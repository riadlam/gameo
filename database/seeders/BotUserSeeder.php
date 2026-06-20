<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GamePlatform;
use App\Models\User;
use App\Models\UserGame;
use App\Models\UserPlatform;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BotUserSeeder extends Seeder
{
    private const BASE_URL = 'https://himayati.diaszone.com/public/storage/profile_preselect';

    private const BOTS_PER_GAME = 20;

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

    private const MALE_PREFIXES = [
        'Amir', 'Yanis', 'Redouane', 'Sami', 'Lyes', 'Fares', 'Nadir', 'Jalel',
        'Karim', 'Youcef', 'Aymen', 'Hamza', 'Islam', 'Ryadh', 'Bilal', 'Nassim',
        'Zakaria', 'Omar', 'Fahd', 'Khaled', 'Abdullah', 'Tarek', 'Mahmoud',
        'Youssef', 'Ali', 'Samir', 'Walid', 'Adam', 'Saad', 'Hussein',
    ];

    private const FEMALE_PREFIXES = [
        'Lina', 'Maria', 'Nesrine', 'Yasmin', 'Rania', 'Chaima', 'Aicha', 'Djamila',
        'Sarah', 'Nour', 'Amel', 'Imane', 'Rima', 'Asmaa', 'Meriem', 'Yasmine',
        'Hana', 'Noura', 'Rawan', 'Dana', 'Fatima', 'Sana', 'Ines', 'Amina',
        'Kawtar', 'Salma', 'Lara', 'Shaima', 'Maram', 'Zainab',
    ];

    private const SUFFIXES = [
        'Gamer', 'Player', 'Pro', 'Elite', 'Star', 'Wolf', 'Fury', 'Shot',
        'Ace', 'King', 'Queen', 'Shadow', 'Storm', 'Fire', 'Ice', 'Blade',
        'Strike', 'Blaze', 'Flash', 'Ghost', 'Hawk', 'Tiger', 'Wolf', 'Knight',
        'Phoenix', 'Dragon', 'Viper', 'Fox', 'Bear', 'Lion', 'Bolt',
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
        'Love meeting new people through gaming 🌟 Add me and let\'s play!',
        'Weekend warrior but I go hard 💥 Let\'s run some games!',
        'Mostly play in evenings after studies 📚 Game time is my me time',
        'Competitive player looking for serious teammates ⚔️ No randoms pls',
        'I play for fun but I like winning too 🏆 Add me up!',
    ];

    public function run(): void
    {
        $games = Game::where('is_populer', true)->get();

        if ($games->isEmpty()) {
            $this->command->warn('No popular games found (is_populer = 1). Nothing to seed.');

            return;
        }

        $totalGames = $games->count();
        $this->command->info("Found {$totalGames} popular game(s) to seed.");

        $completed = 0;
        $failed = 0;

        foreach ($games as $game) {
            $ok = $this->seedGame($game);
            if ($ok) {
                $completed++;
            } else {
                $failed++;
            }
        }

        $this->command->info("=== DONE ===");
        $this->command->info("  Completed: {$completed} game(s)");
        $this->command->info("  Failed:    {$failed} game(s)");

        if ($failed > 0) {
            $this->command->warn("Re-run the seeder after fixing issues to seed remaining games.");
        }
    }

    private function seedGame(Game $game): bool
    {
        $gamePlatforms = GamePlatform::where('game_id', $game->id)->get();

        if ($gamePlatforms->isEmpty()) {
            $this->command->warn("[SKIP] {$game->name} — no game_platform entries found");

            return false;
        }

        $this->command->info("");
        $this->command->info(">>> START: {$game->name}");

        DB::beginTransaction();
        try {
            for ($i = 0; $i < self::BOTS_PER_GAME; $i++) {
                $gender = $i % 2 === 0 ? 'male' : 'female';
                $username = $this->randomUsername($gender);
                $age = rand(18, 25);
                $birthDate = Carbon::now()->subYears($age)->subDays(rand(0, 365));
                $region = self::ALGERIAN_REGIONS[array_rand(self::ALGERIAN_REGIONS)];
                $avatarIndex = rand(1, 4);
                $avatarUrl = $this->avatarUrl($gender, $avatarIndex);

                $user = User::create([
                    'username' => $username,
                    'email' => strtolower($username) . '@gameo.bot',
                    'password' => Hash::make('password123'),
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'region' => $region,
                    'bio' => self::BIOS[array_rand(self::BIOS)],
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
                    'game_id' => $game->id,
                    'skill_level' => rand(1, 5),
                    'play_time_hours' => rand(10, 500),
                    'created_at' => Carbon::now(),
                ]);

                foreach ($gamePlatforms as $gp) {
                    UserPlatform::create([
                        'user_id' => $user->id,
                        'platform_id' => $gp->platform_id,
                        'game_platform_id' => $gp->id,
                        'username_on_platform' => $username,
                    ]);
                }

                if ($i % 5 === 4 || $i === self::BOTS_PER_GAME - 1) {
                    $this->command->info("  {$game->name}: " . ($i + 1) . "/" . self::BOTS_PER_GAME);
                }
            }

            DB::commit();
            $this->command->info("<<< DONE:  {$game->name} — " . self::BOTS_PER_GAME . " bots created");

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error("<<< FAIL: {$game->name} — {$e->getMessage()}");

            return false;
        }
    }

    private function randomUsername(string $gender): string
    {
        $prefixes = $gender === 'male' ? self::MALE_PREFIXES : self::FEMALE_PREFIXES;
        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = self::SUFFIXES[array_rand(self::SUFFIXES)];
        $number = rand(10, 999);

        return "{$prefix}{$suffix}{$number}";
    }

    private function avatarUrl(string $gender, int $index): string
    {
        return self::BASE_URL . "/{$gender}/{$gender}_{$index}.jpg";
    }
}
