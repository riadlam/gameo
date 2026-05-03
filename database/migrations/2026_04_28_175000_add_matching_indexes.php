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
        Schema::table('users', function (Blueprint $table) {
            $table->index('region');
            $table->index('is_online');
        });

        // These pairs are already indexed through UNIQUE constraints in their
        // create-table migrations: (user_id, game_id) and (user_id, platform_id).
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropIndex(['is_online']);
        });
    }
};

