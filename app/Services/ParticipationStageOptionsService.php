<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;
use Illuminate\Support\Collection;

class ParticipationStageOptionsService
{
    /**
     * @return Collection<int, array{value: string, label: string, stage_type: string|null, stage_name: string|null, round_label: string|null, bracket_path: string|null, editable_placement: bool, range_placement: bool, placement_min: int|null, placement_max: int|null, allowed_placements: array<int, int>}>
     */
    public function optionsFor(Tournament $tournament): Collection
    {
        $options = collect();
        $stages = $tournament->formatStages();
        $currentFieldSize = null;

        if ($this->hasQualifier($tournament)) {
            $options->push($this->option('dnq', 'Qualifier', Tournament::STAGE_QUALIFIER, null, null, null, false));
        }

        if ($this->hasDraftLikeSelection($tournament)) {
            $options->push($this->option('dnp', 'DNP', null, null, null, null, true));
        }

        if ($tournament->team_formation_style === Tournament::TEAM_FORMATION_WORLD_CUP) {
            $options->push($this->option('tryout', 'Tryout', Tournament::STAGE_QUALIFIER, 'Tryout', null, null, false));
        }

        foreach ($stages as $index => $stage) {
            $type = (string) ($stage['type'] ?? '');
            $entrantFieldSize = $this->explicitStageFieldSize($stage) ?? $currentFieldSize;

            if ($type === Tournament::STAGE_QUALIFIER) {
                $currentFieldSize = $this->stageAdvanceCount($stage) ?? $currentFieldSize;

                continue;
            }

            if ($type === Tournament::STAGE_BRACKET) {
                $options = $options->merge($this->bracketOptions($stage, $index, $entrantFieldSize));
                $currentFieldSize = null;

                continue;
            }

            if ($type === Tournament::STAGE_GROUP) {
                $options->push($this->groupStageOption($stage, $index, $entrantFieldSize));
                $currentFieldSize = $this->stageAdvanceCount($stage) ?? $entrantFieldSize;

                continue;
            }

            if ($type === Tournament::STAGE_SWISS) {
                $options->push($this->swissStageOption($stage, $index, $entrantFieldSize));
                $currentFieldSize = $this->stageAdvanceCount($stage) ?? $entrantFieldSize;

                continue;
            }

            if ($type === Tournament::STAGE_BATTLE_ROYALE) {
                $options = $options->merge($this->battleRoyaleOptions($stage, $index, $entrantFieldSize));
                $currentFieldSize = $this->battleRoyaleFinalFieldSize($stage, $entrantFieldSize) ?? $entrantFieldSize;

                continue;
            }

            $label = $this->stageLabel($stage, $index);
            $options->push($this->option(
                "stage:{$index}",
                $label,
                $type ?: null,
                $label,
                null,
                null,
                true
            ));
        }

        if ($options->isEmpty()) {
            $options->push($this->option('completed', __('users.participation.stage.completed'), null, null, null, null, true));
        }

        return $options->values();
    }

    public function hasQualifier(Tournament $tournament): bool
    {
        return $tournament->formatStages()->contains(fn (array $stage): bool => ($stage['type'] ?? null) === Tournament::STAGE_QUALIFIER);
    }

    public function hasDraftLikeSelection(Tournament $tournament): bool
    {
        return in_array($tournament->team_formation_style, [
            Tournament::TEAM_FORMATION_DRAFT,
            Tournament::TEAM_FORMATION_AUCTION,
            Tournament::TEAM_FORMATION_WORLD_CUP,
        ], true);
    }

    public function qualifierCutoff(Tournament $tournament): ?int
    {
        $qualifier = $tournament->formatStages()
            ->first(fn (array $stage): bool => ($stage['type'] ?? null) === Tournament::STAGE_QUALIFIER);

        $cutoff = is_array($qualifier) ? (int) ($qualifier['advance_count'] ?? 0) : 0;

        return $cutoff > 0 ? $cutoff : null;
    }

    public function isTeamTournament(Tournament $tournament): bool
    {
        return (int) ($tournament->team_size_max ?? 0) > 1
            || (int) ($tournament->team_size_min ?? 0) > 1
            || (int) ($tournament->vs_size ?? 0) > 1;
    }

