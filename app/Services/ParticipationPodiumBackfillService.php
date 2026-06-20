<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use App\Models\TournamentWinner;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ParticipationPodiumBackfillService
{
    public function createPodiumGroupId(): string
    {
        return (string) Str::uuid();
    }

    public function backfillFor(User $user): void
    {
        TournamentWinner::query()
            ->where('user_id', $user->id)
            ->where('placement', '<=', 3)
            ->whereHas('tournament', fn ($query) => $query->where('status', 'approved'))
            ->with('tournament')
            ->each(function (TournamentWinner $winner) use ($user): void {
                $stage = $this->podiumStage($winner);
                $groupId = $this->groupIdForWinner($winner);
                $metadata = [
                    'autofilled_from' => 'tournament_winners',
                    'winner_id' => $winner->id,
                    'stage_value' => $stage['value'],
                    'hide_finished_bracket' => true,
                ];
                if ($groupId !== null) {
                    $metadata['podium_group_id'] = $groupId;
                }

                $record = TournamentParticipationRecord::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'tournament_id' => $winner->tournament_id,
                    ],
                    [
                        'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
                        'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
                        'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
                        'final_result' => $winner->placement === 1
                            ? TournamentParticipationRecord::RESULT_WINNER
                            : TournamentParticipationRecord::RESULT_PODIUM,
                        'placement' => $winner->placement,
                        'placement_min' => $winner->placement,
                        'placement_max' => $winner->placement,
                        'stage_type' => Tournament::STAGE_BRACKET,
                        'stage_name' => $stage['label'],
                        'round_label' => $stage['label'],
                        'bracket_path' => $stage['bracket_path'],
                        'metadata' => $metadata,
                    ]
                );

                $this->syncPodiumRecord($record, $winner, $stage);
            });
    }

    public function syncRosterFromRecord(TournamentParticipationRecord $record): void
    {
        $record->loadMissing(['tournament', 'user', 'teammates']);

        $placement = $record->placement_min ?? $record->placement;
        if ($placement === null || $placement > 3) {
            return;
        }

        $groupId = $this->groupIdForRecord($record);
        $includeUngroupedLegacyWinners = $groupId === null;
        if ($groupId === null) {
            $winner = TournamentWinner::query()
                ->where('tournament_id', $record->tournament_id)
                ->where('user_id', $record->user_id)
                ->where('placement', $placement)
                ->first();

            $groupId = $winner instanceof TournamentWinner
                ? $this->ensureWinnerGroupId($winner)
                : $this->createPodiumGroupId();
        }

        $desiredUserIds = $record->teammates
            ->pluck('id')
            ->push($record->user_id)
            ->filter()
            ->unique()
            ->values();

        $existingWinners = TournamentWinner::query()
            ->where('tournament_id', $record->tournament_id)
            ->where('placement', $placement)
            ->whereNotNull('user_id')
            ->get();
        $removedWinners = $existingWinners
            ->filter(fn (TournamentWinner $winner): bool => $this->groupIdForWinner($winner) === $groupId
                || ($includeUngroupedLegacyWinners && $this->groupIdForWinner($winner) === null))
            ->reject(fn (TournamentWinner $winner): bool => $desiredUserIds->contains((int) $winner->user_id));
        $removedUserIds = $removedWinners
            ->pluck('user_id')
            ->filter()
            ->map(fn ($userId): int => (int) $userId)
            ->values();

        $removedWinners->each->delete();

        if ($removedUserIds->isNotEmpty()) {
            $removedRecordsQuery = TournamentParticipationRecord::query()
                ->where('tournament_id', $record->tournament_id)
                ->whereIn('user_id', $removedUserIds)
                ->where(function ($query): void {
                    $query->where('source', TournamentParticipationRecord::SOURCE_SYSTEM)
                        ->orWhere('metadata->autofilled_from', 'tournament_winners');
                });

            if ($includeUngroupedLegacyWinners) {
                $removedRecordsQuery->where(function ($query) use ($groupId): void {
                    $query->where('metadata->podium_group_id', $groupId)
                        ->orWhereNull('metadata->podium_group_id');
                });
            } else {
                $removedRecordsQuery->where('metadata->podium_group_id', $groupId);
            }

            $removedRecordsQuery->delete();
        }

        $users = User::query()
            ->whereIn('id', $desiredUserIds)
            ->get()
            ->keyBy('id');
        $mode = $this->firstTournamentMode($record->tournament);

        foreach ($desiredUserIds as $userId) {
            $podiumUser = $users->get($userId);
            if (! $podiumUser) {
                continue;
            }

            $existingWinner = TournamentWinner::query()
                ->where('tournament_id', $record->tournament_id)
                ->where('user_id', $podiumUser->id)
                ->where('placement', $placement)
                ->first();
            $existingMetadata = $existingWinner instanceof TournamentWinner ? ($existingWinner->metadata ?? []) : [];

            TournamentWinner::query()->updateOrCreate(
                [
                    'tournament_id' => $record->tournament_id,
                    'user_id' => $podiumUser->id,
                    'placement' => $placement,
                ],
                [
                    'username' => $podiumUser->username,
                    'osu_id' => $podiumUser->osu_id,
                    'gamemode' => $mode,
                    'metadata' => array_merge($existingMetadata, [
                        'podium_group_id' => $groupId,
                        'podium_group_manual' => true,
                    ]),
                ]
            );
        }

        $desiredUserIds
            ->merge($removedUserIds)
            ->unique()
            ->each(function (int $userId): void {
                $user = User::query()->find($userId);
                if ($user instanceof User) {
                    $this->backfillFor($user);
                }
            });

        TournamentParticipationRecord::query()
            ->where('tournament_id', $record->tournament_id)
            ->whereIn('user_id', $desiredUserIds)
            ->where('metadata->podium_group_id', $groupId)
            ->update(['team_name' => $record->team_name]);
    }

    public function deletePodiumParticipationRecord(int $tournamentId, ?int $userId, ?string $groupId = null): void
    {
        if ($userId === null) {
            return;
        }

        $query = TournamentParticipationRecord::query()
            ->where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('source', TournamentParticipationRecord::SOURCE_SYSTEM)
                    ->orWhere('metadata->autofilled_from', 'tournament_winners');
            });

        if ($groupId !== null) {
            $query->where('metadata->podium_group_id', $groupId);
        }

        $query->delete();
    }

    public function backfillTournamentGroup(int $tournamentId, string $groupId): void
    {
        TournamentWinner::query()
            ->where('tournament_id', $tournamentId)
            ->where('metadata->podium_group_id', $groupId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->each(function (int $userId): void {
                $user = User::query()->find($userId);
                if ($user instanceof User) {
                    $this->backfillFor($user);
                }
            });
    }

    public function normalizeGroupFromRecord(?TournamentParticipationRecord $record): ?string
    {
        if (! $record instanceof TournamentParticipationRecord) {
            return null;
        }

        $record->loadMissing(['tournament', 'teammates']);

        $placement = $record->placement_min ?? $record->placement;
        if ($placement === null || $placement > 3 || ! $record->isPodiumBacked()) {
            return null;
        }

        $userIds = $record->teammates
            ->pluck('id')
            ->push($record->user_id)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->count() <= 1) {
            return null;
        }

        $winnerIds = TournamentWinner::query()
            ->where('tournament_id', $record->tournament_id)
            ->where('placement', $placement)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($winnerIds === []) {
            return null;
        }

        $sourceWinnerId = TournamentWinner::query()
            ->where('tournament_id', $record->tournament_id)
            ->where('user_id', $record->user_id)
            ->where('placement', $placement)
            ->value('id');

        return $this->assignWinnersToGroup(
            $record->tournament,
            $winnerIds,
            $sourceWinnerId !== null ? (int) $sourceWinnerId : null
        );
    }

    public function defaultGroupIdForNewWinner(Tournament $tournament, int $placement): string
    {
        /** @var Collection<int, TournamentWinner> $winners */
        $winners = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('placement', $placement)
            ->whereNotNull('user_id')
            ->get();

        if ($winners->isEmpty()) {
            return $this->createPodiumGroupId();
        }

        $largestGroup = $winners
            ->groupBy(fn (TournamentWinner $winner): string => $this->groupIdForWinner($winner) ?? 'legacy-placement-'.$placement)
            ->sortByDesc(fn (Collection $groupWinners): int => $groupWinners->count())
            ->first();

        if (! $largestGroup instanceof Collection || $largestGroup->isEmpty()) {
            return $this->createPodiumGroupId();
        }

        $existingGroupId = $largestGroup
            ->map(fn (TournamentWinner $winner): ?string => $this->groupIdForWinner($winner))
            ->filter()
            ->first();

        if (is_string($existingGroupId) && $existingGroupId !== '') {
            return $existingGroupId;
        }

        return $this->assignWinnersToGroup(
            $tournament,
            $largestGroup->pluck('id')->map(fn ($id): int => (int) $id)->all()
        );
    }

    /**
     * @param  array<int, int>  $winnerIds
     */
    public function assignWinnersToGroup(Tournament $tournament, array $winnerIds, ?int $sourceWinnerId = null, ?string $teamName = null, bool $backfill = true): string
    {
        /** @var Collection<int, TournamentWinner> $winners */
        $winners = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', $winnerIds)
            ->get();

        if ($winners->isEmpty()) {
            return $this->createPodiumGroupId();
        }

        if ($winners->count() !== count(array_unique($winnerIds))) {
            throw ValidationException::withMessages([
                'winner_ids' => 'All podium members must belong to this tournament.',
            ]);
        }

        if ($winners->pluck('placement')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'winner_ids' => 'Podium members can only be grouped within the same placement.',
            ]);
        }

        $oldGroupIds = $winners
            ->map(fn (TournamentWinner $winner): ?string => $this->groupIdForWinner($winner))
            ->filter()
            ->unique()
            ->values();
        $sourceWinner = $this->sourceWinnerForGroupMerge($winners, $sourceWinnerId);
        $groupId = $sourceWinner instanceof TournamentWinner
            ? ($this->groupIdForWinner($sourceWinner) ?? $this->createPodiumGroupId())
            : $this->createPodiumGroupId();

        $winners->each(function (TournamentWinner $winner) use ($groupId): void {
            $winner->forceFill([
                'metadata' => array_merge($winner->metadata ?? [], [
                    'podium_group_id' => $groupId,
                    'podium_group_manual' => true,
                ]),
            ])->save();
        });

        if ($backfill) {
            $winners
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->each(function (int $userId): void {
                    $user = User::query()->find($userId);
                    if ($user instanceof User) {
                        $this->backfillFor($user);
                    }
                });

            $this->syncGroupParticipationData($tournament, $groupId, $sourceWinner, $teamName);
            $oldGroupIds
                ->reject(fn (string $oldGroupId): bool => $oldGroupId === $groupId)
                ->each(fn (string $oldGroupId) => $this->backfillTournamentGroup($tournament->id, $oldGroupId));
        }

        return $groupId;
    }

    public function updateGroupDetails(Tournament $tournament, string $groupId, ?int $sourceWinnerId = null, ?string $teamName = null): void
    {
        $winners = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('metadata->podium_group_id', $groupId)
            ->get();

        if ($winners->isEmpty()) {
            return;
        }

        $sourceWinner = $this->sourceWinnerForGroupMerge($winners, $sourceWinnerId);
        $this->syncGroupParticipationData($tournament, $groupId, $sourceWinner, $teamName);
    }

    public function replaceGroupTeamName(int $tournamentId, string $groupId, ?string $teamName): void
    {
        TournamentParticipationRecord::query()
            ->where('tournament_id', $tournamentId)
            ->where('metadata->podium_group_id', $groupId)
            ->update([
                'team_name' => filled($teamName) ? trim((string) $teamName) : null,
            ]);
    }

    public function splitWinnerToNewGroup(TournamentWinner $winner): string
    {
        $oldGroupId = $this->groupIdForWinner($winner);
        $newGroupId = $this->createPodiumGroupId();

        $winner->forceFill([
            'metadata' => array_merge($winner->metadata ?? [], [
                'podium_group_id' => $newGroupId,
                'podium_group_manual' => true,
            ]),
        ])->save();

        if ($winner->user_id !== null) {
            $user = User::query()->find($winner->user_id);
            if ($user instanceof User) {
                $this->backfillFor($user);
            }
        }

        if ($oldGroupId !== null) {
            $this->backfillTournamentGroup($winner->tournament_id, $oldGroupId);
        }
        $this->clearGroupSharedParticipationData($winner->tournament_id, $newGroupId);

        return $newGroupId;
    }

    /**
     * @param  array<int, int>  $winnerIds
     */
    public function splitWinnersToNewGroup(Tournament $tournament, array $winnerIds): string
    {
        /** @var Collection<int, TournamentWinner> $winners */
        $winners = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', $winnerIds)
            ->get();

        if ($winners->isEmpty() || $winners->count() !== count(array_unique($winnerIds))) {
            throw ValidationException::withMessages([
                'winner_ids' => 'All podium members must belong to this tournament.',
            ]);
        }

        if ($winners->pluck('placement')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'winner_ids' => 'Podium members can only be split within the same placement.',
            ]);
        }

        $oldGroupIds = $winners
            ->map(fn (TournamentWinner $winner): ?string => $this->groupIdForWinner($winner))
            ->filter()
            ->unique()
            ->values();
        $newGroupId = $this->createPodiumGroupId();

        $winners->each(function (TournamentWinner $winner) use ($newGroupId): void {
            $winner->forceFill([
                'metadata' => array_merge($winner->metadata ?? [], [
                    'podium_group_id' => $newGroupId,
                    'podium_group_manual' => true,
                ]),
            ])->save();
        });

        $winners
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->each(function (int $userId): void {
                $user = User::query()->find($userId);
                if ($user instanceof User) {
                    $this->backfillFor($user);
                }
            });

        $this->clearGroupSharedParticipationData($tournament->id, $newGroupId);
        $oldGroupIds->each(fn (string $oldGroupId) => $this->backfillTournamentGroup($tournament->id, $oldGroupId));

        return $newGroupId;
    }

    /**
     * @return array{label: string, value: string, bracket_path: string}
     */
    private function podiumStage(TournamentWinner $winner): array
    {
        $bracketIndex = 0;
        $bracketStage = $winner->tournament->formatStages()
            ->values()
            ->first(function (array $stage, int $index) use (&$bracketIndex): bool {
                if (($stage['type'] ?? null) !== Tournament::STAGE_BRACKET) {
                    return false;
                }

                $bracketIndex = $index;

                return true;
            });

        $isSingleElimination = is_array($bracketStage)
            && ($bracketStage['elimination_type'] ?? null) === Tournament::BRACKET_ELIMINATION_SINGLE;
        if ($isSingleElimination) {
            return [
                'label' => 'F',
                'value' => "bracket:{$bracketIndex}:2:winners:f:{$winner->placement}:{$winner->placement}",
                'bracket_path' => TournamentParticipationRecord::BRACKET_WINNERS,
            ];
        }

        if ($winner->placement === 3) {
            return [
                'label' => 'GF LB',
                'value' => "bracket:{$bracketIndex}:1:losers:gf_lb:3:3",
                'bracket_path' => TournamentParticipationRecord::BRACKET_LOSERS,
            ];
        }

        return [
            'label' => 'GF',
            'value' => "bracket:{$bracketIndex}:1:grand_finals:gf",
            'bracket_path' => TournamentParticipationRecord::BRACKET_GRAND_FINALS,
        ];
    }

    /**
     * @param  array{label: string, value: string, bracket_path: string}  $stage
     */
    private function syncPodiumRecord(TournamentParticipationRecord $record, TournamentWinner $winner, array $stage): void
    {
        $groupId = $this->groupIdForWinner($winner);
        $metadata = array_merge($record->metadata ?? [], [
            'autofilled_from' => 'tournament_winners',
            'winner_id' => $winner->id,
            'stage_value' => $stage['value'],
            'hide_finished_bracket' => true,
        ]);
        if ($groupId !== null) {
            $metadata['podium_group_id'] = $groupId;
        } else {
            unset($metadata['podium_group_id']);
        }

        $record->forceFill([
            'source' => TournamentParticipationRecord::SOURCE_SYSTEM,
            'review_status' => TournamentParticipationRecord::REVIEW_APPROVED,
            'selection_outcome' => TournamentParticipationRecord::SELECTION_REGISTERED,
            'final_result' => $winner->placement === 1
                ? TournamentParticipationRecord::RESULT_WINNER
                : TournamentParticipationRecord::RESULT_PODIUM,
            'stage_type' => Tournament::STAGE_BRACKET,
            'stage_name' => $stage['label'],
            'round_label' => $stage['label'],
            'bracket_path' => $stage['bracket_path'],
            'placement' => $winner->placement,
            'placement_min' => $winner->placement,
            'placement_max' => $winner->placement,
            'placement_override' => null,
            'metadata' => $metadata,
        ])->save();

        $teammateQuery = TournamentWinner::query()
            ->where('tournament_id', $winner->tournament_id)
            ->where('placement', $winner->placement)
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $winner->user_id);

        if ($groupId !== null) {
            $teammateQuery->where('metadata->podium_group_id', $groupId);
        } elseif ($this->shouldGroupLegacyPlacement($winner)) {
            $teammateQuery->where(function ($query): void {
                $query->whereNull('metadata->podium_group_id')
                    ->orWhereNull('metadata->podium_group_manual')
                    ->orWhere('metadata->podium_group_manual', false);
            });
        } else {
            $teammateQuery->whereRaw('1 = 0');
        }

        $teammateIds = $teammateQuery->pluck('user_id')->all();

        $record->teammates()->sync($teammateIds);
    }

    public function groupIdForWinner(TournamentWinner $winner): ?string
    {
        $groupId = data_get($winner->metadata, 'podium_group_id');

        if (! is_string($groupId) || $groupId === '') {
            return null;
        }

        if (data_get($winner->metadata, 'podium_group_manual') === true) {
            return $groupId;
        }

        $groupSize = TournamentWinner::query()
            ->where('tournament_id', $winner->tournament_id)
            ->where('metadata->podium_group_id', $groupId)
            ->count();

        return $groupSize > 1 ? $groupId : null;
    }

    private function ensureWinnerGroupId(TournamentWinner $winner): string
    {
        $groupId = $this->groupIdForWinner($winner);
        if ($groupId !== null) {
            return $groupId;
        }

        $groupId = $this->createPodiumGroupId();
        $winner->forceFill([
            'metadata' => array_merge($winner->metadata ?? [], [
                'podium_group_id' => $groupId,
                'podium_group_manual' => true,
            ]),
        ])->save();

        return $groupId;
    }

    private function groupIdForRecord(TournamentParticipationRecord $record): ?string
    {
        $groupId = data_get($record->metadata, 'podium_group_id');

        return is_string($groupId) && $groupId !== '' ? $groupId : null;
    }

    /**
     * @param  Collection<int, TournamentWinner>  $winners
     */
    private function sourceWinnerForGroupMerge(Collection $winners, ?int $sourceWinnerId): ?TournamentWinner
    {
        if ($sourceWinnerId !== null) {
            $sourceWinner = $winners->firstWhere('id', $sourceWinnerId);
            if ($sourceWinner instanceof TournamentWinner) {
                return $sourceWinner;
            }
        }

        return $winners
            ->sortByDesc(function (TournamentWinner $winner): int {
                if ($winner->user_id === null) {
                    return 0;
                }

                $record = TournamentParticipationRecord::query()
                    ->where('tournament_id', $winner->tournament_id)
                    ->where('user_id', $winner->user_id)
                    ->first();

                if (! $record instanceof TournamentParticipationRecord) {
                    return 0;
                }

                return (filled($record->team_name) ? 4 : 0)
                    + ($record->seed !== null ? 2 : 0)
                    + (! empty($record->matches) ? 1 : 0);
            })
            ->first();
    }

    private function syncGroupParticipationData(Tournament $tournament, string $groupId, ?TournamentWinner $sourceWinner, ?string $teamName): void
    {
        $sourceRecord = $sourceWinner?->user_id === null
            ? null
            : TournamentParticipationRecord::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $sourceWinner->user_id)
                ->first();
        $groupUserIds = TournamentWinner::query()
            ->where('tournament_id', $tournament->id)
            ->where('metadata->podium_group_id', $groupId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($userId): int => (int) $userId)
            ->unique()
            ->values();

        if ($groupUserIds->isEmpty()) {
            return;
        }

        $records = TournamentParticipationRecord::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('user_id', $groupUserIds)
            ->get()
            ->keyBy('user_id');
        $effectiveTeamName = $teamName !== null ? trim($teamName) : ($sourceRecord instanceof TournamentParticipationRecord ? $sourceRecord->team_name : null);
        $shareSeed = app(ParticipationStageOptionsService::class)->qualifierCutoff($tournament) !== null;
        $sourceUserId = $sourceRecord?->user_id;
        $sharedMatches = $sourceRecord instanceof TournamentParticipationRecord ? ($sourceRecord->matches ?? []) : [];

        foreach ($groupUserIds as $userId) {
            $record = $records->get($userId);
            if (! $record instanceof TournamentParticipationRecord) {
                continue;
            }

            $payload = [
                'team_name' => $effectiveTeamName !== '' ? $effectiveTeamName : null,
            ];

            if ($shareSeed || $sourceUserId === $userId) {
                $payload['seed'] = $sourceRecord?->seed;
            } else {
                $payload['seed'] = null;
            }

            $record->update($payload);
            $record->syncParticipationMatches($sharedMatches, $sourceUserId);
        }
    }

    private function clearGroupSharedParticipationData(int $tournamentId, string $groupId): void
    {
        TournamentParticipationRecord::query()
            ->where('tournament_id', $tournamentId)
            ->where('metadata->podium_group_id', $groupId)
            ->get()
            ->each(function (TournamentParticipationRecord $record): void {
                $record->update([
                    'team_name' => null,
                    'seed' => null,
                ]);
                $record->syncParticipationMatches([]);
            });
    }

    private function shouldGroupLegacyPlacement(TournamentWinner $winner): bool
    {
        if ($winner->placement <= 2) {
            return true;
        }

        if ($winner->placement !== 3) {
            return false;
        }

        $bracketStage = $winner->tournament->formatStages()
            ->first(fn (array $stage): bool => ($stage['type'] ?? null) === Tournament::STAGE_BRACKET);

        $isSingleElimination = is_array($bracketStage)
            && ($bracketStage['elimination_type'] ?? null) === Tournament::BRACKET_ELIMINATION_SINGLE;

        if (! $isSingleElimination) {
            return true;
        }

        $teamSizeMax = max(1, (int) ($winner->tournament->team_size_max ?? 1));
        $thirdPlaceCount = TournamentWinner::query()
            ->where('tournament_id', $winner->tournament_id)
            ->where('placement', 3)
            ->whereNotNull('user_id')
            ->count();

        return $thirdPlaceCount <= $teamSizeMax;
    }

    private function firstTournamentMode(Tournament $tournament): string
    {
        $mode = $tournament->modes_with_details
            ->pluck('mode')
            ->filter()
            ->first();

        return (string) ($mode ?: 'osu');
    }
}
