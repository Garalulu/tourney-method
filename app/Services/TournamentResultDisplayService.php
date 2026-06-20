<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Support\Collection;

class TournamentResultDisplayService
{
    public function __construct(
        private BwsCalculator $bwsCalculator,
    ) {}

    /**
     * @return Collection<int, array{key: string, label: string, teams: Collection<int, array<string, mixed>>}>
     */
    public function podiumSections(Tournament $tournament): Collection
    {
        /** @var Collection<int, TournamentWinner> $winners */
        $winners = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('placement', '<=', 3)
            ->with(['user.rankHistory'])
            ->orderBy('placement')
            ->get();

        if ($winners->isEmpty()) {
            return collect();
        }

        $recordsByUser = $this->approvedRecords($tournament)
            ->whereIn('user_id', $winners->pluck('user_id')->filter()->values())
            ->keyBy('user_id');

        return $winners
            ->groupBy('placement')
            ->sortKeys()
            ->map(function (Collection $placementWinners, int $placement) use ($tournament, $recordsByUser): array {
                $teams = $placementWinners
                    ->groupBy(fn (TournamentWinner $winner): string => $this->winnerTeamKey($winner, $placement))
                    ->values()
                    ->map(fn (Collection $teamWinners): array => $this->podiumTeam($tournament, $placement, $teamWinners, $recordsByUser))
                    ->sortBy(fn (array $team): int|float => $this->bestRosterRank($tournament, collect($team['roster'])))
                    ->values();

                return [
                    'key' => 'podium-'.$placement,
                    'label' => $this->placementLabel($placement, null, true),
                    'teams' => $teams,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{key: string, label: string, teams: Collection<int, array<string, mixed>>}>
     */
    public function expandedSections(Tournament $tournament): Collection
    {
        return $this->sectionsFromRecords($tournament, $this->nonPodiumRecords($tournament));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentUserTeam(Tournament $tournament, ?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $record = $this->nonPodiumRecords($tournament)
            ->first(fn (TournamentParticipationRecord $record): bool => (int) $record->user_id === (int) $user->id);

        if (! $record instanceof TournamentParticipationRecord) {
            return null;
        }

        foreach ($this->sectionsFromRecords($tournament, $this->nonPodiumRecords($tournament)) as $section) {
            foreach ($section['teams'] as $team) {
                if (collect($team['record_ids'])->contains((int) $record->id)) {
                    $team['current_user'] = true;

                    return $team;
                }
            }
        }

        return null;
    }

    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    private function nonPodiumRecords(Tournament $tournament): Collection
    {
        return $this->approvedRecords($tournament)
            ->reject(function (TournamentParticipationRecord $record): bool {
                $placement = $record->displayPlacement() ?? $record->placement_min;

                return $placement !== null && $placement <= 3;
            })
            ->values();
    }

    /**
     * @return Collection<int, TournamentParticipationRecord>
     */
    private function approvedRecords(Tournament $tournament): Collection
    {
        return TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->with(['user.rankHistory', 'teammates.rankHistory'])
            ->get();
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @return Collection<int, array{key: string, label: string, teams: Collection<int, array<string, mixed>>}>
     */
    private function sectionsFromRecords(Tournament $tournament, Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $sharedRootIds = $records
            ->map(fn (TournamentParticipationRecord $record): int => (int) data_get($record->metadata, 'shared_from_record_id'))
            ->filter(fn (int $id): bool => $id > 0)
            ->flip();

        $teams = $records
            ->groupBy(fn (TournamentParticipationRecord $record): string => $this->recordTeamKey($tournament, $record, $sharedRootIds))
            ->map(fn (Collection $teamRecords): array => $this->participationTeam($tournament, $teamRecords))
            ->values()
            ->sortBy([
                fn (array $first, array $second): int => $first['sort_group'] <=> $second['sort_group'],
                fn (array $first, array $second): int => $first['sort_min'] <=> $second['sort_min'],
                fn (array $first, array $second): int => $first['sort_max'] <=> $second['sort_max'],
                fn (array $first, array $second): int => $first['sort_seed'] <=> $second['sort_seed'],
                fn (array $first, array $second): int => strcasecmp((string) $first['team_name'], (string) $second['team_name']),
            ])
            ->values();

        return $teams
            ->groupBy('section_key')
            ->map(fn (Collection $sectionTeams): array => [
                'key' => (string) $sectionTeams->first()['section_key'],
                'label' => (string) $sectionTeams->first()['section_label'],
                'teams' => $sectionTeams->values(),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, TournamentWinner>  $winners
     * @param  Collection<int, TournamentParticipationRecord>  $recordsByUser
     * @return array<string, mixed>
     */
    private function podiumTeam(Tournament $tournament, int $placement, Collection $winners, Collection $recordsByUser): array
    {
        $teamName = $winners
            ->map(fn (TournamentWinner $winner): ?string => $winner->user_id ? $recordsByUser->get($winner->user_id)?->team_name : null)
            ->first(fn (?string $name): bool => filled($name));
        $roster = $winners
            ->map(fn (TournamentWinner $winner): array => $this->winnerRosterMember($winner))
            ->values();

        return [
            'key' => 'podium-'.$placement.'-'.$winners->pluck('id')->sort()->implode('-'),
            'placement_label' => $this->placementLabel($placement, null, true),
            'section_label' => $this->placementLabel($placement, null, true),
            'team_name' => $teamName,
            'seed' => null,
            'roster' => $this->sortRoster($tournament, $roster),
            'record_ids' => collect(),
            'current_user' => false,
            'is_podium' => true,
        ];
    }

    /**
     * @param  Collection<int, TournamentParticipationRecord>  $records
     * @return array<string, mixed>
     */
    private function participationTeam(Tournament $tournament, Collection $records): array
    {
        $first = $records->sortBy('id')->first();
        $stage = $this->stageValue($first);
        $status = $this->statusKey($first);
        $isNonTeam = in_array($status, ['dnp', 'tryout'], true);
        $placementMin = $records->pluck('placement_min')->filter()->min();
        $placementMax = $records->pluck('placement_max')->filter()->max();
        $placement = $records->pluck('placement_override')->filter()->min() ?? $records->pluck('placement')->filter()->min();
        $seed = $records->pluck('seed')->filter()->min();
        $sectionLabel = $this->sectionLabel($status, $placement, $placementMin, $placementMax);
        $sort = $this->sectionSort($status, $placement, $placementMin, $placementMax, $seed);
        $roster = $records
            ->flatMap(fn (TournamentParticipationRecord $record): Collection => $this->recordRoster($record))
            ->unique(fn (array $member): string => $member['user_id'] ? 'user-'.$member['user_id'] : 'name-'.$member['name'])
            ->values();

        return [
            'key' => 'records-'.$records->pluck('id')->sort()->implode('-'),
            'section_key' => $this->sectionKey($status, $placement, $placementMin, $placementMax),
            'section_label' => $sectionLabel,
            'placement_label' => $sectionLabel,
            'team_name' => $isNonTeam ? null : $records->pluck('team_name')->first(fn (?string $name): bool => filled($name)),
            'seed' => $seed,
            'roster' => $this->sortRoster($tournament, $roster),
            'record_ids' => $records->pluck('id')->map(fn ($id): int => (int) $id)->values(),
            'current_user' => false,
            'is_podium' => false,
            'stage' => $stage,
            'status' => $status,
            'sort_group' => $sort['group'],
            'sort_min' => $sort['min'],
            'sort_max' => $sort['max'],
            'sort_seed' => $sort['seed'],
        ];
    }

    private function winnerTeamKey(TournamentWinner $winner, int $placement): string
    {
        $groupId = data_get($winner->metadata, 'podium_group_id');

        if (is_string($groupId) && $groupId !== '') {
            return 'podium-group-'.$groupId;
        }

        return 'legacy-placement-'.$placement;
    }

    /**
     * @param  Collection<int, int>  $sharedRootIds
     */
    private function recordTeamKey(Tournament $tournament, TournamentParticipationRecord $record, Collection $sharedRootIds): string
    {
        $status = $this->statusKey($record);
        if (in_array($status, ['dnp', 'tryout'], true)) {
            return 'status-'.$status;
        }

        $sharedRoot = (int) data_get($record->metadata, 'shared_from_record_id');
        if ($sharedRoot > 0) {
            return 'shared-'.$sharedRoot;
        }

        if ($sharedRootIds->has($record->id)) {
            return 'shared-'.$record->id;
        }

        if ($status === 'placement' && $this->isSoloTournament($tournament) && ! $this->hasTeamSignal($record)) {
            return 'solo-placement-'.$this->sectionKey(
                $status,
                $record->placement_override ?? $record->placement,
                $record->placement_min,
                $record->placement_max,
            );
        }

        if ($this->hasTeamSignal($record)) {
            return 'roster-'.$this->recordRoster($record)
                ->map(fn (array $member): string => $member['user_id'] ? 'u'.$member['user_id'] : 'n'.md5($member['name']))
                ->sort()
                ->implode('-');
        }

        return 'record-'.$record->id;
    }

    private function isSoloTournament(Tournament $tournament): bool
    {
        return ($tournament->team_size_max ?? 1) <= 1;
    }

    private function hasTeamSignal(TournamentParticipationRecord $record): bool
    {
        return filled($record->team_name)
            || $record->teammates->isNotEmpty()
            || (int) data_get($record->metadata, 'shared_from_record_id') > 0;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recordRoster(TournamentParticipationRecord $record): Collection
    {
        return collect([$record->user])
            ->merge($record->teammates)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->map(fn (User $user): array => $this->userRosterMember($user))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function winnerRosterMember(TournamentWinner $winner): array
    {
        if ($winner->user instanceof User) {
            return array_merge($this->userRosterMember($winner->user), [
                'badge_image_url' => $winner->badge_image_url,
                'osu_id' => $winner->display_osu_id,
            ]);
        }

        return [
            'user_id' => null,
            'name' => $winner->display_username,
            'avatar_url' => null,
            'country_code' => null,
            'profile_url' => null,
            'badge_image_url' => $winner->badge_image_url,
            'osu_id' => $winner->display_osu_id,
            'user' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userRosterMember(User $user): array
    {
        return [
            'user_id' => $user->id,
            'name' => $user->username,
            'avatar_url' => $user->avatar_url,
            'country_code' => $user->country_code,
            'profile_url' => route('users.show', $user),
            'badge_image_url' => null,
            'osu_id' => $user->osu_id,
            'user' => $user,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $roster
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRoster(Tournament $tournament, Collection $roster): Collection
    {
        return $roster
            ->sortBy([
                fn (array $first, array $second): int => $this->memberRank($tournament, $first) <=> $this->memberRank($tournament, $second),
                fn (array $first, array $second): int => strcasecmp((string) $first['name'], (string) $second['name']),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $roster
     */
    private function bestRosterRank(Tournament $tournament, Collection $roster): int|float
    {
        return $roster
            ->map(fn (array $member): int|float => $this->memberRank($tournament, $member))
            ->min() ?? PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function memberRank(Tournament $tournament, array $member): int|float
    {
        $user = $member['user'] ?? null;
        if (! $user instanceof User) {
            return PHP_INT_MAX;
        }

        $modeDetail = $this->primaryMode($tournament);
        $mode = $modeDetail['mode'];
        $keyCount = $modeDetail['key_count'];

        if ($mode === 'osu') {
            return $this->bwsCalculator->calculateForUser($user, $tournament, 'osu') ?? PHP_INT_MAX;
        }

        if ($mode === 'mania' && $keyCount === 4) {
            return $user->rank_mania_4k ?? PHP_INT_MAX;
        }

        if ($mode === 'mania' && $keyCount === 7) {
            return $user->rank_mania_7k ?? PHP_INT_MAX;
        }

        $rankMode = $mode === 'fruits' ? 'catch' : $mode;

        $rankHistory = $user->rankHistory
            ->where('mode', $rankMode)
            ->sortByDesc('recorded_at')
            ->first();

        return $rankHistory?->rank ?: PHP_INT_MAX;
    }

    /**
     * @return array{mode: string, key_count: int|null}
     */
    private function primaryMode(Tournament $tournament): array
    {
        $priority = ['osu' => 1, 'taiko' => 2, 'catch' => 3, 'fruits' => 3, 'mania' => 4];
        $mode = $tournament->modes_with_details
            ->sortBy(fn (array $detail): int => $priority[$detail['mode']] ?? 999)
            ->first() ?: ['mode' => 'osu', 'key_count' => null];

        return [
            'mode' => (string) $mode['mode'],
            'key_count' => $mode['key_count'] ?? null,
        ];
    }

    private function stageValue(?TournamentParticipationRecord $record): ?string
    {
        return $record instanceof TournamentParticipationRecord ? data_get($record->metadata, 'stage_value') : null;
    }

    private function statusKey(?TournamentParticipationRecord $record): string
    {
        $stage = $this->stageValue($record);

        return match (true) {
            $stage === 'dnq' || $record?->selection_outcome === 'dnq' => 'dnq',
            $stage === 'dnp' || $record?->selection_outcome === 'dnp' => 'dnp',
            $stage === 'tryout' || $record?->selection_outcome === TournamentParticipationRecord::SELECTION_TRYOUT_FAILED => 'tryout',
            default => 'placement',
        };
    }

    private function sectionLabel(string $status, ?int $placement, ?int $placementMin, ?int $placementMax): string
    {
        return match ($status) {
            'dnq' => 'DNQ',
            'dnp' => 'DNP',
            'tryout' => 'Tryout',
            default => $this->placementLabel($placement ?? $placementMin, $placementMax),
        };
    }

    private function sectionKey(string $status, ?int $placement, ?int $placementMin, ?int $placementMax): string
    {
        if ($status !== 'placement') {
            return $status;
        }

        return 'placement-'.($placement ?? $placementMin ?? 'unknown').'-'.($placementMax ?? $placement ?? $placementMin ?? 'unknown');
    }

    /**
     * @return array{group: int, min: int, max: int, seed: int}
     */
    private function sectionSort(string $status, ?int $placement, ?int $placementMin, ?int $placementMax, ?int $seed): array
    {
        if ($status === 'placement') {
            $min = $placement ?? $placementMin ?? PHP_INT_MAX;

            return ['group' => 1, 'min' => $min, 'max' => $placementMax ?? $min, 'seed' => PHP_INT_MAX];
        }

        return match ($status) {
            'dnq' => ['group' => 2, 'min' => PHP_INT_MAX, 'max' => PHP_INT_MAX, 'seed' => $seed ?? PHP_INT_MAX],
            'tryout' => ['group' => 3, 'min' => PHP_INT_MAX, 'max' => PHP_INT_MAX, 'seed' => PHP_INT_MAX],
            'dnp' => ['group' => 4, 'min' => PHP_INT_MAX, 'max' => PHP_INT_MAX, 'seed' => PHP_INT_MAX],
            default => ['group' => 9, 'min' => PHP_INT_MAX, 'max' => PHP_INT_MAX, 'seed' => PHP_INT_MAX],
        };
    }

    private function placementLabel(?int $placementMin, ?int $placementMax = null, bool $withPlace = false): string
    {
        if ($placementMin === null) {
            return __('tournaments.results.unknown_placement');
        }

        if ($placementMax !== null && $placementMax !== $placementMin) {
            return $this->ordinal($placementMin).'-'.$this->ordinal($placementMax);
        }

        return $this->ordinal($placementMin).($withPlace ? ' Place' : '');
    }

    private function ordinal(int $value): string
    {
        $mod100 = $value % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $value.'th';
        }

        return $value.match ($value % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
