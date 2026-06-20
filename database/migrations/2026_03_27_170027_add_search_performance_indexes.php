<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pg_trgm extension for trigram-based ILIKE searches
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Add GIN indexes for fast ILIKE searches on tournaments
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tournaments_title_trgm ON tournaments USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tournaments_host_username_trgm ON tournaments USING gin (host_username gin_trgm_ops)');

        // Add GIN index for fast ILIKE searches on users
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_username_trgm ON users USING gin (username gin_trgm_ops)');

        // Add GIN index for fast ILIKE searches on tournament winners
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tournament_winners_username_trgm ON tournament_winners USING gin (username gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the indexes
        DB::statement('DROP INDEX IF EXISTS idx_tournaments_title_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_tournaments_host_username_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_username_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_tournament_winners_username_trgm');

        // Note: We don't drop the pg_trgm extension as other parts of the app might use it
    }
};
