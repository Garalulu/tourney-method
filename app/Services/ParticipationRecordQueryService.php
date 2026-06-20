<?php

namespace App\Services;

use App\Models\ParticipationDeletionRequest;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\User as UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ParticipationRecordQueryService
{
    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    public function recordsFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request = null): Collection
    {
        return $this->summaryRecordsFor($user, $viewerCanSeeHiddenParticipation, $request);
    }

    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    public function summaryRecordsFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request = null): Collection
    {
        return $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation, $request)
            ->with(['tournament', 'teammates.rankHistory'])
            ->get();
    }

    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    public function pageFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request = null, int $limit = 10, int $offset = 0): Collection
    {
        /** @var Collection<int, TournamentParticipationRecord> $records */
        $records = $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation, $request)
            ->with([
                'tournament',
                'teammates.rankHistory',
                'deletionRequests' => fn ($query) => $query->where('status', ParticipationDeletionRequest::STATUS_PENDING),
                'participationMatches',
            ])
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $records;
    }

    public function countFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request = null): int
    {
        return (clone $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation, $request))
            ->toBase()
            ->getCountForPagination();
    }

    public function hasMoreFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request, int $nextOffset): bool
    {
        return $this->countFor($user, $viewerCanSeeHiddenParticipation, $request) > $nextOffset;
    }

    /**
     * @return Collection<int, int>
     */
    public function availableYearsFor(User $user, bool $viewerCanSeeHiddenParticipation): Collection
    {
        return $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation)
            ->reorder()
            ->whereNotNull('participation_tournaments.tournament_end')
            ->select(DB::raw('distinct extract(year from participation_tournaments.tournament_end)::int as year'))
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function availableModesFor(User $user, bool $viewerCanSeeHiddenParticipation): Collection
    {
        $modeOrder = ['osu' => 0, 'taiko' => 1, 'fruits' => 2, 'catch' => 2, 'mania' => 3];
        $recordsTable = (clone $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation))
            ->reorder()
            ->toBase();

        return DB::query()
            ->fromSub($recordsTable, 'profile_records')
            ->join('tournaments as mode_tournaments', 'mode_tournaments.id', '=', 'profile_records.tournament_id')
            ->selectRaw("distinct coalesce(mode_elem->>'mode', mode_elem #>> '{}') as mode")
            ->crossJoin(DB::raw('jsonb_array_elements(mode_tournaments.modes::jsonb) as mode_elem'))
            ->whereRaw("coalesce(mode_elem->>'mode', mode_elem #>> '{}') <> ''")
            ->pluck('mode')
            ->filter()
            ->unique()
            ->sortBy(fn (string $mode): int => $modeOrder[$mode] ?? 99)
            ->values();
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @return Collection<int, int>
     */
    public function globalIndicesFor(Collection $records, User $user, bool $viewerCanSeeHiddenParticipation): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $ids = $records->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $orderedRecords = (clone $this->baseQueryFor($user, $viewerCanSeeHiddenParticipation))
            ->reorder()
            ->select([
                'tournament_participation_records.id',
                'participation_tournaments.tournament_end',
            ])
            ->orderByRaw('participation_tournaments.tournament_end ASC NULLS LAST')
            ->orderBy('tournament_participation_records.id')
            ->toBase();

        $rankedRecords = DB::query()
            ->fromSub($orderedRecords, 'ordered_records')
            ->selectRaw('id, row_number() over (order by tournament_end asc nulls last, id) as participation_index');

        return DB::query()
            ->fromSub($rankedRecords, 'ranked_records')
            ->whereIn('id', $ids)
            ->pluck('participation_index', 'id')
            ->map(fn ($index): int => (int) $index);
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @return Collection<int, Collection<int, UserModel>>
     */
    public function displayTeammatesForRecords(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $displayUserIdsByRecord = [];
        $directUserIds = [];

        foreach ($records as $record) {
            $teammateIds = $record->relationLoaded('teammates')
                ? $record->teammates->pluck('id')->map(fn ($id): int => (int) $id)->values()
                : collect();

            if ($teammateIds->isNotEmpty()) {
                $displayUserIdsByRecord[$record->id] = $teammateIds->all();
                $directUserIds = array_merge($directUserIds, $teammateIds->all());
            }
        }

        $recordsWithoutDirectTeammates = $records
            ->reject(fn (TournamentParticipationRecord $record): bool => array_key_exists($record->id, $displayUserIdsByRecord));

        if ($recordsWithoutDirectTeammates->isNotEmpty()) {
            $sharedRows = TournamentParticipationRecord::query()
                ->select(['metadata', 'user_id'])
                ->whereIn('tournament_id', $recordsWithoutDirectTeammates->pluck('tournament_id')->unique()->all())
                ->whereIn('metadata->shared_from_record_id', $recordsWithoutDirectTeammates->pluck('id')->map(fn ($id): string => (string) $id)->all())
                ->get();

            foreach ($sharedRows as $sharedRecord) {
                $sourceId = (int) data_get($sharedRecord->metadata, 'shared_from_record_id');
                $sourceRecord = $recordsWithoutDirectTeammates->first(fn (TournamentParticipationRecord $record): bool => $record->id === $sourceId);
                $sharedUserId = (int) $sharedRecord->user_id;

                if (! $sourceRecord instanceof TournamentParticipationRecord || $sharedUserId === (int) $sourceRecord->user_id) {
                    continue;
                }

                $displayUserIdsByRecord[$sourceId] ??= [];
                $displayUserIdsByRecord[$sourceId][] = $sharedUserId;
                $directUserIds[] = $sharedUserId;
            }
        }

        /** @var Collection<int, UserModel> $usersById */
        $usersById = UserModel::query()
            ->with('rankHistory')
            ->whereIn('id', collect($directUserIds)->unique()->values()->all())
            ->get()
            ->keyBy('id');

        return collect($displayUserIdsByRecord)
            ->map(function (array $userIds) use ($usersById): Collection {
                /** @var Collection<int, UserModel> $users */
                $users = collect($userIds)
                    ->unique()
                    ->map(function (int $userId) use ($usersById): ?UserModel {
                        $user = $usersById->get($userId);

                        return $user instanceof UserModel ? $user : null;
                    })
                    ->filter(fn (?UserModel $user): bool => $user instanceof UserModel)
                    ->values();

                return $users;
            });
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @param  Collection<int, Collection<int, UserModel>>  $displayTeammatesByRecord
     * @return Collection<int, array<int, float|null>>
     */
    public function displayTeammateBwsRanksForRecords(
        Collection $records,
        Collection $displayTeammatesByRecord
    ): Collection {
        if ($records->isEmpty() || $displayTeammatesByRecord->isEmpty()) {
            return collect();
        }

        $bwsCalculator = app(BwsCalculator::class);
        $ranksByRecord = collect();

        $records
            ->groupBy(function (TournamentParticipationRecord $record): string {
                $mode = collect($record->tournament->modes)
                    ->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))
                    ->filter()
                    ->first();

                return $record->tournament_id.':'.($mode ?? 'none');
            })
            ->each(function (Collection $contextRecords) use (
                $displayTeammatesByRecord,
                $bwsCalculator,
                $ranksByRecord
            ): void {
                /** @var TournamentParticipationRecord $record */
                $record = $contextRecords->first();
                $mode = collect($record->tournament->modes)
                    ->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))
                    ->filter()
                    ->first();

                if ($mode === null) {
                    return;
                }

                /** @var Collection<int, UserModel> $users */
                $users = $contextRecords
                    ->flatMap(fn (TournamentParticipationRecord $contextRecord): array => $displayTeammatesByRecord
                        ->get($contextRecord->id, collect())
                        ->all())
                    ->unique('id')
                    ->values();
                $contextRanks = $bwsCalculator->calculateForUsers($users, $record->tournament, $mode);

                foreach ($contextRecords as $contextRecord) {
                    $ranksByRecord->put(
                        $contextRecord->id,
                        $displayTeammatesByRecord
                            ->get($contextRecord->id, collect())
                            ->mapWithKeys(fn (UserModel $teammate): array => [
                                $teammate->id => $contextRanks[$teammate->id] ?? null,
                            ])
                            ->all()
                    );
                }
            });

        return $ranksByRecord;
    }

    /**
     * @return Builder<TournamentParticipationRecord>
     */
    public function baseQueryFor(User $user, bool $viewerCanSeeHiddenParticipation, ?Request $request = null): Builder
    {
        $query = TournamentParticipationRecord::query();
        $query->select('tournament_participation_records.*');
        $query->join('tournaments as participation_tournaments', 'participation_tournaments.id', '=', 'tournament_participation_records.tournament_id');
        $query->where('tournament_participation_records.user_id', $user->id);
        $query->where('participation_tournaments.status', 'approved');

        if (! $viewerCanSeeHiddenParticipation) {
            $query
                ->whereNull('tournament_participation_records.profile_hidden_at')
                ->where('tournament_participation_records.review_status', '!=', TournamentParticipationRecord::REVIEW_PENDING);
        }

        if ($request instanceof Request) {
            $this->applyFilters($query, $request);
        }

        $query->orderByRaw(
            'case when participation_tournaments.tournament_end is not null and participation_tournaments.tournament_end < ? then 1 else 0 end',
            [now()]
        );
        $query->orderByRaw('participation_tournaments.tournament_end DESC NULLS FIRST');
        $query->orderBy('tournament_participation_records.id');

        return $query;
    }

    /**
     * @param  Builder<TournamentParticipationRecord>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        $year = trim((string) $request->query('year', ''));
        $tournamentId = $request->integer('participation_tournament');
        $badgedOnly = $request->boolean('badged');
        $mode = trim((string) $request->query('mode', ''));

        $query
            ->when($tournamentId > 0, fn (Builder $query) => $query->where('tournament_participation_records.tournament_id', $tournamentId))
            ->when($badgedOnly, fn (Builder $query) => $query
                ->where('participation_tournaments.is_badge', true)
                ->where('participation_tournaments.badge_status', 'approved'))
            ->when($year !== '', fn (Builder $query) => $query->whereYear('participation_tournaments.tournament_end', (int) $year))
            ->when($mode !== '', fn (Builder $query) => $query->whereRaw(
                "exists (
                    select 1
                    from jsonb_array_elements(participation_tournaments.modes::jsonb) as mode_elem
                    where coalesce(mode_elem->>'mode', mode_elem #>> '{}') = ?
                )",
                [$mode]
            ))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = "%{$search}%";

                $query->where(function (Builder $query) use ($needle): void {
                    $query->where('participation_tournaments.title', 'ilike', $needle)
                        ->orWhere('tournament_participation_records.team_name', 'ilike', $needle)
                        ->orWhereHas('teammates', fn (Builder $query) => $query->where('username', 'ilike', $needle));
                });
            });
    }
}
