<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasGamePlatformColumn = Schema::hasColumn('game_rank_tiers', 'game_platform_id');
        $hasGameIdIndex = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_id_index'"
        ));
        $hasOldUnique = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_id_code_unique'"
        ));
        $hasNewUnique = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_platform_id_code_unique'"
        ));

        Schema::table('game_rank_tiers', function (Blueprint $table) use (
            $hasGamePlatformColumn,
            $hasGameIdIndex,
            $hasOldUnique,
            $hasNewUnique
        ) {
            // Keep an index for the existing game_id FK before dropping the
            // old unique(game_id, code), otherwise MySQL may reject the drop.
            if (! $hasGameIdIndex) {
                $table->index('game_id');
            }

            if (! $hasGamePlatformColumn) {
                $table->foreignId('game_platform_id')
                    ->nullable()
                    ->after('game_id')
                    ->constrained('game_platform')
                    ->cascadeOnDelete();
            }

            if ($hasOldUnique) {
                $table->dropUnique(['game_id', 'code']);
            }
            if (! $hasNewUnique) {
                $table->unique(['game_platform_id', 'code']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasGamePlatformColumn = Schema::hasColumn('game_rank_tiers', 'game_platform_id');
        $hasGameIdIndex = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_id_index'"
        ));
        $hasOldUnique = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_id_code_unique'"
        ));
        $hasNewUnique = ! empty(DB::select(
            "SHOW INDEX FROM `game_rank_tiers` WHERE Key_name = 'game_rank_tiers_game_platform_id_code_unique'"
        ));

        Schema::table('game_rank_tiers', function (Blueprint $table) use (
            $hasGamePlatformColumn,
            $hasGameIdIndex,
            $hasOldUnique,
            $hasNewUnique
        ) {
            if ($hasNewUnique) {
                $table->dropUnique(['game_platform_id', 'code']);
            }
            if ($hasGamePlatformColumn) {
                $table->dropConstrainedForeignId('game_platform_id');
            }
            if (! $hasOldUnique) {
                $table->unique(['game_id', 'code']);
            }
            if ($hasGameIdIndex) {
                $table->dropIndex(['game_id']);
            }
        });
    }
};

