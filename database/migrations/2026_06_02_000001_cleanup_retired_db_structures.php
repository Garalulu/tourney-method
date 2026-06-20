<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('participation_record_matches') && Schema::hasColumn('participation_record_matches', 'match_id')) {
            Schema::table('participation_record_matches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('match_id');
            });
        }

        Schema::dropIfExists('bws_exclusion_patterns');

        if ($this->indexExists('tournament_winners', 'idx_tournament_winners_mode')) {
            Schema::table('tournament_winners', function (Blueprint $table) {
                $table->dropIndex('idx_tournament_winners_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('participation_record_matches') && ! Schema::hasColumn('participation_record_matches', 'match_id')) {
            Schema::table('participation_record_matches', function (Blueprint $table) {
                $table->foreignId('match_id')
                    ->nullable()
                    ->after('tournament_participation_record_id')
                    ->constrained('matches')
                    ->nullOnDelete();

                $table->index('match_id', 'participation_matches_match_index');
            });
        }

        if (! Schema::hasTable('bws_exclusion_patterns')) {
            Schema::create('bws_exclusion_patterns', function (Blueprint $table) {
                $table->id();
                $table->string('pattern');
                $table->string('match_type')->default('contains');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();

                $table->index('is_active');
                $table->index('match_type');
            });
        }

        if (Schema::hasTable('tournament_winners') && ! $this->indexExists('tournament_winners', 'idx_tournament_winners_mode')) {
            Schema::table('tournament_winners', function (Blueprint $table) {
                $table->index(['tournament_id', 'gamemode'], 'idx_tournament_winners_mode');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->exists();
    }
};
