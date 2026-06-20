<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tournament_participation_records', 'matches')) {
            Schema::table('tournament_participation_records', function (Blueprint $table): void {
                $table->dropColumn('matches');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tournament_participation_records', 'matches')) {
            Schema::table('tournament_participation_records', function (Blueprint $table): void {
                $table->jsonb('matches')->nullable()->after('memo');
            });
        }
    }
};
