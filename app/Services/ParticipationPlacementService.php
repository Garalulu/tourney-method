<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentParticipationRecord;

class ParticipationPlacementService
{
    /**
     * @return array{placement: int|null, placement_min: int|null, placement_max: int|null, editable: bool}
     */
    public function calculate(Tournament $tournament, ?string $stageValue, ?int $override = null): array
    {
        if ($override !== null) {
            return [
                'placement' => $override,
                'placement_min' => $override,
                'placement_max' => $override,
                'editable' => true,
            ];
        }

        if (! $stageValue) {
            return $this->empty();
        }

        if ($stageValue === 'dnq') {
            return ['placement' => null, 'placement_min' => null, 'placement_max' => null, 'editable' => false];
        }

        if ($stageValue === 'dnp') {
            return ['placement' => null, 'placement_min' => null, 'placement_max' => null, 'editable' => true];
        }

        if ($stageValue === 'tryout') {
            return ['placement' => null, 'placement_min' => null, 'placement_max' => null, 'editable' => false];
        }

        $parts = explode(':', $stageValue);
        $stageKind = $parts[0];

        if (in_array($stageKind, ['stage', 'swiss', 'bracket', 'battle_royale'], true)) {
            $range = $this->rangeFromStageValue($parts, $stageKind);

            if ($range !== null) {
                return [
                    'placement' => $range['min'],
                    'placement_min' => $range['min'],
                    'placement_max' => $range['max'],
                    'editable' => false,
                ];
            }
        }

        if (! in_array($stageKind, ['bracket', 'battle_royale'], true)) {
            return ['placement' => null, 'placement_min' => null, 'placement_max' => null, 'editable' => true];
        }

        $roundSize = (int) ($parts[2] ?? 0);
        $path = (string) ($parts[3] ?? '');
        if ($roundSize <= 0) {
            return ['placement' => null, 'placement_min' => null, 'placement_max' => null, 'editable' => true];
        }

        if ($roundSize === 2) {
            $placement = $path === TournamentParticipationRecord::BRACKET_WINNERS ? 2 : 3;

            return [
                'placement' => $placement,
                'placement_min' => $placement,
                'placement_max' => $placement,
                'editable' => false,
            ];
        }

        $min = (int) floor($roundSize / 2) + 1;
        $max = $roundSize;

        return [
            'placement' => $min,
            'placement_min' => $min,
            'placement_max' => $max,
            'editable' => false,
        ];
    }

    /**
     * @return array{placement: int|null, placement_min: int|null, placement_max: int|null, editable: bool}
     */
    private function empty(): array
    {
        return [
            'placement' => null,
            'placement_min' => null,
            'placement_max' => null,
            'editable' => true,
        ];
    }

    /**
     * @param  array<int, string>  $parts
     * @return array{min: int, max: int}|null
     */
    private function rangeFromStageValue(array $parts, string $stageKind): ?array
    {
        $placementMin = isset($parts[5]) ? (int) $parts[5] : (isset($parts[2]) && in_array($stageKind, ['stage', 'swiss'], true) ? (int) $parts[2] : null);
        $placementMax = isset($parts[6]) ? (int) $parts[6] : (isset($parts[3]) && in_array($stageKind, ['stage', 'swiss'], true) ? (int) $parts[3] : null);

        if ($placementMin !== null && $placementMax !== null && $placementMin > 0 && $placementMax > 0) {
            return [
                'min' => min($placementMin, $placementMax),
                'max' => max($placementMin, $placementMax),
            ];
        }

        return null;
    }
}
