<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL refuses to drop a unique index if InnoDB still needs it for an
     * existing foreign key on those columns. Drop child FKs first, then the
     * unique, then add game_platform_id + new unique, then restore FKs with
     * a dedicated index on platform_id (left-prefix for platform_id FK).
     */
    public function up(): void
    {
        Schema::table('user_platforms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['platform_id']);
        });

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->dropUnique('user_platforms_user_id_platform_id_unique');
        });

        if (! Schema::hasColumn('user_platforms', 'game_platform_id')) {
            Schema::table('user_platforms', function (Blueprint $table) {
                $table->foreignId('game_platform_id')
                    ->nullable()
                    ->after('platform_id')
                    ->constrained('game_platform')
                    ->nullOnDelete();
            });
        }

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->unique(['user_id', 'game_platform_id']);
            $table->index('platform_id');
        });

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('platform_id')
                ->references('id')
                ->on('platforms')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_platforms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['platform_id']);
        });

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->dropUnique('user_platforms_user_id_game_platform_id_unique');
            $table->dropIndex(['platform_id']);
        });

        if (Schema::hasColumn('user_platforms', 'game_platform_id')) {
            Schema::table('user_platforms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('game_platform_id');
            });
        }

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->unique(['user_id', 'platform_id']);
        });

        Schema::table('user_platforms', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('platform_id')
                ->references('id')
                ->on('platforms')
                ->cascadeOnDelete();
        });
    }
};
