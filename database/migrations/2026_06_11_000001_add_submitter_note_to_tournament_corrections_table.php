<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->text('submitter_note')->nullable()->after('current_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_corrections', function (Blueprint $table) {
            $table->dropColumn('submitter_note');
        });
    }
};
