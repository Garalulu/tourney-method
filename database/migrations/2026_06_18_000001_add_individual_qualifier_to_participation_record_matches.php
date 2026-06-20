<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participation_record_matches', function (Blueprint $table): void {
            $table->boolean('is_individual_qualifier')->default(false)->after('is_forfeit');
        });

        DB::table('tournaments')
            ->select(['id', 'format_structure'])
            ->whereIn('team_formation_style', ['draft', 'auction', 'suiji'])
            ->orderBy('id')
            ->chunkById(200, function ($tournaments): void {
                foreach ($tournaments as $tournament) {
                    if ($this->hasQualifierCutoff($tournament->format_structure)) {
                        continue;
                    }

                    DB::table('participation_record_matches')
                        ->whereIn('tournament_participation_record_id', function ($query) use ($tournament): void {
                            $query->select('id')
                                ->from('tournament_participation_records')
                                ->where('tournament_id', $tournament->id);
                        })
                        ->whereRaw('LOWER(TRIM(stage)) IN (?, ?)', ['ql', 'qualifier'])
                        ->update(['is_individual_qualifier' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('participation_record_matches', function (Blueprint $table): void {
            $table->dropColumn('is_individual_qualifier');
        });
    }

    private function hasQualifierCutoff(mixed $formatStructure): bool
    {
        if (is_string($formatStructure)) {
            $formatStructure = json_decode($formatStructure, true);
        }

        if (! is_array($formatStructure)) {
            return false;
        }

        foreach ($formatStructure['stages'] ?? [] as $stage) {
            if (is_array($stage)
                && ($stage['type'] ?? null) === 'qualifier'
                && (int) ($stage['advance_count'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
};
