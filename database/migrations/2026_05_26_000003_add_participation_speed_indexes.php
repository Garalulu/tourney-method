<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_previous_usernames_trgm ON users USING gin ((previous_usernames::text) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tournaments_status_end_desc ON tournaments (status, tournament_end DESC NULLS LAST)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_participation_visible_user_tournament ON tournament_participation_records (user_id, tournament_id) WHERE profile_hidden_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_participation_visible_user_tournament');
        DB::statement('DROP INDEX IF EXISTS idx_tournaments_status_end_desc');
        DB::statement('DROP INDEX IF EXISTS idx_users_previous_usernames_trgm');
    }
};
