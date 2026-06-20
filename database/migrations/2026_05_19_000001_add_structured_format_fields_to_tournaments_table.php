<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('team_formation_style', 30)->default('standard')->after('format');
            $table->jsonb('format_tags')->nullable()->after('team_formation_style');
            $table->jsonb('format_structure')->nullable()->after('format_tags');

            $table->index('team_formation_style', 'tournaments_team_formation_style_index');
        });

        DB::table('tournaments')
            ->select(['id', 'format', 'start_round_size'])
            ->chunkById(200, function ($tournaments): void {
                foreach ($tournaments as $tournament) {
                    $format = trim((string) ($tournament->format ?? ''));
                    $normalizedFormat = strtolower($format);

                    $tags = [];
                    if (str_contains($normalizedFormat, 'battle royale')) {
                        $tags[] = 'battle_royale';
                    }

                    $stage = [
                        'type' => match (true) {
                            str_contains($normalizedFormat, 'battle royale') => 'battle_royale',
                            str_contains($normalizedFormat, 'swiss') => 'swiss_round',
                            default => 'bracket',
                        },
                    ];

                    if ($stage['type'] === 'bracket') {
                        $stage['entry_type'] = 'winner_only';
                        $stage['elimination_type'] = 'double_elimination';

                        if ($tournament->start_round_size !== null) {
                            $stage['start_round_size'] = (int) $tournament->start_round_size;
                        }
                    } else {
                        $stage['name'] = 'Battle Royale';
                    }

                    if ($format !== '') {
                        $stage['legacy_format'] = in_array($normalizedFormat, ['round robin', 'stage-based'], true)
                            ? 'Double Elimination'
                            : $format;
                    }

                    DB::table('tournaments')
                        ->where('id', $tournament->id)
                        ->update([
                            'team_formation_style' => 'standard',
                            'format_tags' => json_encode(array_values(array_unique($tags))),
                            'format_structure' => json_encode([
                                'stages' => [$stage],
                                'legacy_format' => $format !== '' ? $format : null,
                            ]),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('tournaments_team_formation_style_index');
            $table->dropColumn([
                'team_formation_style',
                'format_tags',
                'format_structure',
            ]);
        });
    }
};
