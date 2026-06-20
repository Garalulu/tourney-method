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
        Schema::table('tournament_winners', function (Blueprint $table) {
            // No changes needed - gamemode column already exists and is correct
            // Tournament badges should always have gamemode from tournament.modes

            // Add index for performance
            $table->index(['tournament_id', 'gamemode'], 'idx_tournament_winners_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_winners', function (Blueprint $table) {
            $table->dropIndex('idx_tournament_winners_mode');
        });
    }
};
