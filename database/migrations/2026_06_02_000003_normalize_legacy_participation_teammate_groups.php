<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            /** @var Collection<int, Collection<int, object{id: int, user_id: int, tournament_id: int, source: string|null, metadata: mixed}>> $recordsByTournament */
            $recordsByTournament = DB::table('tournament_participation_records')
                ->select(['id', 'user_id', 'tournament_id', 'source', 'metadata'])
                ->orderBy('id')
                ->get()
                ->groupBy('tournament_id');

            /** @var Collection<int, Collection<int, object{tournament_participation_record_id: int, user_id: int}>> $teammatesByRecord */
            $teammatesByRecord = DB::table('participation_record_teammates')
                ->select(['tournament_participation_record_id', 'user_id'])
                ->get()
                ->groupBy('tournament_participation_record_id');

            foreach ($recordsByTournament as $records) {
                $this->normalizeTournamentRecords($records, $teammatesByRecord);
            }
        });
    }

    public function down(): void
    {
        // Legacy teammate shapes cannot be reconstructed once normalized.
    }

    /**
     * @param  Collection<int, object{id: int, user_id: int, tournament_id: int, source: string|null, metadata: mixed}>  $records
     * @param  Collection<int, Collection<int, object{tournament_participation_record_id: int, user_id: int}>>  $teammatesByRecord
     */
    private function normalizeTournamentRecords(Collection $records, Collection $teammatesByRecord): void
    {
        $recordsById = $records->keyBy('id');
        $recordIdByUserId = $records->pluck('id', 'user_id');
        $adjacentRecordIds = [];

        foreach ($records as $record) {
            $adjacentRecordIds[$record->id] ??= [];
            $metadata = $this->metadataArray($record->metadata);
            $rootId = (int) ($metadata['shared_from_record_id'] ?? 0);

            if ($rootId > 0 && $recordsById->has($rootId)) {
                $adjacentRecordIds[$record->id][$rootId] = true;
                $adjacentRecordIds[$rootId][$record->id] = true;
            }

            foreach ($teammatesByRecord->get($record->id, collect()) as $teammate) {
                $teammateRecordId = (int) ($recordIdByUserId->get($teammate->user_id) ?? 0);
                if ($teammateRecordId <= 0) {
                    continue;
                }

                $adjacentRecordIds[$record->id][$teammateRecordId] = true;
                $adjacentRecordIds[$teammateRecordId][$record->id] = true;
            }
        }

        $visited = [];
        foreach ($records as $record) {
            if (isset($visited[$record->id])) {
                continue;
            }

            $componentIds = $this->componentIds($record->id, $adjacentRecordIds, $visited);
            if (count($componentIds) < 2) {
                continue;
            }

            $component = $recordsById->only($componentIds)->values();
            $root = $this->canonicalRoot($component);
            $userIds = $component
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            foreach ($component as $componentRecord) {
                $metadata = $this->metadataArray($componentRecord->metadata);

                if ((int) $componentRecord->id === (int) $root->id) {
                    unset($metadata['shared_from_record_id']);

                    DB::table('tournament_participation_records')
                        ->where('id', $componentRecord->id)
                        ->update(['metadata' => $metadata === [] ? null : json_encode($metadata)]);
                } else {
                    $metadata['shared_from_record_id'] = (int) $root->id;

                    DB::table('tournament_participation_records')
                        ->where('id', $componentRecord->id)
                        ->update([
                            'source' => 'manual_shared',
                            'metadata' => json_encode($metadata),
                        ]);
                }

                $this->syncRoster($componentRecord->id, $userIds->reject(
                    fn (int $userId): bool => $userId === (int) $componentRecord->user_id
                )->values());
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $adjacentRecordIds
     * @param  array<int, bool>  $visited
     * @return list<int>
     */
    private function componentIds(int $startRecordId, array $adjacentRecordIds, array &$visited): array
    {
        $componentIds = [];
        $queue = [$startRecordId];

        while ($queue !== []) {
            $recordId = array_shift($queue);
            if (isset($visited[$recordId])) {
                continue;
            }

            $visited[$recordId] = true;
            $componentIds[] = $recordId;

            foreach (array_keys($adjacentRecordIds[$recordId] ?? []) as $nextRecordId) {
                if (! isset($visited[$nextRecordId])) {
                    $queue[] = (int) $nextRecordId;
                }
            }
        }

        return $componentIds;
    }

    /**
     * @param  Collection<int, object{id: int, user_id: int, tournament_id: int, source: string|null, metadata: mixed}>  $component
     * @return object{id: int, user_id: int, tournament_id: int, source: string|null, metadata: mixed}
     */
    private function canonicalRoot(Collection $component): object
    {
        $componentIds = $component->pluck('id')->map(fn ($id): int => (int) $id);
        $referencedRootId = $component
            ->map(fn (object $record): int => (int) (($this->metadataArray($record->metadata))['shared_from_record_id'] ?? 0))
            ->first(fn (int $rootId): bool => $rootId > 0 && $componentIds->contains($rootId));

        if ($referencedRootId) {
            $root = $component->firstWhere('id', $referencedRootId);
            if (is_object($root)) {
                return $root;
            }
        }

        $root = $component
            ->filter(fn (object $record): bool => $record->source !== 'manual_shared')
            ->sortBy('id')
            ->first();

        return is_object($root)
            ? $root
            : $component->sortBy('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataArray(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  Collection<int, int>  $teammateUserIds
     */
    private function syncRoster(int $recordId, Collection $teammateUserIds): void
    {
        DB::table('participation_record_teammates')
            ->where('tournament_participation_record_id', $recordId)
            ->delete();

        if ($teammateUserIds->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('participation_record_teammates')->insert(
            $teammateUserIds
                ->map(fn (int $userId): array => [
                    'tournament_participation_record_id' => $recordId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }
};
