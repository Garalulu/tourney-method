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
        // Change banner_url from varchar(500) to TEXT to support long URLs (Google Drive, etc.)
        DB::statement('ALTER TABLE tournaments ALTER COLUMN banner_url TYPE TEXT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to varchar(500)
        DB::statement('ALTER TABLE tournaments ALTER COLUMN banner_url TYPE varchar(500)');
    }
};
