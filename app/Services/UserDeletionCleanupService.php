<?php

namespace App\Services;

use App\Models\TournamentParticipationRecord;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class UserDeletionCleanupService
{
    public function __construct(
        private ParticipationPodiumBackfillService $podiumBackfill
    ) {}

    /**
     * Remove only the deleted user's tournament presence while preserving teammate history.
     *
     * @return array<string, int>
     */
    public function cleanupBeforeDelete(User $user): array
    {
        $stats = [
            'participation_records_deleted' => 0,
            'teammate_links_removed' => 0,
            'participation_groups_repaired' => 0,
            'staff_roles_deleted' => 0,
            'podium_rows_deleted' => 0,
        ];

        $groups = $this->participationGroupsFor($user);

        foreach ($groups as $recordIds) {
            $stats['participation_groups_repaired'] += $this->repairParticipationGroup($recordIds, $user->id) ? 1 : 0;
        }

        $stats['teammate_links_removed'] += DB::table('participation_record_teammates')
            ->where('user_id', $user->id)
            ->delete();

        $stats['participation_records_deleted'] += TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->delete();

        $stats['staff_roles_deleted'] += TournamentStaff::query()
            ->where('user_id', $user->id)
            ->delete();

        $stats['podium_rows_deleted'] += $this->deletePodiumRows($user);

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function connectionCounts(User $user): array
    {
        return [
            'participation_records' => $user->tournamentParticipationRecords()->count(),
            'teammate_rosters' => DB::table('participation_record_teammates')
                ->where('user_id', $user->id)
                ->count(),
            'staff_roles' => $user->tournaments()->count(),
            'podium_rows' => $user->tournamentWinners()->count(),
            'contributions' => $user->tournamentCorrections()->count(),
            'hosted_tournaments' => $user->tournamentsHosted()->count(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    public function hasConnections(array $counts): bool
    {
        return array_sum($counts) > 0;
    }

    /**
     * @return array<int, array<int>>
     */
    private function participationGroupsFor(User $user): array
    {
        $seedIds = TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->merge(
                DB::table('participation_record_teammates')
                    ->where('user_id', $user->id)
                    ->pluck('tournament_participation_record_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $groups = [];
        $seenKeys = [];

        foreach ($seedIds as $seedId) {
            $seed = TournamentParticipationRecord::query()
                ->with('teammates:id')
                ->find($seedId);

            if (! $seed instanceof TournamentParticipationRecord) {
                continue;
            }

            $recordIds = $this->relatedRecordIds($seed, $user->id);
            sort($recordIds);

            $key = implode('-', $recordIds);
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;
            $groups[] = $recordIds;
        }

        return $groups;
    }

    /**
     * @return array<int>
     */
    private function relatedRecordIds(TournamentParticipationRecord $record, int $deletedUserId): array
    {
        $record->loadMissing('teammates:id');

        $rootId = (int) (data_get($record->metadata, 'shared_from_record_id') ?: $record->id);
        $teamUserIds = $record->teammates
            ->pluck('id')
            ->push($record->user_id)
            ->push($deletedUserId)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return TournamentParticipationRecord::query()
            ->where('tournament_id', $record->tournament_id)
            ->where(function (Builder $query) use ($record, $rootId, $teamUserIds): void {
                $query->whereKey($record->id)
                    ->orWhere('id', $rootId)
                    ->orWhereIn('user_id', $teamUserIds)
                    ->orWhere('metadata->shared_from_record_id', $record->id)
                    ->orWhere('metadata->shared_from_record_id', $rootId);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $recordIds
     */
    private function repairParticipationGroup(array $recordIds, int $deletedUserId): bool
    {
        /** @var EloquentCollection<int, TournamentParticipationRecord> $records */
        $records = TournamentParticipationRecord::query()
            ->whereIn('id', $recordIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return false;
        }

        $remaining = $records
            ->reject(fn (TournamentParticipationRecord $record): bool => $record->user_id === $deletedUserId)
            ->values();

        if ($remaining->isEmpty()) {
            return true;
        }

        $remainingUserIds = $remaining
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $newRoot = $remaining->first();

        foreach ($remaining as $record) {
            $teammateIds = $remainingUserIds
                ->reject(fn (int $userId): bool => $userId === $record->user_id)
                ->values()
                ->all();

            $record->teammates()->sync($teammateIds);
            $metadata = $record->metadata ?? [];

            if ($remaining->count() === 1 || $record->id === $newRoot->id) {
                unset($metadata['shared_from_record_id']);
            } else {
                $metadata['shared_from_record_id'] = $newRoot->id;
            }

            $record->forceFill([
                'metadata' => $metadata === [] ? null : $metadata,
            ])->save();
        }

        return true;
    }

    private function deletePodiumRows(User $user): int
    {
        $winners = TournamentWinner::query()
            ->where('user_id', $user->id)
            ->get();

        $deleted = 0;

        foreach ($winners as $winner) {
            $tournamentId = $winner->tournament_id;
            $placement = $winner->placement;
            $groupId = data_get($winner->metadata, 'podium_group_id');

            $winner->delete();
            $deleted++;

            $remainingRecord = TournamentParticipationRecord::query()
                ->where('tournament_id', $tournamentId)
                ->where(function (Builder $query) use ($placement): void {
                    $query->where('placement', $placement)
                        ->orWhere('placement_min', $placement);
                })
                ->when(is_string($groupId) && $groupId !== '', fn (Builder $query) => $query->where('metadata->podium_group_id', $groupId))
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            if ($remainingRecord instanceof TournamentParticipationRecord) {
                $this->podiumBackfill->syncRosterFromRecord($remainingRecord);
            }
        }

        return $deleted;
    }
}
