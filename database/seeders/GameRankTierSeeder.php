<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GamePlatform;
use App\Models\GameRankTier;
use App\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GameRankTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sourcePath = base_path('../assets/competitive_games_with_ranks.txt');
        if (! is_file($sourcePath)) {
            $this->command?->warn("GameRankTierSeeder: source file not found at {$sourcePath}");
            return;
        }

        $lines = file($sourcePath, FILE_IGNORE_NEW_LINES) ?: [];
        $parsed = $this->parseRankSource($lines);
        if (empty($parsed)) {
            $this->command?->warn('GameRankTierSeeder: no rank entries parsed from source file.');
            return;
        }

        $platformMap = [
            'MOBILE' => 'Mobile',
            'PC' => 'PC',
            'XBOX' => 'Xbox',
            'PLAYSTATION' => 'PlayStation',
        ];
        $platformIds = Platform::query()
            ->whereIn('name', array_values($platformMap))
            ->pluck('id', 'name');

        $rows = [];
        foreach ($parsed as $entry) {
            $platformName = $platformMap[$entry['platform_key']] ?? null;
            $platformId = $platformName ? ($platformIds[$platformName] ?? null) : null;
            if (! $platformId) {
                continue;
            }

            $game = Game::query()->where('rawg_id', $entry['rawg_id'])->first();
            if (! $game) {
                continue;
            }

            $gamePlatform = GamePlatform::query()->firstOrCreate(
                ['game_id' => $game->id, 'platform_id' => $platformId],
                ['game_id' => $game->id, 'platform_id' => $platformId]
            );

            foreach ($entry['ranks'] as $index => $rankLabel) {
                $rows[] = [
                    'game_id' => $game->id,
                    'game_platform_id' => $gamePlatform->id,
                    'code' => $this->toCode($rankLabel, $index),
                    'label' => $rankLabel,
                    'order_index' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($rows)) {
            $this->command?->warn('GameRankTierSeeder: no rank rows matched existing games/platforms.');
            return;
        }

        GameRankTier::query()
            ->whereIn('game_platform_id', collect($rows)->pluck('game_platform_id')->unique()->values())
            ->delete();

        GameRankTier::query()->upsert(
            $rows,
            ['game_platform_id', 'code'],
            ['game_id', 'label', 'order_index', 'updated_at']
        );
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, array{platform_key: string, rawg_id: int, ranks: array<int, string>}>
     */
    private function parseRankSource(array $lines): array
    {
        $platformKey = null;
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(MOBILE|PC|XBOX|PLAYSTATION)\b/i', $trimmed, $m) === 1) {
                $platformKey = strtoupper($m[1]);
                continue;
            }

            if ($platformKey === null) {
                continue;
            }

            if (preg_match('/^\d+\.\s*(.+)$/', $trimmed, $m) !== 1) {
                continue;
            }

            $item = trim($m[1]);
            if (preg_match('/(\d+)\s*$/', $item, $rawg) !== 1) {
                continue;
            }
            $rawgId = (int) $rawg[1];
            if ($rawgId <= 0) {
                continue;
            }

            $withoutId = preg_replace('/\s*:?\s*\d+\s*$/', '', $item) ?? $item;
            $parts = preg_split('/\s+[–-]\s+/u', $withoutId, 2) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $rankText = trim($parts[1]);
            $ranks = preg_split('/\s*,\s*|\s*→\s*/u', $rankText) ?: [];
            $ranks = array_values(array_filter(array_map(static fn ($v) => trim($v), $ranks)));
            if (empty($ranks)) {
                continue;
            }

            $out[] = [
                'platform_key' => $platformKey,
                'rawg_id' => $rawgId,
                'ranks' => $ranks,
            ];
        }

        return $out;
    }

    private function toCode(string $label, int $index): string
    {
        $code = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '_', $label) ?? '');
        $code = trim($code, '_');
        if ($code === '') {
            $code = 'RANK_'.($index + 1);
        }
        return $code;
    }
}

