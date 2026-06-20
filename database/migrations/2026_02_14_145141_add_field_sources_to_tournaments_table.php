<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add field_sources column to track which fields were manually edited vs parsed.
     * This enables re-parse protection by preventing overwriting manual edits.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->jsonb('field_sources')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('field_sources');
        });
    }
};
