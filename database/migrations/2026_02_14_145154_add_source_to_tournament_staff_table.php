<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add source column to track if staff role was manually added or parsed.
     * This enables re-parse protection by preventing overwriting manual staff additions.
     */
    public function up(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->enum('source', ['manual', 'parsed'])->default('manual')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