    /**
     * @return array<int, string>
     */
    public function matchStageLabelsFor(Tournament $tournament): array
    {
        $labels = [
            'Battle Royale',
            'Group Stage',
            'Swiss Round',
            'Qualifier',
            'Tryout',
            'Ro128',
            'Ro64',
            'Ro32',
            'Ro16',
            'QF',
            'SF',
            'F',
            'GF',
        ];

        return $labels;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $stage
     * @return Collection<int, array<string, mixed>>
     */
    private function bracketOptions(array $stage, int $index, ?int $entrantFieldSize = null): Collection
    {
        $roundSize = $this->positiveInt($stage['start_round_size'] ?? null) ?? $entrantFieldSize;
        $eliminationType = (string) ($stage['elimination_type'] ?? Tournament::BRACKET_ELIMINATION_DOUBLE);
        if ($roundSize === null || $roundSize < 2) {
            $label = $this->stageLabel($stage, $index);

            return collect([$this->option("stage:{$index}", $label, Tournament::STAGE_BRACKET, $label, null, null, true)]);
        }

        $roundSize = $this->nearestPowerOfTwo($roundSize);
        $roundSizes = $this->roundSizes($roundSize);

        if ($eliminationType === Tournament::BRACKET_ELIMINATION_DOUBLE) {
            return $this->doubleEliminationOptions($roundSize, $index, (string) ($stage['entry_type'] ?? Tournament::BRACKET_ENTRY_WINNER_ONLY));
        }

        return collect($roundSizes)->map(function (int $size) use ($index) {
            $label = $size > 0 ? $this->shortRoundLabel($size) : __('users.participation.stage.bracket');
            $isFinal = $size === 2;
            $placementMin = $isFinal ? 1 : ($size > 1 ? (int) floor($size / 2) + 1 : null);
            $placementMax = $isFinal ? 4 : ($size > 1 ? $size : null);

            return $this->option(
                "bracket:{$index}:{$size}:single",
                $label,
                Tournament::STAGE_BRACKET,
                $label,
                $label,
                null,
                $isFinal,
                $placementMin,
                $placementMax
            );
        });
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<string, mixed>
     */
    private function groupStageOption(array $stage, int $index, ?int $entrantFieldSize = null): array
    {
        $label = $this->stageLabel($stage, $index);
        $advanceCount = $this->positiveInt($stage['advance_count'] ?? null);
        $totalTeams = $this->explicitGroupFieldSize($stage) ?? $entrantFieldSize;

        if ($totalTeams !== null && $advanceCount !== null && $advanceCount < $totalTeams) {
            $placementMin = $advanceCount + 1;
            $placementMax = $totalTeams;

            return $this->option(
                "stage:{$index}:{$placementMin}:{$placementMax}",
                $label,
                Tournament::STAGE_GROUP,
                $label,
                null,
                null,
                false,
                $placementMin,
                $placementMax
            );
        }

        return $this->option("stage:{$index}", $label, Tournament::STAGE_GROUP, $label, null, null, true);
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<string, mixed>
     */
    private function swissStageOption(array $stage, int $index, ?int $entrantFieldSize = null): array
    {
        $label = $this->stageLabel($stage, $index);
        $advanceCount = $this->positiveInt($stage['advance_count'] ?? null);
        $placeholderMin = $advanceCount !== null ? $advanceCount + 1 : null;

        if ($entrantFieldSize !== null && $advanceCount !== null && $advanceCount < $entrantFieldSize) {
            $placementMin = $advanceCount + 1;
            $placementMax = $entrantFieldSize;

            return $this->option(
                "swiss:{$index}:{$placementMin}:{$placementMax}",
                $label,
                Tournament::STAGE_SWISS,
                $label,
                null,
                null,
                false,
                $placementMin,
                $placementMax
            );
        }

        return $this->option(
            "swiss:{$index}",
            $label,
            Tournament::STAGE_SWISS,
            $label,
            null,
            null,
            false,
            $placeholderMin,
            null,
            [],
            true
        );
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return Collection<int, array<string, mixed>>
     */
    private function battleRoyaleOptions(array $stage, int $index, ?int $entrantFieldSize = null): Collection
    {
        $rounds = $this->battleRoyaleRounds($stage, $entrantFieldSize);

        if ($rounds === []) {
            $label = $this->stageLabel($stage, $index);

            return collect([$this->option("stage:{$index}", $label, Tournament::STAGE_BATTLE_ROYALE, $label, null, null, true)]);
        }

        return collect($rounds)->map(function (array $round) use ($index) {
            return $this->option(
                "battle_royale:{$index}:{$round['size']}:single:".strtolower($round['label']).":{$round['placement_min']}:{$round['placement_max']}",
                $round['label'],
                Tournament::STAGE_BATTLE_ROYALE,
                'Battle Royale',
                $round['label'],
                null,
                $round['editable'],
                $round['placement_min'],
                $round['placement_max']
            );
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function doubleEliminationOptions(int $startRoundSize, int $index, string $entryType): Collection
    {
        $options = collect();
        $startsWithLowerBracket = $entryType === Tournament::BRACKET_ENTRY_WINNER_LOSER_HYBRID;
        $totalEntrants = $startsWithLowerBracket ? $startRoundSize * 2 : $startRoundSize;
        $lowestUnplaced = $totalEntrants;
        $firstLowerSize = $startsWithLowerBracket ? $startRoundSize : (int) floor($startRoundSize / 2);

        if ($firstLowerSize >= 2) {
            $placementMax = $lowestUnplaced;
            $placementMin = max(4, $placementMax - (int) floor($firstLowerSize / 2) + 1);
            $label = $this->shortRoundLabel($firstLowerSize).' LB';

            $options->push($this->option(
                "bracket:{$index}:{$firstLowerSize}:losers:".strtolower(str_replace(' ', '_', $label)).":{$placementMin}:{$placementMax}",
                $label,
                Tournament::STAGE_BRACKET,
                $label,
                $label,
                TournamentParticipationRecord::BRACKET_LOSERS,
                false,
                $placementMin,
                $placementMax
            ));

            $lowestUnplaced = $placementMin - 1;
        }

        $pairedRoundSize = (int) floor($firstLowerSize / 2);

        while ($pairedRoundSize >= 2) {
            foreach ([
                ['suffix' => 'LB1', 'eliminated' => $pairedRoundSize],
                ['suffix' => 'LB2', 'eliminated' => (int) floor($pairedRoundSize / 2)],
            ] as $variant) {
                $variantLabel = $this->shortRoundLabel($pairedRoundSize).' '.$variant['suffix'];
                $placementMax = $lowestUnplaced;
                $placementMin = max(4, $placementMax - $variant['eliminated'] + 1);

                $options->push($this->option(
                    "bracket:{$index}:{$pairedRoundSize}:losers:".strtolower(str_replace(' ', '_', $variantLabel)).":{$placementMin}:{$placementMax}",
                    $variantLabel,
                    Tournament::STAGE_BRACKET,
                    $variantLabel,
                    $variantLabel,
                    TournamentParticipationRecord::BRACKET_LOSERS,
                    false,
                    $placementMin,
                    $placementMax
                ));

                $lowestUnplaced = $placementMin - 1;
            }

            $pairedRoundSize = (int) floor($pairedRoundSize / 2);
        }

        $options->push($this->option(
            "bracket:{$index}:1:losers:gf_lb:3:3",
            'GF LB',
            Tournament::STAGE_BRACKET,
            'GF LB',
            'GF LB',
            TournamentParticipationRecord::BRACKET_LOSERS,
            false,
            3,
            3
        ));

        $options->push($this->option(
            "bracket:{$index}:1:grand_finals:gf",
            'GF',
            Tournament::STAGE_BRACKET,
            'GF',
            'GF',
            TournamentParticipationRecord::BRACKET_GRAND_FINALS,
            true,
            null,
            null,
            [1, 2]
        ));

        return $options->values();
    }

    /**
     * @return array<int>
     */
    private function roundSizes(int $startRoundSize): array
    {
        $sizes = [];
        for ($size = $startRoundSize; $size >= 2; $size = (int) ($size / 2)) {
            $sizes[] = $size;
        }

        return $sizes;
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<int, array{label: string, size: int, editable: bool, placement_min: int, placement_max: int}>
     */
    private function battleRoyaleRounds(array $stage, ?int $entrantFieldSize = null): array
    {
        $lobbyCount = $this->positiveInt($stage['lobby_count'] ?? null);
        $playersPerLobby = $this->positiveInt($stage['players_per_lobby'] ?? null);
        $advancePerLobby = $this->positiveInt($stage['advance_per_lobby'] ?? null);
        $initialPlayers = $lobbyCount !== null && $playersPerLobby !== null
            ? $lobbyCount * $playersPerLobby
            : $entrantFieldSize;

        if ($lobbyCount === 1 && $playersPerLobby !== null) {
            return [[
                'label' => 'GF',
                'size' => 1,
                'editable' => true,
                'placement_min' => 1,
                'placement_max' => $playersPerLobby,
            ]];
        }

        if (! $initialPlayers || ! $playersPerLobby || ! $advancePerLobby || $advancePerLobby >= $playersPerLobby) {
            return [];
        }

        $currentPlayers = $initialPlayers;
        $rounds = 1;

        while ($currentPlayers > $playersPerLobby && $rounds < 100) {
            $currentLobbyCount = (int) ceil($currentPlayers / $playersPerLobby);
            $currentPlayers = $currentLobbyCount * $advancePerLobby;
            $rounds++;
        }

        $labels = collect($this->roundSizes(2 ** max(1, $rounds - 1)))
            ->map(fn (int $size): string => $this->shortRoundLabel($size))
            ->values()
            ->all();

        $currentPlayers = $initialPlayers;
        $battleRoyaleRounds = [];

        foreach ($labels as $index => $label) {
            $currentLobbyCount = (int) ceil($currentPlayers / $playersPerLobby);
            $advancingPlayers = $currentLobbyCount * $advancePerLobby;

            $battleRoyaleRounds[] = [
                'label' => $label,
                'size' => 2 ** (count($labels) - $index),
                'editable' => false,
                'placement_min' => $advancingPlayers + 1,
                'placement_max' => $currentPlayers,
            ];

            $currentPlayers = $advancingPlayers;
        }

        $battleRoyaleRounds[] = [
            'label' => 'GF',
            'size' => 1,
            'editable' => true,
            'placement_min' => 1,
            'placement_max' => $currentPlayers,
        ];

        return $battleRoyaleRounds;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function explicitStageFieldSize(array $stage): ?int
    {
        return match ($stage['type'] ?? null) {
            Tournament::STAGE_GROUP => $this->explicitGroupFieldSize($stage),
            Tournament::STAGE_BRACKET => $this->positiveInt($stage['start_round_size'] ?? null),
            Tournament::STAGE_BATTLE_ROYALE => $this->explicitBattleRoyaleFieldSize($stage),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function explicitGroupFieldSize(array $stage): ?int
    {
        $groupCount = $this->positiveInt($stage['group_count'] ?? null);
        $teamsPerGroup = $this->positiveInt($stage['teams_per_group'] ?? null);

        return $groupCount !== null && $teamsPerGroup !== null
            ? $groupCount * $teamsPerGroup
            : null;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function explicitBattleRoyaleFieldSize(array $stage): ?int
    {
        $lobbyCount = $this->positiveInt($stage['lobby_count'] ?? null);
        $playersPerLobby = $this->positiveInt($stage['players_per_lobby'] ?? null);

        return $lobbyCount !== null && $playersPerLobby !== null
            ? $lobbyCount * $playersPerLobby
            : null;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function stageAdvanceCount(array $stage): ?int
    {
        return $this->positiveInt($stage['advance_count'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function battleRoyaleFinalFieldSize(array $stage, ?int $entrantFieldSize = null): ?int
    {
        $rounds = $this->battleRoyaleRounds($stage, $entrantFieldSize);

        if ($rounds === []) {
            return null;
        }

        return collect($rounds)->last()['placement_max'] ?? null;
    }

    private function nearestPowerOfTwo(int $value): int
    {
        $power = 2;
        while ($power * 2 <= $value && $power < 4096) {
            $power *= 2;
        }

        return $power;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function shortRoundLabel(int $size): string
    {
        return match ($size) {
            8 => 'QF',
            4 => 'SF',
            2 => 'F',
            default => 'Ro'.$size,
        };
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function stageLabel(array $stage, int $index): string
    {
        if (! empty($stage['name'])) {
            return (string) $stage['name'];
        }

        return match ($stage['type'] ?? null) {
            Tournament::STAGE_QUALIFIER => 'Qualifier',
            Tournament::STAGE_GROUP => 'Group Stage',
            Tournament::STAGE_SWISS => 'Swiss Round',
            Tournament::STAGE_BATTLE_ROYALE => 'Battle Royale',
            default => 'Stage '.($index + 1),
        };
    }

    /**
     * @param  array<int, int>  $allowedPlacements
     * @return array{value: string, label: string, stage_type: string|null, stage_name: string|null, round_label: string|null, bracket_path: string|null, editable_placement: bool, range_placement: bool, placement_min: int|null, placement_max: int|null, allowed_placements: array<int, int>}
     */
    private function option(string $value, string $label, ?string $stageType, ?string $stageName, ?string $roundLabel, ?string $bracketPath, bool $editablePlacement, ?int $placementMin = null, ?int $placementMax = null, array $allowedPlacements = [], bool $rangePlacement = false): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'stage_type' => $stageType,
            'stage_name' => $stageName,
            'round_label' => $roundLabel,
            'bracket_path' => $bracketPath,
            'editable_placement' => $editablePlacement,
            'range_placement' => $rangePlacement,
            'placement_min' => $placementMin,
            'placement_max' => $placementMax,
            'allowed_placements' => $allowedPlacements,
        ];
    }
}
