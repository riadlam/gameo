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
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('game_platform_id')
                ->nullable()
                ->after('target_user_id')
                ->constrained('game_platform')
                ->nullOnDelete();

            $table->index('game_platform_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['game_platform_id']);
            $table->dropConstrainedForeignId('game_platform_id');
        });
    }
};

