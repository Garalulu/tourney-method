<?php

namespace App\Services;

use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ForumParser
{
    // Precompiled regex patterns (compiled once on class load)

    // Format 1: Profile tag pattern: [profile=123]Username[/profile]
    private const PROFILE_TAG_PATTERN = '/\[profile=([1-9]\d*)\](.*?)\[\/profile\]/is';

    /**
     * Parse a forum topic and extract tournament data
     *
     * @param  int  $topicId  The forum topic ID
     * @param  string|null  $postContent  Optional pre-fetched post content (can be BBcode)
     * @param  bool  $strict  If true, validates that the post looks like a tournament
     * @return array<string, mixed>|null Parsed tournament data or null if not a tournament
     */
    public function parseForumTopic(int $topicId, ?string $postContent = null, bool $strict = true): ?array
    {
        try {
            // Fetch forum topic data from osu! API
            $topicData = $this->fetchForumTopic($topicId);

            if ($topicData === null) {
                return null;
            }

            return $this->parseForumTopicData($topicId, $topicData, $postContent, $strict);

        } catch (\Exception $e) {
            Log::error('Failed to parse forum topic', [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse already-fetched forum topic data.
     *
     * @param  array<string, mixed>  $topicData
     * @return array<string, mixed>|null
     */
    public function parseForumTopicData(int $topicId, array $topicData, ?string $postContent = null, bool $strict = true): ?array
    {
        try {
            // Handle nested response structure: {topic: {...}, posts: [...]}
            $topic = $topicData['topic'] ?? $topicData;
            $posts = $topicData['posts'] ?? [];

            // Use provided content or fetch from API
            $rawContent = $postContent;
            if ($rawContent === null) {
                // Extract from posts structure
                $post = $posts[0] ?? [];
                $body = $post['body'] ?? null;
                if ($body !== null && is_array($body)) {
                    // osu! API returns body as {html: "...", raw: "..."}
                    // Store raw BBcode for later parsing
                    $rawContent = $body['raw'] ?? $body['html'] ?? null;
                } elseif (is_string($body)) {
                    $rawContent = $body;
                }
            }

            if ($rawContent === null) {
                Log::warning('Forum topic has no content', ['topic_id' => $topicId]);

                return null;
            }

            // Initialize BBcode parser
            $bbcodeParser = app(BbcodeParser::class);

            // Strip BBcode for content analysis (modes, ranks, etc)
            // but keep raw BBcode for description
            $content = $bbcodeParser->toMarkdown($rawContent);

            // Extract all tournament fields
            $data = [
                'forum_topic_id' => $topicId,
                'title' => $topic['title'] ?? '',
                'host_osu_id' => $topic['user_id'] ?? null,
                'host_username' => null,
                'modes' => [],
                'team_size_min' => null,
                'team_size_max' => null,
                'vs_size' => null,
                'registration_start' => null,
                'registration_end' => null,
                'tournament_start' => null,
                'tournament_end' => null,
                'rank_range_min' => null,
                'rank_range_max' => null,
                'rank_range_is_open' => false,
                'is_badge' => false,
                'is_bws' => false,
                'team_formation_style' => Tournament::TEAM_FORMATION_STANDARD,
                'discord_url' => null,
                'twitch_url' => null,
                'spreadsheet_url' => null,
                'bracket_url' => null,
                'registration_url' => null,
                'tcomm_url' => null,
                // Store raw BBcode in description
                'description' => $rawContent,
            ];

            // Parse individual fields (include title in mode and rank detection)
            $titleAndContent = $data['title'].' '.$content;
            $data = array_merge($data, $this->parseHost($content));
            $data = array_merge($data, $this->parseModes($titleAndContent));
            $data = array_merge($data, $this->parseDates($content, $data['title'], $topic['created_at'] ?? null));
            $data = array_merge($data, $this->parseRankRange($titleAndContent));
            $data = array_merge($data, $this->parseTournamentStructure($titleAndContent));
            $data = array_merge($data, $this->parseTeamFormationStyle($titleAndContent));
            $data = array_merge($data, $this->parseBws($titleAndContent));
            $data = array_merge($data, $this->parseBadgeDetection($content));
            // Use BBcode parser for more accurate link extraction
            $data = array_merge($data, $this->parseLinksFromBbcode($rawContent));

            // Validate this is actually a tournament post
            if ($strict && ! $this->isTournamentPost($data)) {
                return null;
            }

            // Even if verification failed, we return the data if not in strict mode
            // This ensures OTR-imported tournaments still get their metadata enriched
            if (! $strict && ! $this->isTournamentPost($data)) {
                Log::info('Successfully parsed forum topic (verification bypassed)', [
                    'topic_id' => $topicId,
                    'title' => $data['title'],
                    'strict_mode' => $strict,
                ]);
            } else {
                Log::info('Successfully parsed forum topic', [
                    'topic_id' => $topicId,
                    'title' => $data['title'],
                    'is_badge' => $data['is_badge'],
                    'strict_mode' => $strict,
                ]);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Failed to parse forum topic', [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch forum topic from osu! API via OsuApiService
     *
     * @return array<string, mixed>|null
     */
    private function fetchForumTopic(int $topicId): ?array
    {
        $osuApi = app(OsuApiService::class);

        return $osuApi->getForumTopic($topicId);
    }

    /**
     * Determine if this is a tournament post
     *
     * @param  array<string, mixed>  $data
     */
    private function isTournamentPost(array $data): bool
    {
        // If we have a forum topic ID, we assume it's a valid topic from an OTR import or similar
        if (! empty($data['forum_topic_id'])) {
            return true;
        }

        // Must have at least one mode detected
        if (empty($data['modes'])) {
            return false;
        }

        // Must have some tournament-like content
        $keywords = ['tournament', 'registration', 'bracket', 'mappool', 'sign-up', 'signup'];
        $content = strtolower($data['title'].' '.$data['description']);

        foreach ($keywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse host username from content
     *
     * @return array{host_username: string|null}
     */
    public function parseHost(string $content): array
    {
        $patterns = [
            '~\bHost(?:ed by)?:?\s*@(\w+)~i',
            '~\bOrganiser?:?\s*@(\w+)~i',
            '~\bOrganizer?:?\s*@(\w+)~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return ['host_username' => $matches[1]];
            }
        }

        return ['host_username' => null];
    }

    /**
     * Parse game modes from content
     * Detects common abbreviations: STD, o!std, o!t, o!m, etc.
     *
     * @return array{modes: array<int, array{mode: string, key_count: int|null}>}
     */
    public function parseModes(string $content): array
    {
        $modes = [];
        $detectedAbbreviations = [];
        $contentLower = strtolower($content);

        // Full mode names
        $modeMap = [
            'osu!' => 'osu',
            'osu' => 'osu',
            'taiko' => 'taiko',
            'catch' => 'catch',
            'fruits' => 'catch',
            'mania' => 'mania',
        ];

        // Common abbreviations (case-insensitive)
        $abbreviations = [
            'std' => 'osu',        // [STD] or just STD
            'o!std' => 'osu',      // osu!std
            'o!t' => 'taiko',      // o!t
            'o!m' => 'mania',      // o!m
            'o!c' => 'catch',      // o!c (rare but possible)
            '4k' => 'mania_4k',   // 4K mania (enhanced format marker)
            '7k' => 'mania_7k',   // 7K mania (enhanced format marker)
        ];

        // Track detected mania variants
        $maniaVariants = [];

        // Check for mania key count abbreviations first (higher priority)
        foreach ($abbreviations as $abbr => $mode) {
            // Use word boundaries to avoid false matches
            if (preg_match('/\b'.preg_quote($abbr, '/').'\b/i', $contentLower)) {
                if ($abbr === '4k' || $abbr === '7k') {
                    // Track mania variant for later enhancement
                    $maniaVariants[] = $abbr;
                } elseif (! in_array($mode, $modes, true)) {
                    $modes[] = $mode;
                    $detectedAbbreviations[] = $abbr;
                }
            }
        }

        // Check for full mode keywords with exclusion logic
        foreach ($modeMap as $keyword => $mode) {
            // Skip if already detected via abbreviation
            if (in_array($mode, $modes, true)) {
                continue;
            }

            // Special handling for "osu" keyword - exclude when specific abbreviations detected
            if ($mode === 'osu') {
                // If o!m, o!t, or o!c detected, exclude "osu!" unless explicitly part of "Mode:" list
                if (in_array('mania', $modes, true) || in_array('taiko', $modes, true) || in_array('catch', $modes, true)) {
                    // Skip "osu" detection entirely when specific mode abbreviation found
                    // The "osu" keyword is too ambiguous and appears in "osu!mania", "osu! Supporter", etc.
                    continue;
                }
            }

            if (preg_match('/\b'.$keyword.'\b/i', $content)) {
                if (! in_array($mode, $modes, true)) {
                    $modes[] = $mode;
                }
            }
        }

        // Also look for "Mode(s):" patterns
        if (preg_match('/modes?:\s*([^\n]+)/i', $content, $matches)) {
            $modeList = strtolower($matches[1]);
            foreach ($modeMap as $keyword => $mode) {
                if (str_contains($modeList, $keyword)) {
                    if (! in_array($mode, $modes, true)) {
                        $modes[] = $mode;
                    }
                }
            }
            // Check abbreviations in mode list too
            foreach ($abbreviations as $abbr => $mode) {
                if (str_contains($modeList, $abbr)) {
                    if (! in_array($mode, $modes, true)) {
                        $modes[] = $mode;
                    }
                }
            }
        }

        // Enhance modes array with mania key count information
        $enhancedModes = [];
        foreach (array_values(array_unique($modes)) as $mode) {
            // Check if this mode was detected with a specific key count
            if ($mode === 'mania' && ! empty($maniaVariants)) {
                // Add enhanced mania variants
                foreach ($maniaVariants as $variant) {
                    $keyCount = match ($variant) {
                        '4k' => 4,
                        '7k' => 7,
                        default => null,
                    };
                    $enhancedModes[] = ['mode' => 'mania', 'key_count' => $keyCount];
                }
            } else {
                // ALL modes should use new structure
                $enhancedModes[] = ['mode' => $mode, 'key_count' => null];
            }
        }

        return ['modes' => $enhancedModes];
    }

    /**
     * Parse mania key count from content
     * Looks for patterns like "4k mania", "mania 7k", "4-key", etc.
     *
     * @return int|null Key count (4, 7, 8, 9) or null if not found
     */
    protected function parseManiaKeyCount(string $text): ?int
    {
        // Look for patterns like "4k mania", "mania 7k", "4-key", etc.
        if (preg_match('/(\d)\s*[kK]/i', $text, $matches)) {
            $keyCount = (int) $matches[1];

            // Validate it's a reasonable key count
            if (in_array($keyCount, [4, 7, 8, 9])) {
                return $keyCount;
            }
        }

        return null;
    }

    /**
     * Parse banner URL from BBCode content
     * Supports [image], [img], and [imagemap] tags
     *
     * @return string|null Banner URL or null if not found
     */
    public function parseBanner(string $bbcode): ?string
    {
        // Priority order: [imagemap] -> [img] -> [image]

        // [imagemap] - extract URL from first line (before coordinates)
        if (preg_match('/\[imagemap\]\s*(https?:\/\/[^\s\n]+)/i', $bbcode, $matches)) {
            $url = trim($matches[1]);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        // [img] tag
        if (preg_match('/\[img\]([^\[]+)\[\/img\]/i', $bbcode, $matches)) {
            $url = trim($matches[1]);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        // [image] tag (alternative BBCode format)
        if (preg_match('/\[image\]([^\[]+)\[\/image\]/i', $bbcode, $matches)) {
            $url = trim($matches[1]);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Parse dates from content
     *
     * @return array{registration_start: string|null, registration_end: string|null, tournament_start: string|null, tournament_end: string|null}
     */
    public function parseDates(string $content, ?string $title = null, ?string $topicCreatedAt = null): array
    {
        $result = [
            'registration_start' => null,
            'registration_end' => null,
            'tournament_start' => null,
            'tournament_end' => null,
        ];
        $fallbackYear = $this->inferTournamentYear($title, $topicCreatedAt);

        // Date patterns to try
        $patterns = [
            // DD/MM/YYYY or DD-MM-YYYY
            '/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/',
            // Month DD, YYYY
            '/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(\d{4})/i',
            // YYYY-MM-DD (ISO format)
            '/(\d{4})-(\d{2})-(\d{2})/',
            // DD Month YYYY
            '/(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i',
        ];

        // Look for registration dates
        if (preg_match('/registration[:\s]+(.*?)(?:\n|$)/i', $content, $matches)) {
            $dateRange = $this->parseDateRange($matches[1], $patterns, $fallbackYear);
            if ($dateRange !== null) {
                $result['registration_start'] = $dateRange['start'];
                $result['registration_end'] = $dateRange['end'];
            }
        }

        // Look for tournament dates
        if (preg_match('/tournament[:\s]+(.*?)(?:\n|$)/i', $content, $matches)) {
            $dateRange = $this->parseDateRange($matches[1], $patterns, $fallbackYear);
            if ($dateRange !== null) {
                $result['tournament_start'] = $dateRange['start'];
                $result['tournament_end'] = $dateRange['end'];
            }
        }

        foreach ($this->parseScheduleDateLines($content, $patterns, $fallbackYear) as $label => $dateRange) {
            if ($label === 'registration') {
                $result['registration_start'] ??= $dateRange['start'];
                $result['registration_end'] ??= $dateRange['end'];

                continue;
            }

            $result['tournament_start'] = $this->earliestDate($result['tournament_start'], $dateRange['start']);
            $result['tournament_end'] = $this->latestDate($result['tournament_end'], $dateRange['end'] ?? $dateRange['start']);
        }

        return $result;
    }

    /**
     * Parse a date range string
     *
     * @param  array<string>  $patterns
     * @return array{start: string|null, end: string|null}|null
     */
    private function parseDateRange(string $dateString, array $patterns, ?int $fallbackYear = null): ?array
    {
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $dateString, $matches, PREG_SET_ORDER);

            if (count($matches) >= 2) {
                return [
                    'start' => $this->normalizeDate($matches[0]),
                    'end' => $this->normalizeDate($matches[1]),
                ];
            } elseif (count($matches) === 1) {
                // Only one date found
                return [
                    'start' => $this->normalizeDate($matches[0]),
                    'end' => null,
                ];
            }
        }

        if ($fallbackYear !== null) {
            return $this->parseMonthDayRange($dateString, $fallbackYear);
        }

        return null;
    }

    /**
     * @param  array<string>  $patterns
     * @return array<string, array{start: string|null, end: string|null}>
     */
    private function parseScheduleDateLines(string $content, array $patterns, ?int $fallbackYear): array
    {
        $schedule = [];
        $labels = 'registration|qualifiers?|round of 32|round of 16|quarter finals?|semi finals?|grand finals?|finals?';

        if (! preg_match_all("/(?:^|\\n).*?\\b({$labels})\\b.*?:\\s*([^\\n]+)/i", $content, $matches, PREG_SET_ORDER)) {
            return $schedule;
        }

        foreach ($matches as $match) {
            $label = strtolower($match[1]);
            $key = str_starts_with($label, 'registration') ? 'registration' : $label;
            $dateRange = $this->parseDateRange($match[2], $patterns, $fallbackYear);

            if ($dateRange !== null) {
                $schedule[$key] = $dateRange;
            }
        }

        return $schedule;
    }

    /**
     * @return array{start: string|null, end: string|null}|null
     */
    private function parseMonthDayRange(string $dateString, int $fallbackYear): ?array
    {
        $monthDay = '(January|February|March|April|May|June|July|August|September|October|November|December)\\s+(\\d{1,2})(?:st|nd|rd|th)?';

        if (! preg_match_all("/{$monthDay}/i", $dateString, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $start = $this->normalizeMonthDay($matches[0][1], (int) $matches[0][2], $fallbackYear);
        $end = null;

        if (isset($matches[1])) {
            $endYear = $fallbackYear;
            if ($start !== null) {
                $startDate = Carbon::parse($start);
                $candidateEnd = Carbon::createFromDate($fallbackYear, $this->monthNumber($matches[1][1]), (int) $matches[1][2])->startOfDay();
                if ($candidateEnd->lt($startDate)) {
                    $endYear++;
                }
            }

            $end = $this->normalizeMonthDay($matches[1][1], (int) $matches[1][2], $endYear);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function normalizeMonthDay(string $month, int $day, int $year): ?string
    {
        try {
            return Carbon::createFromDate($year, $this->monthNumber($month), $day)
                ->startOfDay()
                ->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function monthNumber(string $month): int
    {
        return (int) Carbon::parse("1 {$month} 2000")->format('n');
    }

    private function inferTournamentYear(?string $title, ?string $topicCreatedAt): ?int
    {
        if ($title !== null && preg_match('/\b(20\d{2}|19\d{2})\b/', $title, $matches)) {
            return (int) $matches[1];
        }

        if ($topicCreatedAt !== null) {
            try {
                return Carbon::parse($topicCreatedAt)->year;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function earliestDate(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null) {
            return $candidate;
        }

        return Carbon::parse($candidate)->lt(Carbon::parse($current)) ? $candidate : $current;
    }

    private function latestDate(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null) {
            return $candidate;
        }

        return Carbon::parse($candidate)->gt(Carbon::parse($current)) ? $candidate : $current;
    }

    /**
     * Normalize date string to ISO format
     *
     * @param  array<int, string>  $matches
     */
    private function normalizeDate(array $matches): ?string
    {
        try {
            // Check the pattern type and construct date accordingly

            // If we have 4 elements and index 3 is a 4-digit year, assume DD/MM/YYYY
            if (count($matches) >= 4 && strlen($matches[3] ?? '') === 4 && is_numeric($matches[1] ?? '')) {
                $format = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                $date = Carbon::parse($format);

                return $date->toIso8601String();
            }

            // Month DD, YYYY format (e.g., "February 1, 2024")
            // matches[1] = month name, matches[2] = day, matches[3] = year
            $monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            if (count($matches) >= 4 && in_array($matches[1] ?? '', $monthNames, true)) {
                $monthNum = array_search($matches[1], $monthNames, true) + 1;
                $format = sprintf('%s-%02d-%s', $matches[3], $monthNum, $matches[2]);
                $date = Carbon::parse($format);

                return $date->toIso8601String();
            }

            // Try parsing with Carbon directly
            $date = Carbon::parse(implode('-', $matches));

            return $date->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse rank range from content
     *
     * @return array{rank_range_min: int|null, rank_range_max: int|null, rank_range_is_open: bool}
     */
    public function parseRankRange(string $content): array
    {
        $result = [
            'rank_range_min' => null,
            'rank_range_max' => null,
            'rank_range_is_open' => false,
        ];

        if (preg_match('/\bopen\s+rank\b/i', $content)) {
            $result['rank_range_is_open'] = true;

            return $result;
        }

        // Pattern: #X-#Y, X-Y, X to Y, 1K-10K, etc.
        // Updated to handle commas: 10,000 - 99,999
        // Limit to reasonable rank values (< 10 million) to avoid matching Discord snowflake IDs
        $patterns = [
            '/#?(\d{1,7}(?:,\d{3})*(?:[KkMm])?|[\d,]+[KkMm]?)\s*(?:-|–|—|\bto\b)\s*#?(\d{1,7}(?:,\d{3})*(?:[KkMm])?|[\d,]+[KkMm]?)/i',
            '/rank[:\s]+#?(\d{1,7}(?:,\d{3})*(?:[KkMm])?|[\d,]+[KkMm]?)\s*(?:-|–|—|\bto\b)\s*#?(\d{1,7}(?:,\d{3})*(?:[KkMm])?|[\d,]+[KkMm]?)/i',
        ];

        foreach ($patterns as $pattern) {
            // Find ALL matches, not just the first one
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rank1 = $this->parseRankValue($match[1]);
                    $rank2 = $this->parseRankValue($match[2]);

                    // Skip if either value is invalid (null)
                    if ($rank1 === null || $rank2 === null) {
                        continue;
                    }

                    // Ensure min is always the smaller value
                    $result['rank_range_min'] = min($rank1, $rank2);
                    $result['rank_range_max'] = max($rank1, $rank2);

                    // Found valid range, stop searching
                    break 2;
                }
            }
        }

        // "under X" or "below X"
        if (preg_match('/(?:under|below)[:\s]+#?([\d,]+[KkMm]?)/i', $content, $matches)) {
            $rank = $this->parseRankValue($matches[1]);
            if ($rank !== null) {
                $result['rank_range_max'] = $rank;
            }
        }

        // "above Y" or "higher than Y"
        if (preg_match('/(?:above|higher than)[:\s]+#?([\d,]+[KkMm]?)/i', $content, $matches)) {
            $rank = $this->parseRankValue($matches[1]);
            if ($rank !== null) {
                $result['rank_range_min'] = $rank;
            }
        }

        return $result;
    }

    /**
     * Parse team formation style from title and content.
     *
     * @return array{team_formation_style: string}
     */
    public function parseTeamFormationStyle(string $content): array
    {
        $matches = [];
        $patterns = [
            Tournament::TEAM_FORMATION_WORLD_CUP => '/\bworld\s+cup\b/i',
            Tournament::TEAM_FORMATION_SUIJI => '/\bsuiji\b/i',
            Tournament::TEAM_FORMATION_DRAFT => '/\bdraft\b/i',
            Tournament::TEAM_FORMATION_AUCTION => '/\bauction\b/i',
        ];

        foreach ($patterns as $style => $pattern) {
            if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
                $matches[$style] = $match[0][1];
            }
        }

        if ($matches === []) {
            return ['team_formation_style' => Tournament::TEAM_FORMATION_STANDARD];
        }

        asort($matches, SORT_NUMERIC);

        return ['team_formation_style' => array_key_first($matches)];
    }

    /**
     * Parse rank value with K/M suffix
     * Handles commas (e.g., "10,000" -> 10000, "1.5M" -> 1500000)
     * Rejects values larger than PostgreSQL int4 max (2,147,483,647)
     */
    private function parseRankValue(string $value): ?int
    {
        // Remove commas and whitespace
        $value = strtoupper(trim(str_replace([',', ' '], '', $value)));

        // Convert K suffix to thousands
        if (str_ends_with($value, 'K')) {
            $numericValue = (float) rtrim($value, 'K') * 1000;
        } elseif (str_ends_with($value, 'M')) {
            $numericValue = (float) rtrim($value, 'M') * 1000000;
        } else {
            $numericValue = (float) $value;
        }

        // Validate: must be positive and within PostgreSQL int4 range
        $maxInt = 2147483647;
        if ($numericValue <= 0 || $numericValue > $maxInt) {
            return null; // Invalid rank value
        }

        return (int) $numericValue;
    }

    /**
     * Parse tournament structure details such as 2v2 and team size.
     *
     * @return array{vs_size: int|null, team_size_min: int|null, team_size_max: int|null}
     */
    public function parseTournamentStructure(string $content): array
    {
        $result = [
            'vs_size' => null,
            'team_size_min' => null,
            'team_size_max' => null,
        ];

        if (preg_match('/\b([1-9]\d?)\s*v\s*\1\b/i', $content, $matches)) {
            $result['vs_size'] = (int) $matches[1];
        }

        if (preg_match('/teams?\s+of\s+(\d+)\s*(?:-|–|—|to)\s*(\d+)\s+players?/i', $content, $matches)) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $result['team_size_min'] = min($first, $second);
            $result['team_size_max'] = max($first, $second);
        } elseif (preg_match('/teams?\s+of\s+(\d+)\s+players?/i', $content, $matches)) {
            $result['team_size_min'] = (int) $matches[1];
            $result['team_size_max'] = (int) $matches[1];
        }

        return $result;
    }

    /**
     * Detect Badge Weighted Seeding/rank references.
     *
     * @return array{is_bws: bool}
     */
    public function parseBws(string $content): array
    {
        return [
            'is_bws' => (bool) preg_match('/\b(?:BWS|Badge Weighted Seeding|Badge Weighted Rank(?:ing)?)\b/i', $content),
        ];
    }

    /**
     * Detect if this is a badge tournament
     *
     * @return array{is_badge: bool, tcomm_url: string|null}
     */
    public function parseBadgeDetection(string $content): array
    {
        $isBadge = false;
        $tcommUrl = null;

        // Check for "badge" keyword in content
        if (preg_match('/\bbadge\b/i', $content)) {
            $isBadge = true;
        }

        // Check for tcomm.hivie.tn link
        if (preg_match('(https?://tcomm\.hivie\.tn/[^\s]+)', $content, $matches)) {
            $isBadge = true;
            $tcommUrl = $matches[0];
        }

        // Check for PIF (Pending & Interesting Fora) link
        if (preg_match('(osu\.ppy\.sh/community/forums/topics/\d+)', $content, $matches)) {
            $isBadge = true;
        }

        return [
            'is_badge' => $isBadge,
            'tcomm_url' => $tcommUrl,
        ];
    }

    /**
     * Parse links from content
     *
     * @return array{discord_url: string|null, twitch_url: string|null, spreadsheet_url: string|null, bracket_url: string|null, registration_url: string|null}
     */
    public function parseLinks(string $content): array
    {
        return [
            'discord_url' => $this->extractUrl($content, [
                '~discord\.gg/[\w-]+~',
                '~discord\.com/invite/[\w-]+~',
            ]),
            'twitch_url' => $this->extractUrl($content, [
                '~twitch\.tv/[\w-]+~',
            ]),
            'spreadsheet_url' => $this->extractUrl($content, [
                '~docs\.google\.com/spreadsheets/d/[\w-]+~',
            ]),
            'bracket_url' => $this->extractUrl($content, [
                '~challonge\.com/[\w-]+~',
                '~brack\.net/[\w-]+~',
                '~braacket\.com/[^\s\]]+~',
            ]),
            'registration_url' => $this->extractUrl($content, [
                '~forms\.gle/[\w-]+~',
                '~docs\.google\.com/forms/d/[\w-]+~',
                '~typeform\.com/to/[\w-]+~',
            ]),
        ];
    }

    /**
     * Parse links from BBcode content using BbcodeParser
     *
     * @return array{discord_url: string|null, twitch_url: string|null, spreadsheet_url: string|null, bracket_url: string|null, registration_url: string|null}
     */
    public function parseLinksFromBbcode(string $bbcode): array
    {
        $bbcodeParser = app(BbcodeParser::class);

        $allUrls = $bbcodeParser->extractUrls($bbcode);

        $result = [
            'discord_url' => null,
            'twitch_url' => null,
            'spreadsheet_url' => null,
            'bracket_url' => null,
            'registration_url' => null,
        ];

        foreach ($allUrls as $url) {
            if ($this->isDiscordUrl($url)) {
                $result['discord_url'] = $result['discord_url'] ?? $url;
            }

            if ($this->isTwitchUrl($url)) {
                $result['twitch_url'] = $result['twitch_url'] ?? $url;
            }

            if ($this->isSpreadsheetUrl($url)) {
                $result['spreadsheet_url'] = $result['spreadsheet_url'] ?? $url;
            }

            if ($this->isBracketUrl($url)) {
                $result['bracket_url'] = $result['bracket_url'] ?? $url;
            }

            if ($this->isRegistrationUrl($url)) {
                $result['registration_url'] = $result['registration_url'] ?? $url;
            }
        }

        return $result;
    }

    private function isDiscordUrl(string $url): bool
    {
        return str_contains($url, 'discord.gg') || str_contains($url, 'discord.com/invite');
    }

    private function isTwitchUrl(string $url): bool
    {
        return str_contains($url, 'twitch.tv');
    }

    private function isSpreadsheetUrl(string $url): bool
    {
        return str_contains($url, 'docs.google.com/spreadsheets');
    }

    private function isBracketUrl(string $url): bool
    {
        return str_contains($url, 'challonge.com')
            || str_contains($url, 'brack.net')
            || str_contains($url, 'braacket.com');
    }

    private function isRegistrationUrl(string $url): bool
    {
        return str_contains($url, 'forms.gle')
            || str_contains($url, 'docs.google.com/forms')
            || str_contains($url, 'typeform.com');
    }

    /**
     * Extract URL using patterns
     *
     * @param  array<string>  $patterns
     */
    private function extractUrl(string $content, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return 'https://'.$matches[0];
            }
        }

        return null;
    }

    /**
     * Parse staff list from BBcode content
     * Extracts staff members with their roles and osu! user IDs
     * Returns array of staff arrays with keys: osu_id, username, role
     *
     * Handles role sections containing numeric profile tags or osu! user links:
     * 1. [profile=123]Username[/profile] - Profile tag format
     * 2. [*][color=...]Role:[/color]: users... - Color tag list format
     * 3. [b][color=...]Role:[/color][/b] users... - Nested bold+color format
     * 4. [b]Role:[/b] users... - Standard bold format
     * 5. [b]Role[/b] followed by users on the next line
     * 6. [*][b]Role: users...[/b] - Role and users inside one bold tag
     *
     * Plain usernames and username-based osu! URLs are intentionally rejected.
     *
     * @return array<int, array{osu_id: int, username: string, role: string}>
     */
    public function parseStaffFromBBcode(string $bbcode): array
    {
        if ($this->shouldSkipParsing($bbcode)) {
            return [];
        }

        return $this->deduplicateByOsuId($this->extractRoleSections($bbcode));
    }

    /**
     * Early exit validation to avoid expensive regex operations
     * Returns true if content should be skipped (invalid format)
     */
    private function shouldSkipParsing(string $bbcode): bool
    {
        // Early exit 1: Empty or too short
        if (empty($bbcode) || strlen($bbcode) < 20) {
            return true;
        }

        // Early exit 2: No supported identity tokens or role formatting.
        $hasRoleMarkers = str_contains($bbcode, '[b]')
            || str_contains($bbcode, '[/b]')
            || str_contains($bbcode, '[color=')
            || str_contains($bbcode, '[*]');
        if (! $hasRoleMarkers) {
            return true;
        }

        // Early exit 3: No supported numeric user links or profile tags.
        $hasUserLinks = str_contains($bbcode, 'osu.ppy.sh/users/');
        $hasProfileTags = str_contains($bbcode, '[profile=');

        if (! $hasUserLinks && ! $hasProfileTags) {
            return true;
        }

        return false;
    }

    /**
     * Extract numeric identities while carrying role context across adjacent lines.
     *
     * @return array<int, array{osu_id: int, username: string, role: string}>
     */
    private function extractRoleSections(string $bbcode): array
    {
        $staff = [];
        $currentRoles = [];

        foreach (preg_split('/\R/u', $bbcode) ?: [] as $line) {
            $header = $this->extractRoleHeader($line);
            $identities = $this->extractNumericIdentities($line);

            if ($header !== null) {
                $currentRoles = $header['roles'];
            } elseif ($this->isStandaloneBbcodeHeading($line)) {
                $currentRoles = [];
            } elseif ($identities === [] && trim($line) !== '') {
                $currentRoles = [];
            }

            if ($currentRoles === []) {
                continue;
            }

            foreach ($identities as $identity) {
                foreach ($currentRoles as $role) {
                    $staff[] = [
                        'osu_id' => $identity['osu_id'],
                        'username' => $identity['username'],
                        'role' => $role,
                    ];
                }
            }
        }

        return $staff;
    }

    /**
     * @return array{roles: array<int, string>}|null
     */
    private function extractRoleHeader(string $line): ?array
    {
        $hasIdentity = $this->containsNumericIdentity($line);
        $patterns = [
            // Role and identities are inside the same bold block.
            '/\[b\](?:\[color[^\]]*\])?\s*([^:\[\]]{1,80})\s*:\s*(?=.*(?:\[profile=[1-9]\d*\]|\[url=https?:\/\/osu\.ppy\.sh\/users\/[1-9]\d*))/iu',
            // Standard or nested bold/color header, with or without a colon.
            '/\[b\](?:\[color[^\]]*\])?\s*([^:\[\]]{1,80}?)\s*:?\s*(?:\[\/color\])?\[\/b\]\s*:?\s*/iu',
            // Color-only role header.
            '/\[color[^\]]*\]\s*([^:\[\]]{1,80}?)\s*:?\s*\[\/color\]\s*:?\s*/iu',
            // Plain role label, accepted only when a numeric identity is on the same line.
            '/^\s*(?:\[\*\]\s*)?([A-Za-z][A-Za-z0-9 &\/()_-]{1,80})\s*:\s*/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match($pattern, $line, $matches)) {
                continue;
            }

            if ($index === 3 && ! $hasIdentity) {
                continue;
            }

            $roles = $this->normalizeRoleNames($matches[1]);

            if ($roles !== []) {
                return ['roles' => $roles];
            }
        }

        return null;
    }

    private function containsNumericIdentity(string $line): bool
    {
        return preg_match('/\[profile=[1-9]\d*\]/i', $line) === 1
            || preg_match('/\[url=https?:\/\/osu\.ppy\.sh\/users\/[1-9]\d*(?:[\/?#][^\]\s]*)?\]/i', $line) === 1;
    }

    private function isStandaloneBbcodeHeading(string $line): bool
    {
        if ($this->containsNumericIdentity($line)) {
            return false;
        }

        return preg_match('/^\s*(?:\[[^\]]+\]\s*)*\[b\](?:\[[^\]]+\])?[^:\[\]]{1,100}:?(?:\[\/[^\]]+\])?\[\/b\](?:\s*\[\/?[^\]]+\])*\s*$/iu', $line) === 1;
    }

    /**
     * @return array<int, array{osu_id: int, username: string}>
     */
    private function extractNumericIdentities(string $line): array
    {
        $identities = [];

        if (preg_match_all(
            '/\[url=https?:\/\/osu\.ppy\.sh\/users\/([1-9]\d*)(?:[\/?#][^\]\s]*)?\](.*?)\[\/url\]/is',
            $line,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($matches as $match) {
                $identities[] = [
                    'offset' => $match[0][1],
                    'osu_id' => (int) $match[1][0],
                    'username' => $this->cleanUsername($match[2][0]),
                ];
            }
        }

        if (preg_match_all(self::PROFILE_TAG_PATTERN, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $identities[] = [
                    'offset' => $match[0][1],
                    'osu_id' => (int) $match[1][0],
                    'username' => $this->cleanUsername($match[2][0]),
                ];
            }
        }

        usort($identities, fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);

        return array_values(array_map(
            fn (array $identity): array => [
                'osu_id' => $identity['osu_id'],
                'username' => $identity['username'],
            ],
            array_filter(
                $identities,
                fn (array $identity): bool => $identity['osu_id'] > 0 && $identity['username'] !== ''
            )
        ));
    }

    /**
     * Deduplicate exact (osu_id, role) pairs and validate the final output boundary.
     *
     * @param  array<int, array{osu_id: int, username: string, role: string}>  $staff
     * @return array<int, array{osu_id: int, username: string, role: string}>
     */
    private function deduplicateByOsuId(array $staff): array
    {
        $grouped = [];
        $seen = [];

        foreach ($staff as $member) {
            $osuId = $member['osu_id'] ?? 0;
            $username = trim($member['username'] ?? '');
            $role = $member['role'] ?? '';

            if (! is_int($osuId) || $osuId <= 0 || $username === '' || $role === '') {
                continue;
            }

            $key = $osuId.':'.$role;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (! isset($grouped[$osuId])) {
                $grouped[$osuId] = [
                    'username' => $username,
                    'roles' => [],
                ];
            }

            $grouped[$osuId]['roles'][] = $role;
        }

        $result = [];

        foreach ($grouped as $osuId => $member) {
            foreach ($member['roles'] as $role) {
                $result[] = [
                    'osu_id' => $osuId,
                    'username' => $member['username'],
                    'role' => $role,
                ];
            }
        }

        return $result;
    }

    /**
     * Strip emoji and special unicode characters from role name
     * Helps with role matching when role names contain emoji
     */
    private function stripEmoji(string $text): string
    {
        // Remove emoji and other symbols (Unicode ranges for emoji)
        $text = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text); // Emoji
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Misc symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Dingbats

        return trim($text);
    }

    /**
     * Clean nested BBcode tags from username
     * Handles cases where usernames contain nested formatting like [color]...[/color]
     */
    private function cleanUsername(string $username): string
    {
        $username = preg_replace('/\[img[^\]]*\].*?\[\/img\]/is', '', $username);
        $username = preg_replace(
            '/\[(?:\/?(?:b|i|u|s|color|size|font|centre|center)(?:=[^\]]*)?)\]/i',
            '',
            $username
        );

        return trim($username);
    }

    /**
     * Expand combined English labels that explicitly assign multiple roles.
     *
     * @return array<int, string>
     */
    private function normalizeRoleNames(string $roleName): array
    {
        $normalizedName = strtolower($this->stripEmoji($roleName));
        $roles = [];

        if (preg_match('/\b(map\s*pool|mappool|poolers?|pooling|map\s*selectors?)\b/', $normalizedName) === 1) {
            $roles[] = 'mappooler';
        }

        if (preg_match('/\b(playtesters?|playtesting|replayers?|testers?|testing)\b/', $normalizedName) === 1) {
            $roles[] = 'playtester';
        }

        if (preg_match('/\b(streamers?|streams?)\b/', $normalizedName) === 1) {
            $roles[] = 'streamer';
        }

        if (preg_match('/\b(commentators?|commentary|casters?)\b/', $normalizedName) === 1) {
            $roles[] = 'commentator';
        }

        if (count($roles) > 1) {
            return array_values(array_unique($roles));
        }

        $role = $this->normalizeRoleName($roleName);

        return $role === null ? [] : [$role];
    }

    /**
     * Map recognized English staff role labels to database enum values.
     */
    private function normalizeRoleName(string $roleName): ?string
    {
        // Strip emoji before mapping
        $roleName = $this->stripEmoji($roleName);
        $roleName = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $roleName);
        $roleName = preg_replace('/\s+/', ' ', $roleName);

        $roleMap = [
            // Organizer variants
            'admin' => 'organizer',
            'administrator' => 'organizer',
            'admins' => 'organizer',
            'administrators' => 'organizer',
            'organiser' => 'organizer',
            'organizer' => 'organizer',
            'organisers' => 'organizer',
            'organizers' => 'organizer',
            'owner' => 'organizer',
            'owners' => 'organizer',
            'host' => 'organizer',
            'hosts' => 'organizer',
            'co-host' => 'organizer',
            'cohost' => 'organizer',
            'co host' => 'organizer',

            // Mapper variants
            'custom mapper' => 'mapper',
            'mapper' => 'mapper',
            'mappers' => 'mapper',

            // Mappooler variants
            'map selector' => 'mappooler',
            'mapselector' => 'mappooler',
            'map selectors' => 'mappooler',
            'mappooler' => 'mappooler',
            'mappoolers' => 'mappooler',
            'head pooler' => 'mappooler',
            'pooler' => 'mappooler',
            'poolers' => 'mappooler',

            // Mappool QA variants
            'mappool qa' => 'mappooler',
            'map pool qa' => 'mappooler',
            'custom map (mapping) qa' => 'mappooler',
            'custom map (gameplay) qa' => 'mappooler',
            'custom map qa' => 'mappooler',

            // Referee variants
            'referee' => 'referee',
            'ref' => 'referee',
            'referees' => 'referee',
            'refs' => 'referee',

            // Streamer variants
            'streamer' => 'streamer',
            'stream' => 'streamer',
            'streamers' => 'streamer',
            'streams' => 'streamer',

            // Commentator variants
            'commentator' => 'commentator',
            'commentary' => 'commentator',
            'commentators' => 'commentator',

            // Playtester variants
            'playtester' => 'playtester',
            'playtesters' => 'playtester',
            'replayer' => 'playtester',
            'replayers' => 'playtester',
            'tester' => 'playtester',
            'testers' => 'playtester',

            // Graphics/Design variants
            'gfx' => 'gfx',
            'graphics' => 'gfx',
            'design team' => 'gfx',
            'graphic designer' => 'gfx',

            // Spreadsheet/Data variants
            'sheeter' => 'sheeter',
            'sheeters' => 'sheeter',
            'spreadsheet manager' => 'sheeter',
            'sheet manager' => 'sheeter',

            // Other roles
            'moderator' => 'other',
            'moderators' => 'other',
            'video editor' => 'other',
            'technical' => 'other',
            'helper' => 'other',
            'other' => 'other',
            'program' => 'other',
        ];

        $normalizedName = strtolower(trim($roleName, " \t\n\r\0\x0B:"));

        if (isset($roleMap[$normalizedName])) {
            return $roleMap[$normalizedName];
        }

        return match (true) {
            preg_match('/\b(hosts?|cohosts?|co-hosts?|admins?|administrators?|organisers?|organizers?|owners?)\b/', $normalizedName) === 1 => 'organizer',
            preg_match('/\b(map\s*pool|mappool|poolers?|pooling|map\s*selectors?)\b/', $normalizedName) === 1 => 'mappooler',
            preg_match('/\b(playtesters?|playtesting|replayers?|testers?|testing)\b/', $normalizedName) === 1 => 'playtester',
            preg_match('/\b(mappers?|charters?|mapping)\b/', $normalizedName) === 1 => 'mapper',
            preg_match('/\b(referees?|refs?)\b/', $normalizedName) === 1 => 'referee',
            preg_match('/\b(streamers?|streams?)\b/', $normalizedName) === 1 => 'streamer',
            preg_match('/\b(commentators?|commentary|casters?)\b/', $normalizedName) === 1 => 'commentator',
            preg_match('/\b(gfx|graphics?|design(?:ers?)?|artists?|artwork)\b/', $normalizedName) === 1 => 'gfx',
            preg_match('/\b(sheeters?|sheets?|spreadsheets?|data)\b/', $normalizedName) === 1 => 'sheeter',
            preg_match('/\b(moderators?|technical|developers?|helpers?|statisticians?|statistics|metadata|creative team|lan helpers?)\b/', $normalizedName) === 1 => 'other',
            default => null,
        };
    }

    /**
     * Parse star ratings from forum content
     * Extracts qualifier, first round, and last round star ratings
     *
     * @return array{star_rating_qualifier: float|null, star_rating_first: float|null, star_rating_last: float|null}
     */
    public function parseStarRatings(string $content): array
    {
        $result = [
            'star_rating_qualifier' => null,
            'star_rating_first' => null,
            'star_rating_last' => null,
        ];

        // Extract qualifier star rating
        // Matches: "Pool size: 4.25* qualifier" (qualifier AFTER)
        //          "Qualifier pool: 4.25* stars" (qualifier BEFORE)
        //          "Pool size is 3.50* for qualifiers" (qualifier AFTER with "for")
        if (preg_match('/(?:qualifier|qualification|qualifying)(?:\s+pool)?(?:\s+size)?[:\s]+(\d+\.?\d*)\*?/i', $content, $matches)) {
            $result['star_rating_qualifier'] = (float) $matches[1];
        } elseif (preg_match('/(?:pool\s+size(?:\s+is)?[:\s]+)?(\d+\.?\d*)\*?\s*(?:for\s+)?(?:qualifier|qualification|qualifying)/i', $content, $matches)) {
            $result['star_rating_qualifier'] = (float) $matches[1];
        }

        // Extract first round star rating
        // Matches: "First round: 5.12* stars", "Round 1: 5.12*", "First round pool size: 4.75*"
        if (preg_match('/(?:first\s+round|round\s+1)(?:[:\s]+(?:pool\s+size)?)?[:\s]+(\d+\.?\d*)\*?/i', $content, $matches)) {
            $result['star_rating_first'] = (float) $matches[1];
        }

        // Extract last round/finals star rating
        // Matches: "Finals: 6.78*", "Last round: 7.20* stars", "Grand Final: 8.5*"
        if (preg_match('/(?:finals?|last\s+round|grand\s+final)(?:[:\s]+(?:pool\s+size)?)?[:\s]+(\d+\.?\d*)\*?/i', $content, $matches)) {
            $result['star_rating_last'] = (float) $matches[1];
        }

        return $result;
    }
}
