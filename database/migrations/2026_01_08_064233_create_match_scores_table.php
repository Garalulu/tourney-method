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
        Schema::create('match_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_game_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->bigInteger('osu_user_id');
            $table->string('username')->nullable(); // Nullable for historical imports without user data
            $table->string('team')->nullable();
            $table->bigInteger('score');
            $table->decimal('accuracy', 6, 4);
            $table->integer('max_combo');
            $table->integer('count_300')->nullable(); // Nullable for historical imports
            $table->integer('count_100')->nullable();
            $table->integer('count_50')->nullable();
            $table->integer('count_miss')->nullable();
            $table->integer('count_geki')->nullable();
            $table->integer('count_katu')->nullable();
            $table->boolean('perfect')->default(false);
            $table->boolean('passed')->default(true);
            $table->json('mods')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('match_game_id');
            $table->index('user_id');
            $table->index('osu_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_scores');
    }
};
