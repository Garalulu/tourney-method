<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Retired: participation results are stored directly on participation_record_matches.
        // A later cleanup migration drops match_id for databases where this migration already ran.
    }

    public function down(): void
    {
        // No-op.
    }
};
