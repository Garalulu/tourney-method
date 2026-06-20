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
        // Step 1: Migrate existing 'interested' records to 'watching'
        DB::statement("UPDATE tournament_watches SET watch_type = 'watching' WHERE watch_type = 'interested'");

        // Step 2: PostgreSQL requires creating a new enum type and altering the column
        // Laravel names enums as: {$table}_{$column}_enum
        $oldEnumType = 'tournament_watches_watch_type_enum';
        $newEnumType = 'tournament_watches_watch_type_enum_new';

        DB::statement('ALTER TABLE tournament_watches RENAME COLUMN watch_type TO watch_type_old');

        DB::statement("CREATE TYPE {$newEnumType} AS ENUM ('watching', 'stream')");

        DB::statement("ALTER TABLE tournament_watches ADD COLUMN watch_type {$newEnumType} NOT NULL DEFAULT 'watching'");

        DB::statement("UPDATE tournament_watches SET watch_type = watch_type_old::text::{$newEnumType}");

        DB::statement('ALTER TABLE tournament_watches DROP COLUMN watch_type_old');

        DB::statement("DROP TYPE IF EXISTS {$oldEnumType}");

        // Rename the new enum type to match Laravel's naming convention
        DB::statement("ALTER TYPE {$newEnumType} RENAME TO {$oldEnumType}");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: Add 'interested' back to enum
        $oldEnumType = 'tournament_watches_watch_type_enum';
        $newEnumType = 'tournament_watches_watch_type_enum_old';

        DB::statement('ALTER TABLE tournament_watches RENAME COLUMN watch_type TO watch_type_new');

        DB::statement("CREATE TYPE {$newEnumType} AS ENUM ('interested', 'watching', 'stream')");

        DB::statement("ALTER TABLE tournament_watches ADD COLUMN watch_type {$newEnumType} NOT NULL DEFAULT 'watching'");

        DB::statement("UPDATE tournament_watches SET watch_type = watch_type_new::text::{$newEnumType}");

        DB::statement('ALTER TABLE tournament_watches DROP COLUMN watch_type_new');

        DB::statement("DROP TYPE IF EXISTS {$oldEnumType}");

        // Rename the old enum type to match Laravel's naming convention
        DB::statement("ALTER TYPE {$newEnumType} RENAME TO {$oldEnumType}");
    }
};
