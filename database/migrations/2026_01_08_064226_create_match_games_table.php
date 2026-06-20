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
        Schema::create('match_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->bigInteger('game_id');
            $table->bigInteger('beatmap_id');
            $table->string('beatmap_title');
            $table->string('beatmap_version');
            $table->json('mods')->nullable();
            $table->string('mode');
            $table->string('scoring_type');
            $table->string('team_type');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('match_id');
            $table->index('beatmap_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_games');
    }
};
