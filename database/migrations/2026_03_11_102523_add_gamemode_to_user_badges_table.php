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
        Schema::table('user_badges', function (Blueprint $table) {
            // Add gamemode column after user_id
            $table->string('gamemode', 10)->after('user_id')->default('osu');

            // Add tournament_id for badge -> tournament linking
            $table->foreignId('tournament_id')->nullable()->after('gamemode');

            // Add composite index for fast gamemode filtering
            $table->index(['user_id', 'gamemode'], 'idx_user_badges_gamemode');

            // Add index for tournament lookups
            $table->index('tournament_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropIndex('idx_user_badges_gamemode');
            $table->dropIndex('tournament_id'); // Will be dropped as 'user_badges_tournament_id_index'
            $table->dropColumn(['gamemode', 'tournament_id']);
        });
    }
};
