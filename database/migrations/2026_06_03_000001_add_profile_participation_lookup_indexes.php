<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS idx_participation_shared_source ON tournament_participation_records (tournament_id, ((metadata->>'shared_from_record_id'))) WHERE jsonb_exists(metadata, 'shared_from_record_id')");
        DB::statement('CREATE INDEX IF NOT EXISTS idx_participation_delete_requests_record_status ON participation_deletion_requests (tournament_participation_record_id, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_participation_profile_user_review_visible ON tournament_participation_records (user_id, review_status, profile_hidden_at, tournament_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_participation_profile_user_review_visible');
        DB::statement('DROP INDEX IF EXISTS idx_participation_delete_requests_record_status');
        DB::statement('DROP INDEX IF EXISTS idx_participation_shared_source');
    }
};
