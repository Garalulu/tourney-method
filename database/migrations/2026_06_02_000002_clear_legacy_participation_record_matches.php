<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournament_participation_records', 'matches')) {
            return;
        }

        DB::table('tournament_participation_records')
            ->whereNotNull('matches')
            ->update(['matches' => null]);
    }

    public function down(): void
    {
        // Legacy match JSON is retired and cannot be reconstructed from this cleanup.
    }
};
