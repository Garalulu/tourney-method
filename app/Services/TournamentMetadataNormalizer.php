<?php

namespace App\Services;

use App\Models\Tournament;
use Carbon\Carbon;

class TournamentMetadataNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, bool $inferLegacyFormat = true): array
    {
        $data = $this->normalizeDates($data);
        $data = $this->normalizeRestrictedCountries($data);
        $data = $this->normalizeBadgeUrls($data);
        $data = $this->normalizeModes($data);

        return $this->normalizeFormatData($data, $inferLegacyFormat);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeDates(array $data): array
    {
        $dateTimeFields = [
            'registration_start',
            'registration_end',
            'tournament_start',
            'tournament_end',
        ];
        $dateFields = [
            ...$dateTimeFields,
            'bws_badge_age_cutoff',
        ];

        foreach ($dateFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                continue;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data[$field])) {
                $defaultTime = in_array($field, $dateTimeFields, true) ? '12:00:00' : '00:00:00';
                $data[$field] .= ' '.$defaultTime;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $data[$field])) {
                $data[$field] = str_replace('T', ' ', (string) $data[$field]).':00';
            }

            try {
                Carbon::parse((string) $data[$field]);
            } catch (\Exception) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRestrictedCountries(array $data): array
    {
        if (! array_key_exists('restricted_countries', $data)) {
            return $data;
        }

        if ($data['restricted_countries'] === '' || $data['restricted_countries'] === null) {
            $data['restricted_countries'] = [];
        } elseif (is_string($data['restricted_countries'])) {
            $data['restricted_countries'] = preg_split('/[\s,]+/', $data['restricted_countries']) ?: [];
        }

        if (is_array($data['restricted_countries'])) {
            $data['restricted_countries'] = array_values(array_unique(array_map(
                fn ($code): string => strtoupper((string) $code),
                array_filter($data['restricted_countries'], fn ($code): bool => filled($code))
            )));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBadgeUrls(array $data): array
    {
        if (! array_key_exists('badge_urls', $data)) {
            return $data;
        }

        $values = is_array($data['badge_urls']) ? $data['badge_urls'] : [$data['badge_urls']];
        $isPlacementKeyed = collect(array_keys($values))
            ->contains(fn ($key): bool => in_array((string) $key, ['1', '2', '3'], true));

        if (! $isPlacementKeyed) {
            $values = [1 => $values];
        }

        $data['badge_urls'] = collect([1, 2, 3])
            ->mapWithKeys(function (int $placement) use ($values): array {
                $placementValues = $values[$placement] ?? $values[(string) $placement] ?? [];
                $placementValues = is_array($placementValues) ? $placementValues : [$placementValues];

                $urls = collect($placementValues)
                    ->flatMap(fn ($value): array => preg_split('/[\r\n,]+/', (string) $value) ?: [])
                    ->map(fn (string $url): string => trim($url))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return $urls === [] ? [] : [(string) $placement => $urls];
            })
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeModes(array $data): array
    {
        if (! array_key_exists('modes', $data) && ! array_key_exists('mania_variants', $data)) {
            return $data;
        }

        $modes = $data['modes'] ?? [];
        $modes = is_array($modes) ? $modes : [];
        $maniaVariants = isset($data['mania_variants']) && is_array($data['mania_variants'])
            ? array_values(array_filter($data['mania_variants'], fn ($variant): bool => filled($variant)))
            : [];

        $normalizedModes = collect($modes)
            ->map(function ($mode) use ($maniaVariants): ?array {
                $modeName = data_get($mode, 'mode', $mode);
                if (! is_string($modeName) || $modeName === '') {
                    return null;
                }

                if ($modeName === 'mania' && $maniaVariants !== []) {
                    return null;
                }

                $keyCount = data_get($mode, 'key_count');

                return [
                    'mode' => $modeName,
                    'key_count' => is_numeric($keyCount) ? (int) $keyCount : null,
                ];
            })
            ->filter()
            ->values();

        foreach ($maniaVariants as $variant) {
            $keyCount = match ($variant) {
                'mania_4k' => 4,
                'mania_7k' => 7,
                'mania_other' => null,
                default => null,
            };

            $normalizedModes->push(['mode' => 'mania', 'key_count' => $keyCount]);
        }

        $data['modes'] = $normalizedModes
            ->unique(fn (array $mode): string => $mode['mode'].':'.($mode['key_count'] ?? 'null'))
            ->values()
            ->all();
        unset($data['mania_variants']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFormatData(array $data, bool $inferLegacyFormat): array
    {
        $hasFormatInput = collect([
            'team_formation_style',
            'format_tags',
            'format_structure',
            'start_round_size',
            'format',
        ])->contains(fn (string $field): bool => array_key_exists($field, $data));

        if (! $hasFormatInput) {
            return $data;
        }

        $teamFormationStyle = strtolower(str_replace('-', '_', (string) ($data['team_formation_style'] ?? Tournament::TEAM_FORMATION_STANDARD)));
        $data['team_formation_style'] = $teamFormationStyle !== '' ? $teamFormationStyle : Tournament::TEAM_FORMATION_STANDARD;

        $tags = collect($data['format_tags'] ?? [])
            ->map(fn ($tag): string => strtolower(str_replace('-', '_', (string) $tag)))
            ->filter()
            ->values();

        if (in_array($data['team_formation_style'], [
            Tournament::TEAM_FORMATION_DRAFT,
            Tournament::TEAM_FORMATION_AUCTION,
            Tournament::TEAM_FORMATION_WORLD_CUP,
            Tournament::TEAM_FORMATION_SUIJI,
        ], true)) {
            $tags->push($data['team_formation_style']);
        }

        $hasExplicitFormatStructure = array_key_exists('format_structure', $data)
            && is_array($data['format_structure'] ?? null);
        $formatStructure = $hasExplicitFormatStructure
            ? $data['format_structure']
            : [];

        $stages = collect($formatStructure['stages'] ?? [])
            ->filter(fn ($stage): bool => is_array($stage))
            ->map(fn (array $stage): ?array => $this->normalizeFormatStage($stage))
            ->filter()
            ->values()
            ->all();

        if ($inferLegacyFormat && $stages === [] && filled($data['start_round_size'] ?? null)) {
            $stages[] = [
                'type' => Tournament::STAGE_BRACKET,
                'start_round_size' => (int) $data['start_round_size'],
                'entry_type' => Tournament::BRACKET_ENTRY_WINNER_ONLY,
                'elimination_type' => Tournament::BRACKET_ELIMINATION_DOUBLE,
            ];
        }

        if ($inferLegacyFormat && $stages === [] && filled($data['format'] ?? null)) {
            $legacyFormat = strtolower((string) $data['format']);
            $stages[] = match (true) {
                str_contains($legacyFormat, 'battle royale') => [
                    'type' => Tournament::STAGE_BATTLE_ROYALE,
                    'name' => 'Battle Royale',
                    'legacy_format' => $data['format'],
                ],
                str_contains($legacyFormat, 'swiss') => [
                    'type' => Tournament::STAGE_SWISS,
                    'legacy_format' => $data['format'],
                ],
                default => [
                    'type' => Tournament::STAGE_BRACKET,
                    'entry_type' => Tournament::BRACKET_ENTRY_WINNER_ONLY,
                    'elimination_type' => Tournament::BRACKET_ELIMINATION_DOUBLE,
                    'legacy_format' => in_array($legacyFormat, ['round robin', 'stage-based'], true)
                        ? 'Double Elimination'
                        : $data['format'],
                ],
            };
        }

        $firstBracketStage = collect($stages)->firstWhere('type', Tournament::STAGE_BRACKET);
        if (is_array($firstBracketStage) && filled($firstBracketStage['start_round_size'] ?? null)) {
            $data['start_round_size'] = (int) $firstBracketStage['start_round_size'];
        } elseif ($hasExplicitFormatStructure) {
            $data['start_round_size'] = null;
        }

        if (collect($stages)->contains(fn (array $stage): bool => ($stage['type'] ?? null) === Tournament::STAGE_BATTLE_ROYALE)) {
            $tags->push(Tournament::FORMAT_TAG_BATTLE_ROYALE);
        }

        $data['format_tags'] = $tags
            ->filter(fn (string $tag): bool => array_key_exists($tag, Tournament::searchableFormatTagLabels()))
            ->unique()
            ->values()
            ->all();

        $data['format_structure'] = [
            'stages' => $stages,
        ];

        if ($inferLegacyFormat && filled($data['format'] ?? null)) {
            $data['format_structure']['legacy_format'] = $data['format'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<string, mixed>|null
     */
    private function normalizeFormatStage(array $stage): ?array
    {
        $type = strtolower(str_replace('-', '_', (string) ($stage['type'] ?? '')));

        if (! in_array($type, [
            Tournament::STAGE_QUALIFIER,
            Tournament::STAGE_GROUP,
            Tournament::STAGE_SWISS,
            Tournament::STAGE_BRACKET,
            Tournament::STAGE_BATTLE_ROYALE,
        ], true)) {
            return null;
        }

        $normalized = ['type' => $type];

        foreach (['name', 'notes', 'legacy_format', 'elimination_rule', 'win_condition'] as $stringField) {
            if (filled($stage[$stringField] ?? null)) {
                $normalized[$stringField] = trim((string) $stage[$stringField]);
            }
        }

        foreach ([
            'advance_count',
            'round_count',
            'group_count',
            'teams_per_group',
            'start_round_size',
            'lobby_count',
            'players_per_lobby',
            'advance_per_lobby',
            'eliminated_per_map',
        ] as $integerField) {
            if (filled($stage[$integerField] ?? null)) {
                $normalized[$integerField] = (int) $stage[$integerField];
            }
        }

        if ($type === Tournament::STAGE_BRACKET) {
            $entryType = (string) ($stage['entry_type'] ?? Tournament::BRACKET_ENTRY_WINNER_ONLY);
            $normalized['entry_type'] = in_array($entryType, [
                Tournament::BRACKET_ENTRY_WINNER_ONLY,
                Tournament::BRACKET_ENTRY_WINNER_LOSER_HYBRID,
            ], true) ? $entryType : Tournament::BRACKET_ENTRY_WINNER_ONLY;

            $eliminationType = (string) ($stage['elimination_type'] ?? Tournament::BRACKET_ELIMINATION_DOUBLE);
            $normalized['elimination_type'] = in_array($eliminationType, [
                Tournament::BRACKET_ELIMINATION_SINGLE,
                Tournament::BRACKET_ELIMINATION_DOUBLE,
            ], true) ? $eliminationType : Tournament::BRACKET_ELIMINATION_DOUBLE;

            if ($normalized['elimination_type'] === Tournament::BRACKET_ELIMINATION_SINGLE) {
                $normalized['entry_type'] = Tournament::BRACKET_ENTRY_WINNER_ONLY;
            }
        }

        return $normalized;
    }
}
