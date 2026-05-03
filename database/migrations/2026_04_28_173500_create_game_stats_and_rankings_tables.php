<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Per-game rank/classment catalog (e.g. Iron, Bronze, Silver for LoL).
        Schema::create('game_rank_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();
            $table->string('code'); // machine key, e.g. GOLD_IV
            $table->string('label'); // display label, e.g. Gold IV
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'code']);
        });

        // Per-game statistic definitions (each game decides its own stats).
        Schema::create('game_stat_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();
            $table->string('key'); // e.g. total_games_played, winrate
            $table->string('label'); // e.g. Total Games Played, Win Rate
            $table->enum('value_type', ['int', 'decimal', 'text', 'rank']);
            $table->string('unit')->nullable(); // e.g. %, KDA, games
            $table->boolean('is_seasonal')->default(false);
            $table->timestamps();

            $table->unique(['game_id', 'key']);
        });

        // Values of those stats per user per game (optionally per season).
        Schema::create('user_game_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();
            $table->foreignId('stat_definition_id')
                ->constrained('game_stat_definitions')
                ->cascadeOnDelete();
            $table->string('season')->nullable(); // e.g. 2026-S1

            // Flexible value fields; use the one matching value_type.
            $table->bigInteger('value_int')->nullable();
            $table->decimal('value_decimal', 12, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->foreignId('value_rank_tier_id')
                ->nullable()
                ->constrained('game_rank_tiers')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'game_id']);
            $table->index(['game_id', 'season']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_game_stats');
        Schema::dropIfExists('game_stat_definitions');
        Schema::dropIfExists('game_rank_tiers');
    }
};

