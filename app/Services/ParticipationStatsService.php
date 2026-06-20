<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ParticipationStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function summarizeForUser(User $user, bool $viewerCanSeeHiddenParticipation): array
    {
        $recordsQuery = $this->baseRecordQuery($user, $viewerCanSeeHiddenParticipation);
        $totalTournaments = (clone $recordsQuery)->count();

        $placements = (clone $recordsQuery)
            ->whereNotNull(DB::raw('coalesce(placement_override, placement)'))
            ->selectRaw('coalesce(placement_override, placement) as display_placement')
            ->pluck('display_placement')
            ->map(fn ($placement): int => (int) $placement);

        $firsts = $placements->filter(fn (int $placement): bool => $placement === 1)->count();
        $top3 = $placements->filter(fn (int $placement): bool => $placement <= 3)->count();

        $recordIds = (clone $recordsQuery)->select('tournament_participation_records.id');
        $teammateRows = DB::table('participation_record_teammates')
            ->join('users', 'users.id', '=', 'participation_record_teammates.user_id')
            ->whereIn('participation_record_teammates.tournament_participation_record_id', $recordIds)
            ->where('participation_record_teammates.user_id', '!=', $user->id)
            ->select([
                'users.id',
                'users.osu_id',
                'users.username',
                'users.country_code',
            ])
            ->selectRaw('count(*) as teammate_count')
            ->groupBy([
                'users.id',
                'users.osu_id',
                'users.username',
                'users.country_code',
            ])
            ->get();

        $ownerCountry = $user->country_code ? strtoupper($user->country_code) : null;
        $maxTeammateCount = $teammateRows->max('teammate_count');
        $mostTeamedCandidates = $maxTeammateCount
            ? $teammateRows->filter(fn (object $teammate): bool => (int) $teammate->teammate_count === (int) $maxTeammateCount)
            : collect();
        $sameCountryCandidates = $ownerCountry === null
            ? collect()
            : $mostTeamedCandidates->filter(
                fn (object $teammate): bool => strtoupper((string) $teammate->country_code) === $ownerCountry
            );

        if ($sameCountryCandidates->isNotEmpty()) {
            $mostTeamedCandidates = $sameCountryCandidates;
        }

        $candidateBwsRanks = $mostTeamedCandidates->count() > 1
            ? $this->bestBwsRanksForCandidates(
                $user,
                $mostTeamedCandidates->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $viewerCanSeeHiddenParticipation,
            )
            : [];
        $mostTeamedUser = $mostTeamedCandidates
            ->sortBy(fn (object $teammate): array => [
                $candidateBwsRanks[(int) $teammate->id] ?? PHP_INT_MAX,
                (string) $teammate->username,
            ])
            ->first();

        $countryOccurrences = $teammateRows
            ->filter(fn (object $teammate): bool => ! empty($teammate->country_code))
            ->groupBy(fn (object $teammate): string => strtoupper((string) $teammate->country_code))
            ->map(fn (Collection $countryTeammates): int => $countryTeammates->sum(
                fn (object $teammate): int => (int) $teammate->teammate_count
            ));
        $maxCountryCount = $countryOccurrences->max();
        $mostCommonCountry = $maxCountryCount
            ? $countryOccurrences
                ->filter(fn (int $count): bool => $count === $maxCountryCount)
                ->keys()
                ->sortBy(fn (string $country): array => [
                    $ownerCountry !== null && $country === $ownerCountry ? 0 : 1,
                    $country,
                ])
                ->first()
            : null;

        return [
            'total_tournaments' => $totalTournaments,
            'total_unique_teammates' => $teammateRows->count(),
            'most_teamed_user' => $mostTeamedUser ? [
                'id' => (int) $mostTeamedUser->id,
                'osu_id' => (int) $mostTeamedUser->osu_id,
                'username' => (string) $mostTeamedUser->username,
            ] : null,
            'total_unique_countries' => $teammateRows
                ->pluck('country_code')
                ->filter()
                ->map(fn (string $country): string => strtoupper($country))
                ->unique()
                ->count(),
            'most_common_country' => $mostCommonCountry,
            'common_country_teammates' => $mostCommonCountry
                ? $teammateRows->filter(fn (object $teammate): bool => strtoupper((string) $teammate->country_code) === $mostCommonCountry)->count()
                : 0,
            'win_rate' => $placements->isEmpty() ? 0 : round($firsts / $placements->count() * 100, 1),
            'top_3_rate' => $placements->isEmpty() ? 0 : round($top3 / $placements->count() * 100, 1),
            'first_placements' => $firsts,
            'second_placements' => $placements->filter(fn (int $placement): bool => $placement === 2)->count(),
            'third_placements' => $placements->filter(fn (int $placement): bool => $placement === 3)->count(),
        ];
    }

    /**
     * @param  list<int>  $candidateIds
     * @return array<int, float>
     */
    private function bestBwsRanksForCandidates(
        User $user,
        array $candidateIds,
        bool $viewerCanSeeHiddenParticipation
    ): array {
        if ($candidateIds === []) {
            return [];
        }

        /** @var Collection<int, TournamentParticipationRecord> $records */
        $records = $this->baseRecordQuery($user, $viewerCanSeeHiddenParticipation)
            ->select('tournament_participation_records.*')
            ->whereHas('teammates', fn (Builder $query) => $query->whereIn('users.id', $candidateIds))
            ->with([
                'tournament',
                'teammates' => fn ($query) => $query
                    ->whereIn('users.id', $candidateIds)
                    ->with('rankHistory'),
            ])
            ->get();

        $bwsCalculator = app(BwsCalculator::class);
        $bestRanks = [];

        foreach ($records->groupBy(function (TournamentParticipationRecord $record): string {
            $mode = $this->firstTournamentMode($record->tournament);

            return $record->tournament_id.':'.($mode ?? 'none');
        }) as $contextRecords) {
            /** @var TournamentParticipationRecord $record */
            $record = $contextRecords->first();
            $mode = $this->firstTournamentMode($record->tournament);

            if ($mode === null) {
                continue;
            }

            /** @var Collection<int, User> $contextCandidates */
            $contextCandidates = $contextRecords
                ->flatMap(fn (TournamentParticipationRecord $contextRecord): array => $contextRecord->teammates->all())
                ->unique('id')
                ->values();

            foreach ($bwsCalculator->calculateForUsers($contextCandidates, $record->tournament, $mode) as $userId => $rank) {
                if ($rank === null) {
                    continue;
                }

                $bestRanks[(int) $userId] = min($bestRanks[(int) $userId] ?? PHP_INT_MAX, $rank);
            }
        }

        return $bestRanks;
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @return array<string, mixed>
     */
    public function summarize(User $user, Collection $records): array
    {
        $bwsCalculator = app(BwsCalculator::class);
        $ownerCountry = $user->country_code ? strtoupper($user->country_code) : null;

        /** @var Collection<int, User> $allTeammates */
        $allTeammates = $records
            ->flatMap(fn (TournamentParticipationRecord $record): array => $record->teammates->all())
            ->where('id', '!=', $user->id)
            ->values();

        /** @var Collection<int, User> $teammates */
        $teammates = $allTeammates
            ->unique('id')
            ->values();

        $teammateBwsRanks = [];
        $countryBwsRanks = [];
        $countryCounts = [];

        foreach ($records as $record) {
            $mode = $this->firstTournamentMode($record->tournament);

            foreach ($record->teammates as $teammate) {
                if ($teammate->id === $user->id) {
                    continue;
                }

                if ($teammate->country_code) {
                    $country = strtoupper($teammate->country_code);
                    $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
                }

                if ($mode === null) {
                    continue;
                }

                $bwsRank = $bwsCalculator->calculateForUser($teammate, $record->tournament, $mode) ?? PHP_INT_MAX;
                $teammateBwsRanks[$teammate->id] = min($teammateBwsRanks[$teammate->id] ?? PHP_INT_MAX, $bwsRank);

                if ($teammate->country_code) {
                    $country = strtoupper($teammate->country_code);
                    $countryBwsRanks[$country] = min($countryBwsRanks[$country] ?? PHP_INT_MAX, $bwsRank);
                }
            }
        }

        $teammateCounts = $allTeammates->pluck('id')->countBy();
        $maxTeammateCount = $teammateCounts->max();
        $mostTeamedUserId = $maxTeammateCount
            ? $teammateCounts
                ->filter(fn (int $count): bool => $count === $maxTeammateCount)
                ->keys()
                ->sortBy(function (int|string $id) use ($teammates, $ownerCountry, $teammateBwsRanks): array {
                    $teammate = $teammates->first(fn (User $candidate): bool => $candidate->id === (int) $id);
                    $country = $teammate?->country_code ? strtoupper($teammate->country_code) : null;

                    return [
                        $ownerCountry !== null && $country === $ownerCountry ? 0 : 1,
                        $teammateBwsRanks[(int) $id] ?? PHP_INT_MAX,
                        $teammate->username,
                    ];
                })
                ->first()
            : null;
        $mostTeamedUser = $mostTeamedUserId
            ? $teammates->first(fn (User $teammate): bool => $teammate->id === (int) $mostTeamedUserId)
            : null;

        $countries = $teammates
            ->pluck('country_code')
            ->filter()
            ->map(fn (string $country): string => strtoupper($country))
            ->values();

        $maxCountryCount = $countryCounts === [] ? null : max($countryCounts);
        $mostCommonCountry = $maxCountryCount
            ? collect($countryCounts)
                ->filter(fn (int $count): bool => $count === $maxCountryCount)
                ->keys()
                ->sortBy(fn (string $country): array => [
                    $ownerCountry !== null && $country === $ownerCountry ? 0 : 1,
                    $countryBwsRanks[$country] ?? PHP_INT_MAX,
                    $country,
                ])
                ->first()
            : null;

        $recordsWithPlacement = $records->filter(fn (TournamentParticipationRecord $record): bool => $record->displayPlacement() !== null);
        $top3 = $recordsWithPlacement->filter(fn (TournamentParticipationRecord $record): bool => (int) $record->displayPlacement() <= 3);
        $firsts = $recordsWithPlacement->filter(fn (TournamentParticipationRecord $record): bool => (int) $record->displayPlacement() === 1);

        return [
            'total_tournaments' => $records->count(),
            'total_unique_teammates' => $teammates->count(),
            'most_teamed_user' => $mostTeamedUser ? [
                'id' => $mostTeamedUser->id,
                'osu_id' => $mostTeamedUser->osu_id,
                'username' => $mostTeamedUser->username,
            ] : null,
            'total_unique_countries' => $countries->unique()->count(),
            'most_common_country' => $mostCommonCountry,
            'common_country_teammates' => $mostCommonCountry
                ? $teammates->filter(fn (User $teammate): bool => strtoupper((string) $teammate->country_code) === $mostCommonCountry)->count()
                : 0,
            'win_rate' => $recordsWithPlacement->isEmpty() ? 0 : round($firsts->count() / $recordsWithPlacement->count() * 100, 1),
            'top_3_rate' => $recordsWithPlacement->isEmpty() ? 0 : round($top3->count() / $recordsWithPlacement->count() * 100, 1),
            'first_placements' => $firsts->count(),
            'second_placements' => $recordsWithPlacement->filter(fn (TournamentParticipationRecord $record): bool => (int) $record->displayPlacement() === 2)->count(),
            'third_placements' => $recordsWithPlacement->filter(fn (TournamentParticipationRecord $record): bool => (int) $record->displayPlacement() === 3)->count(),
        ];
    }

    private function firstTournamentMode(Tournament $tournament): ?string
    {
        return collect($tournament->modes)
            ->map(fn ($mode): ?string => data_get($mode, 'mode', $mode))
            ->filter()
            ->first();
    }

    /**
     * @return Builder<TournamentParticipationRecord>
     */
    private function baseRecordQuery(User $user, bool $viewerCanSeeHiddenParticipation): Builder
    {
        /** @var Builder<TournamentParticipationRecord> $query */
        $query = TournamentParticipationRecord::query()
            ->join('tournaments as participation_tournaments', 'participation_tournaments.id', '=', 'tournament_participation_records.tournament_id')
            ->where('tournament_participation_records.user_id', $user->id)
            ->where('participation_tournaments.status', 'approved');

        if (! $viewerCanSeeHiddenParticipation) {
            $query
                ->whereNull('tournament_participation_records.profile_hidden_at')
                ->where('tournament_participation_records.review_status', '!=', TournamentParticipationRecord::REVIEW_PENDING);
        }

        return $query;
    }
}
