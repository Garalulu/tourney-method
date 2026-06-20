<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->unsignedInteger('processing_total')->default(0)->after('review_note');
            $table->unsignedInteger('processing_completed')->default(0)->after('processing_total');
        });

        DB::statement('DROP INDEX IF EXISTS tournament_corrections_one_pending_per_tournament');
        DB::statement("
            CREATE UNIQUE INDEX tournament_corrections_one_active_per_tournament
            ON tournament_corrections (tournament_id)
            WHERE status IN ('pending', 'processing')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_corrections_one_active_per_tournament');
        DB::statement("
            CREATE UNIQUE INDEX tournament_corrections_one_pending_per_tournament
            ON tournament_corrections (tournament_id)
            WHERE status = 'pending'
        ");

        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->dropColumn(['processing_total', 'processing_completed']);
        });
    }
};
