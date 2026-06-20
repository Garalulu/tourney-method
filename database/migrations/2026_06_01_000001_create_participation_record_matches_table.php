<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participation_record_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_participation_record_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('stage', 80)->nullable();
            $table->string('result', 40)->nullable();
            $table->integer('score_for')->nullable();
            $table->integer('score_against')->nullable();
            $table->string('mp_link')->nullable();
            $table->unsignedBigInteger('mp_id')->nullable();
            $table->boolean('is_forfeit')->default(false);
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index('tournament_participation_record_id', 'participation_matches_record_index');
            $table->index(['tournament_participation_record_id', 'position'], 'participation_matches_record_position_index');
            $table->index('mp_id', 'participation_matches_mp_id_index');
        });

        $this->backfillFromLegacyJson();
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_record_matches');
    }

    private function backfillFromLegacyJson(): void
    {
        DB::table('tournament_participation_records')
            ->select(['id', 'user_id', 'tournament_id', 'matches'])
            ->whereNotNull('matches')
            ->orderBy('id')
            ->chunkById(200, function ($records): void {
                foreach ($records as $record) {
                    $matches = is_string($record->matches)
                        ? json_decode($record->matches, true)
                        : $record->matches;
                    if (! is_array($matches)) {
                        continue;
                    }

                    $rows = [];
                    $now = now();

                    foreach (array_values($matches) as $position => $match) {
                        if (! is_array($match)) {
                            continue;
                        }

                        $mpId = $this->mpId($match['mp_id'] ?? null, $match['mp_link'] ?? null);

                        $rows[] = [
                            'tournament_participation_record_id' => $record->id,
                            'position' => $position,
                            'stage' => $this->stringOrNull($match['stage'] ?? null),
                            'result' => $this->stringOrNull($match['result'] ?? null),
                            'score_for' => $this->integerOrNull($match['score_for'] ?? null),
                            'score_against' => $this->integerOrNull($match['score_against'] ?? null),
                            'mp_link' => $mpId !== null ? $this->canonicalLink($mpId) : $this->stringOrNull($match['mp_link'] ?? null),
                            'mp_id' => $mpId,
                            'is_forfeit' => (bool) ($match['is_forfeit'] ?? false),
                            'note' => $this->stringOrNull($match['note'] ?? null),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('participation_record_matches')->insert($chunk);
                    }
                }
            });
    }

    private function canonicalLink(int $mpId): string
    {
        return "https://osu.ppy.sh/community/matches/{$mpId}";
    }

    private function mpId(mixed $mpId, mixed $mpLink = null): ?int
    {
        if ($mpId !== null && $mpId !== '' && is_numeric($mpId)) {
            return (int) $mpId;
        }

        if (! is_string($mpLink)) {
            return null;
        }

        $mpLink = trim($mpLink);
        if (ctype_digit($mpLink)) {
            return (int) $mpLink;
        }

        if (preg_match('~^https?://osu\.ppy\.sh/(?:community/matches|mp)/(\d+)$~', $mpLink, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
};
