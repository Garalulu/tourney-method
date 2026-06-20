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
        DB::table('bws_exclusion_patterns')->insert([
            'pattern' => 'nominators',
            'match_type' => 'contains',
            'description' => 'Exclude NAT (Nomination Assessment Team) badges',
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bws_exclusion_patterns')
            ->where('pattern', 'nominators')
            ->delete();
    }
};
