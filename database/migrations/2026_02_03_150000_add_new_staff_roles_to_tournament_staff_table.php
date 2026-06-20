<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add new staff roles: mappooler, playtester, gfx, sheeter
     * Updated role enum now has 10 values instead of 6
     */
    public function up(): void
    {
        // PostgreSQL requires dropping the constraint before recreating it
        DB::statement('
            ALTER TABLE tournament_staff
            DROP CONSTRAINT IF EXISTS tournament_staff_role_check
        ');

        DB::statement('
            ALTER TABLE tournament_staff
            ADD CONSTRAINT tournament_staff_role_check
            CHECK (role IN (
                \'organizer\',
                \'mapper\',
                \'mappooler\',
                \'referee\',
                \'streamer\',
                \'commentator\',
                \'playtester\',
                \'gfx\',
                \'sheeter\',
                \'other\'
            ))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE tournament_staff
            DROP CONSTRAINT IF EXISTS tournament_staff_role_check
        ');

        DB::statement('
            ALTER TABLE tournament_staff
            ADD CONSTRAINT tournament_staff_role_check
            CHECK (role IN (
                \'organizer\',
                \'mapper\',
                \'referee\',
                \'streamer\',
                \'commentator\',
                \'other\'
            ))
        ');
    }
};
