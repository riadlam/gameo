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
        Schema::create('platforms', function (Blueprint $table) {
            $table->increments('id'); // int PK
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id(); // bigint PK
            $table->string('name');
            $table->string('image')->nullable(); // image link for API usage
            $table->timestamps();
        });

        Schema::create('game_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();
            $table->unsignedInteger('platform_id');
            $table->foreign('platform_id')
                ->references('id')
                ->on('platforms')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'platform_id']);
        });

        Schema::create('user_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();
            $table->tinyInteger('skill_level'); // 1-5
            $table->integer('play_time_hours')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'game_id']);
        });

        Schema::create('user_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedInteger('platform_id');
            $table->foreign('platform_id')
                ->references('id')
                ->on('platforms')
                ->cascadeOnDelete();
            $table->string('username_on_platform')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_platforms');
        Schema::dropIfExists('user_games');
        Schema::dropIfExists('game_platform');
        Schema::dropIfExists('games');
        Schema::dropIfExists('platforms');
    }
};

