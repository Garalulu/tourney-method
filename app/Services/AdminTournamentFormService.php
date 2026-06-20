<?php

namespace App\Services;

class AdminTournamentFormService
{
    /**
     * Fields that should be marked manual when edited from the admin form.
     *
     * @var array<int, string>
     */
    private const MANUAL_FIELDS = [
        'title',
        'description',
        'host_osu_id',
        'host_username',
        'modes',
        'team_size_min',
        'team_size_max',
        'vs_size',
        'registration_start',
        'registration_end',
        'tournament_start',
        'tournament_end',
        'rank_range_min',
        'rank_range_max',
        'is_badge',
        'is_bws',
        'banner_url',
        'discord_url',
        'twitch_url',
        'spreadsheet_url',
        'bracket_url',
        'registration_url',
        'star_rating_min',
        'star_rating_max',
        'star_rating_first',
        'star_rating_last',
        'star_rating_qualifier',
        'format',
        'team_formation_style',
        'format_tags',
        'format_structure',
        'start_round_size',
        'restricted_countries',
        'badge_status',
        'forum_topic_id',
        'forum_post_url',
    ];

    /**
     * Normalize admin forum/wiki URL input into stored tournament fields.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function normalizeForumLink(array $validated): array
    {
        if (! isset($validated['forum_post_url']) || empty($validated['forum_post_url'])) {
            return $validated;
        }

        if (preg_match('/forums\/topics\/(\d+)/', (string) $validated['forum_post_url'], $matches)) {
            $validated['forum_topic_id'] = (int) $matches[1];
            $validated['forum_post_url'] = null;

            return $validated;
        }

        $validated['forum_topic_id'] = null;

        return $validated;
    }

    /**
     * Build field source metadata for manual admin values.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    public function manualFieldSources(array $validated): array
    {
        return $this->mergeManualFieldSources([], $validated, true);
    }

    /**
     * Merge manual field source metadata into existing field sources.
     *
     * @param  array<string, string|null>  $currentFieldSources
     * @param  array<string, mixed>  $validated
     * @return array<string, string|null>
     */
    public function mergeManualFieldSources(array $currentFieldSources, array $validated, bool $creation = false): array
    {
        foreach (self::MANUAL_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($creation && ! $this->isMeaningfulCreateValue($field, $validated[$field])) {
                continue;
            }

            $currentFieldSources[$field] = 'manual';
        }

        return $currentFieldSources;
    }

    /**
     * Fresh create forms submit empty/default controls for many fields. Only
     * values an admin meaningfully supplied should block the initial parser.
     */
    private function isMeaningfulCreateValue(string $field, mixed $value): bool
    {
        if (in_array($field, ['title', 'modes'], true)) {
            return true;
        }

        if (in_array($field, ['is_badge', 'is_bws'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
