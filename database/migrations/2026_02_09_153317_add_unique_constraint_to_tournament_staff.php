<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds unique constraint on (tournament_id, user_id, role) to prevent
     * duplicate role assignments while allowing multiple roles per user.
     */
    public function up(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            // Drop existing non-unique index
            $table->dropIndex(['tournament_id', 'user_id', 'role']);

            // Add unique constraint to prevent duplicate (tournament_id, user_id, role) entries
            $table->unique(['tournament_id', 'user_id', 'role'], 'unique_tournament_user_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique('unique_tournament_user_role');

            // Restore original non-unique index
            $table->index(['tournament_id', 'user_id', 'role']);
        });
    }
};
