<?php

namespace TourneyMethod\Services;

use TourneyMethod\Utils\SecurityHelper;
use InvalidArgumentException;
use Exception;

/**
 * Forum Post Parser Service
 * 
 * Extracts structured tournament data from osu! forum post content.
 * Handles English and Korean tournament announcements with fallback patterns
 * and comprehensive input validation as per QA requirements (SEC-001).
 */
class ForumPostParserService
{
    /** Pattern matching confidence levels */
    public const CONFIDENCE_HIGH = 'high';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_LOW = 'low';
    public const CONFIDENCE_FAILED = 'failed';
    
    /** Korean tournament terms for pattern matching */
    private array $koreanTerms = [
        'open' => ['오픈', '제한없음', 'Open'],
        'host' => ['호스트', '주최자', 'Host', '진행자'],
        'date' => ['날짜', '일정', 'Date', '시간'],
        'registration' => ['등록', '신청', '참가', 'Registration'],
        'prize' => ['상금', '상품', 'Prize', '보상'],
        'badge' => ['배지', 'Badge', '뱃지'],
        'format' => ['형식', '포맷', 'Format', '방식']
    ];
    
    /** Compiled regex patterns for performance (PERF-001 mitigation) */
    private array $compiledPatterns = [];
    
    public function __construct()
    {
        $this->initializePatterns();
    }
    
    public function parseForumPost(string $content, string $title = '', ?int $topicUserId = null): array
    {
        // Input validation (SEC-001 requirement)
        $content = $this->validateAndSanitizeInput($content);
        $title = $this->validateAndSanitizeInput($title);
        
        // Parse title metadata first for new fields
        $titleMetadata = [];
        if (!empty($title)) {
            $titleMetadata = $this->parseTopicTitleMetadata($title);
        }
        
        // Extract new metadata fields from post content
        $discordLink = $this->extractDiscordLink($content);
        $starRating = $this->extractStarRating($content);
        $registrationDates = $this->extractRegistrationDates($content);
        $endDate = $this->extractEndDate($content);
        $bannerUrl = $this->extractBannerUrl($content);
        
        $extractedData = [
            'title' => $titleMetadata['title'] ?? $this->extractTournamentTitle($content, $title),
            'host_name' => $this->extractHostName($content, $topicUserId),
            'rank_range' => $this->extractRankRange($content),
            'tournament_dates' => $this->extractTournamentDates($content),
            'has_badge' => $this->extractBadgeStatus($content),
            'banner_url' => $bannerUrl,
            
            // New metadata fields from title parsing
            'team_vs' => $titleMetadata['team_vs'] ?? null,
            'team_size' => $titleMetadata['team_size'] ?? null,
            'rank_range_min' => isset($titleMetadata['rank_range'][0]) ? $titleMetadata['rank_range'][0] : null,
            'rank_range_max' => isset($titleMetadata['rank_range'][1]) ? $titleMetadata['rank_range'][1] : null,
            'is_bws' => $titleMetadata['is_bws'] ?? false,
            'game_mode' => $titleMetadata['game_mode'] ?? null,
            
            // New metadata fields from post content
            'discord_link' => $discordLink,
            'star_rating_min' => $starRating['min'],
            'star_rating_max' => $starRating['max'],
            'star_rating_qualifier' => $starRating['qualifier'],
            'registration_open_date' => $registrationDates['open'],
            'registration_close_date' => $registrationDates['close'],
            'end_date' => $endDate,
            
            'extraction_confidence' => []
        ];
        
        // Add confidence scoring for QA risk mitigation (TECH-001)
        foreach ($extractedData as $field => $value) {
            if ($field !== 'extraction_confidence' && $value !== null) {
                $extractedData['extraction_confidence'][$field] = $this->calculateConfidence($field, $value, $content);
            }
        }
        
        return $extractedData;
    }
    
    /**
     * Parse forum topic title once to extract all metadata efficiently
     */
    private function parseTopicTitleMetadata(string $topicTitle): array
    {
        $metadata = [
            'title' => null,
            'team_vs' => null,      // Integer: 1 (1v1), 2 (2v2), 0 (special)
            'team_size' => null,    // Integer: team size number
            'rank_range' => null,   // [min, max] or [min, null] for open-ended
            'is_bws' => false,      // BWS indicator
            'game_mode' => null     // Normalized: STD, TAIKO, CATCH, MANIA4, MANIA7, MANIA0, ETC
        ];
        
        $title = trim($topicTitle);
        
        // Extract and normalize game mode (improved patterns)
        if (preg_match('/[\[\(](?:STD|o!std|osu!standard|osu!|osu!taiko|taiko|o!c|CTB|osu!catch|o!m\s+\d+K?|osu!mania\s+\d+k?|osu!multimode)[\]\)]/i', $title, $matches)) {
            $metadata['game_mode'] = $this->normalizeGameMode(trim($matches[0], '[]()'));
        }
        
        // Extract team vs and convert to integer
        if (preg_match('/(\d+)vs?(\d+)/i', $title, $matches)) {
            $teamVs = (int)$matches[1];
            // Store as integer (1 for 1v1, 2 for 2v2, etc.)
            $metadata['team_vs'] = $teamVs;
        }
        
        // Extract team size and convert to integer (handle ranges like TS2-3)
        if (preg_match('/TS\s*(\d+)(?:-(\d+))?/i', $title, $matches)) {
            // If there's a range (TS2-3), take the maximum value
            if (isset($matches[2])) {
                $metadata['team_size'] = max((int)$matches[1], (int)$matches[2]);
            } else {
                $metadata['team_size'] = (int)$matches[1];
            }
        } elseif (preg_match('/team\s+size\s+(\d+)/i', $title, $matches)) {
            $metadata['team_size'] = (int)$matches[1];
        }
        
        // Extract rank range patterns and convert to numbers
        $metadata['rank_range'] = $this->extractRankRangeNumbers($title);
        
        // Check for BWS (NO BWS means not using BWS)
        if (preg_match('/no\s+bws/i', $title)) {
            $metadata['is_bws'] = false;
        } elseif (stripos($title, 'BWS') !== false) {
            $metadata['is_bws'] = true;
        }
        
        // Apply default values
        if ($metadata['game_mode'] === null) {
            $metadata['game_mode'] = 'STD'; // Default to Standard mode
        }
        
        if ($metadata['team_vs'] === null && $metadata['team_size'] === null) {
            $metadata['team_vs'] = 1;    // Default to 1v1
            $metadata['team_size'] = 1;  // Default to team size 1
        } elseif ($metadata['team_vs'] === 1 && $metadata['team_size'] === null) {
            $metadata['team_size'] = 1;  // If 1v1, team size is 1
        }
        
        // Extract clean tournament title (improved cleaning)
        $cleanTitle = $this->extractCleanTitle($title, $metadata);
        if ($cleanTitle) {
            $metadata['title'] = $cleanTitle;
        }
        
        return $metadata;
    }
    
    /**
     * Extract clean tournament title by removing all metadata
     */
    private function extractCleanTitle(string $title, array $metadata): ?string
    {
        $cleanTitle = $title;
        
        // Remove game mode brackets first
        $cleanTitle = preg_replace('/^[\[\(](?:STD|o!std|osu!standard|osu!|osu!taiko|taiko|o!c|CTB|osu!catch|o!m\s+\d+K?|osu!mania\s+\d+k?|osu!multimode)[\]\)]\s*/i', '', $cleanTitle);
        
        // Remove status indicators
        $cleanTitle = preg_replace('/^[\[\(](?:CLOSED|OPEN|REGS?\s+(?:OPEN|CLOSED)|Player\s+Regs?\s+(?:OPEN|CLOSED))\s*[\]\)]\s*/i', '', $cleanTitle);
        
        // Remove rank ranges from start of title (like #1~200k)
        $cleanTitle = preg_replace('/^#?\d+[kK]?\s*[~-]\s*\d+[kK]?\s*/', '', $cleanTitle);
        $cleanTitle = preg_replace('/^#?\d{1,3}(?:,\d{3})+\s*-\s*#?\d{1,3}(?:,\d{3})+\s*/', '', $cleanTitle);
        
        // Remove all remaining brackets and parentheses with metadata
        $cleanTitle = preg_replace('/[\[\(][^\[\]\(\)]*[\]\)]/', '', $cleanTitle);
        
        // Remove team vs patterns that aren't part of the tournament name
        if (isset($metadata['team_vs']) && $metadata['team_vs']) {
            $teamVsPattern = $metadata['team_vs'] . 'v' . $metadata['team_vs'];
            // Only remove if it's at the end or followed by separators
            $cleanTitle = preg_replace('/\s*-?\s*' . preg_quote($teamVsPattern, '/') . '\s*(?:[|\-]|$)/', '', $cleanTitle);
        }
        
        // Get main title before separators
        if (preg_match('/^([^|]+?)(?:\s*\||$)/', $cleanTitle, $matches)) {
            $tournamentName = trim($matches[1]);
            
            // Clean up trailing noise and common registration text
            $tournamentName = preg_replace('/[!]+$/', '', $tournamentName);
            $tournamentName = preg_replace('/\s*(丨|：).*$/', '', $tournamentName); // Remove Chinese registration text
            $tournamentName = preg_replace('/\s*:\s*.*$/', '', $tournamentName); // Remove subtitle after colon
            $tournamentName = preg_replace('/\s*\/\s*[\w\s]*-\s*\d+.*$/', '', $tournamentName); // Remove rank/team info from title
            $tournamentName = preg_replace('/\s*-\s*\d+v\d+\s*$/', '', $tournamentName); // Remove trailing team format
            $tournamentName = preg_replace('/\s+/', ' ', trim($tournamentName));
            
            if (!empty($tournamentName) && strlen($tournamentName) >= 3) {
                return $tournamentName;
            }
        }
        
        return null;
    }
    
    /**
     * Extract rank range and convert to numeric array
     */
    private function extractRankRangeNumbers(string $title): ?array
    {
        // Check for explicit "Open Rank" patterns first
        if (preg_match('/(?:open\s+rank|international\s+open\s+rank|open)/i', $title)) {
            return null; // No rank restrictions
        }
        
        $patterns = [
            // Handle comma-separated numbers: #10,000 - #99,999
            '/(?<!TS)(#?\\d{1,3}(?:,\\d{3})+\\s*-\\s*#?\\d{1,3}(?:,\\d{3})+)/i',
            // Handle with commas single side: 100-99,999
            '/(?<!TS)(\\d+\\s*-\\s*\\d{1,3}(?:,\\d{3})+)/i',
            // Handle decimal ranges: 10k-99.9k
            '/(?<!TS)(\\d+(?:\\.\\d+)?[kK]?\\s*-\\s*\\d+(?:\\.\\d+)?[kK]?)/i',
            // Standard patterns: 1k-99k, #25k-#200k, 850-99999
            '/(?<!TS)(\\d+[kK]?\\s*-\\s*\\d+[kK]?)/i',
            '/(?<!TS)(#\\d+[kK]?\\s*-\\s*#?\\d+[kK]?)/i',
            '/(?<!TS)(#\\d+[kK]?\\s*~\\s*#?\\d+[kK]?)/i',
            // Open-ended patterns: 500+, 1k+, #9000-INF
            '/(?<!TS)(\\d+[kK]?\\+)/i',
            '/(?<!TS)(\\d+[kK]?\\s*~\\s*$)/i',
            '/(?<!TS)(#?\\d+[kK]?\\s*-\\s*(?:INF|∞))/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return $this->parseRankRangeString($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Parse rank range string to numeric array
     */
    private function parseRankRangeString(string $rankStr): array
    {
        $rankStr = trim($rankStr);
        
        // Handle open-ended patterns first
        if (preg_match('/(\\d+[kK]?)\\+/', $rankStr, $matches)) {
            return [$this->convertRankToNumber($matches[1]), null];
        }
        
        if (preg_match('/(\\d+[kK]?)\\s*~\\s*$/', $rankStr, $matches)) {
            return [$this->convertRankToNumber($matches[1]), null];
        }
        
        if (preg_match('/(#?\\d+[kK]?)\\s*-\\s*(?:INF|∞)/i', $rankStr, $matches)) {
            return [$this->convertRankToNumber($matches[1]), null];
        }
        
        // Handle range patterns (including commas and decimals)
        if (preg_match('/(#?\\d{1,3}(?:,\\d{3})+|#?\\d+(?:\\.\\d+)?[kK]?)\\s*[-~]\\s*(#?\\d{1,3}(?:,\\d{3})+|#?\\d+(?:\\.\\d+)?[kK]?)/i', $rankStr, $matches)) {
            $min = $this->convertRankToNumber($matches[1]);
            $max = $this->convertRankToNumber($matches[2]);
            
            // Fix inverted ranges (higher rank number = worse rank, so min should be smaller)
            if ($min > $max) {
                // Swap values - host made a mistake in ordering
                [$min, $max] = [$max, $min];
            }
            
            return [$min, $max];
        }
        
        return null;
    }
    
    /**
     * Convert rank string to number (1k -> 1000, #25k -> 25000)
     */
    private function convertRankToNumber(string $rank): int
    {
        $rank = trim($rank, '#');
        
        // Handle comma-separated numbers: 10,000 -> 10000
        if (preg_match('/(\d{1,3}(?:,\d{3})+)/', $rank, $matches)) {
            return (int)str_replace(',', '', $matches[1]);
        }
        
        // Handle decimal with k: 99.9k -> 99900
        if (preg_match('/(\d+\.(\d+))[kK]/i', $rank, $matches)) {
            $wholePart = (int)$matches[1];
            $decimalPart = (int)$matches[2];
            return ($wholePart * 1000) + ($decimalPart * 100);
        }
        
        // Handle regular k: 25k -> 25000
        if (preg_match('/(\d+)[kK]/i', $rank, $matches)) {
            return (int)$matches[1] * 1000;
        }
        
        return (int)$rank;
    }
    
    /**
     * Normalize game mode to standardized values
     */
    private function normalizeGameMode(string $gameMode): string
    {
        $gameMode = strtolower(trim($gameMode));
        
        // Standard osu!
        if (in_array($gameMode, ['std', 'o!std', 'osu!standard', 'standard', 'osu!'])) {
            return 'STD';
        }
        
        // Taiko
        if (in_array($gameMode, ['osu!taiko', 'taiko', 'o!taiko'])) {
            return 'TAIKO';
        }
        
        // Catch the Beat
        if (in_array($gameMode, ['o!c', 'ctb', 'osu!catch', 'catch'])) {
            return 'CATCH';
        }
        
        // osu!mania variants
        if (preg_match('/(?:o!m|osu!mania)\s*(\d+)k?/i', $gameMode, $matches)) {
            $keys = (int)$matches[1];
            if ($keys === 4) {
                return 'MANIA4';
            } elseif ($keys === 7) {
                return 'MANIA7';
            } else {
                return 'MANIA0'; // Other key counts
            }
        }
        
        // Generic mania without key specification
        if (in_array($gameMode, ['o!m', 'osu!mania', 'mania'])) {
            return 'MANIA0';
        }
        
        // Multimode
        if (in_array($gameMode, ['osu!multimode', 'multimode'])) {
            return 'ETC';
        }
        
        // Unrecognized
        return 'ETC';
    }
    
    /**
     * Extract tournament title using comprehensive metadata parser
     */
    private function extractTournamentTitle(string $content, string $topicTitle = ''): ?string
    {
        if (!empty($topicTitle)) {
            $metadata = $this->parseTopicTitleMetadata($topicTitle);
            return $metadata['title'];
        }
        
        return null;
    }
    
    private function extractHostName(string $content, ?int $topicUserId = null): ?string
    {
        // Only use forum topic creator name from topicUserId
        if ($topicUserId !== null) {
            try {
                $username = $this->getOsuUsername($topicUserId);
                if ($username && $this->isValidHostName($username)) {
                    return $username;
                }
            } catch (\Exception $e) {
                // Log error but don't fail extraction
                error_log("Failed to fetch username for user ID {$topicUserId}: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Extract rank range with Korean support
     * COMMENTED OUT - REBUILDING ALGORITHM
     */
    private function extractRankRange(string $content): ?string
    {
        // TODO: Rebuild rank range extraction algorithm
        /*
        $patterns = [
            // Standard osu! rank patterns
            '/(?:Rank|랭크|등급).*?(?:Range|범위)?.*?:\s*([0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?|Open|오픈|제한없음|#[0-9,]+-#?[0-9,]*)/im',
            '/(?:BWS|BadgeWeightedSeeding).*?:\s*([0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?)/im',
            // Pattern in brackets or parentheses
            '/\[([0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?|Open|오픈)\]/i',
            '/\(([0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?|Open|오픈)\)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $rankRange = trim($matches[1]);
                if ($this->isValidRankRange($rankRange)) {
                    return $this->normalizeRankRange($rankRange);
                }
            }
        }
        */
        
        return null;
    }
    
    /**
     * Extract tournament dates with various format support
     * COMMENTED OUT - REBUILDING ALGORITHM
     */
    private function extractTournamentDates(string $content): ?string
    {
        // TODO: Rebuild tournament dates extraction algorithm
        /*
        $patterns = [
            // Standard date patterns
            '/(?:Date|날짜|일정|Schedule).*?:\s*([^\n]+)/im',
            '/(?:Tournament|토너먼트|대회).*?(?:Date|날짜).*?:\s*([^\n]+)/im',
            // Date range patterns
            '/([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}\s*-\s*[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4})/i',
            '/([0-9]{1,2}-[0-9]{1,2}-[0-9]{2,4}\s*(?:to|~|부터|까지)\s*[0-9]{1,2}-[0-9]{1,2}-[0-9]{2,4})/i',
            // Month name patterns
            '/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)[a-z]*\s+[0-9]{1,2}(?:st|nd|rd|th)?,?\s*[0-9]{2,4})/im'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $dates = trim($matches[1]);
                if (strlen($dates) > 3 && strlen($dates) < 100) {
                    return $dates;
                }
            }
        }
        */
        
        return null;
    }
    
    /**
     * Extract badge status
     * COMMENTED OUT - REBUILDING ALGORITHM
     */
    private function extractBadgeStatus(string $content): bool
    {
        // Check for tcomm.hivie.tn/reports/create link (strong indicator of badge)
        if (preg_match('/tcomm\.hivie\.tn\/reports\/create/i', $content)) {
            return true;
        }
        
        // Explicit negative patterns (take precedence)
        $negativePatterns = [
            '/unbadge/i',
            '/unbadged/i'
        ];
        
        foreach ($negativePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }
        
        // Positive badge indicators
        $positivePatterns = [
            '/\bbadged\b/i',
            '/\bbadge\b/i'
        ];
        
        foreach ($positivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false; // Default to false if not specified
    }
    
    /**
     * Extract Discord invite link from post content
     */
    private function extractDiscordLink(string $content): ?string
    {
        $patterns = [
            '/discord\.gg\/([a-zA-Z0-9]+)/i',
            '/discord\.com\/invite\/([a-zA-Z0-9]+)/i',
            '/discordapp\.com\/invite\/([a-zA-Z0-9]+)/i'
        ];
        
        // Check main content first
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $inviteCode = trim($matches[1]);
                if (strlen($inviteCode) >= 3 && strlen($inviteCode) <= 20) {
                    return $inviteCode; // Return only the invite code, not the full URL
                }
            }
        }
        
        // Check inside imagemaps as well
        if (preg_match('/\[imagemap\](.*?)\[\/imagemap\]/is', $content, $imagemapMatch)) {
            $imagemapContent = $imagemapMatch[1];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $imagemapContent, $matches)) {
                    $inviteCode = trim($matches[1]);
                    if (strlen($inviteCode) >= 3 && strlen($inviteCode) <= 20) {
                        return $inviteCode;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract star rating information from post content
     */
    private function extractStarRating(string $content): array
    {
        $result = [
            'min' => null,
            'max' => null,
            'qualifier' => null
        ];
        
        // Check if qualifier is mentioned in the content (including Group Stage)
        $hasQualifier = preg_match('/qualifier|qualifiers?|group\s+stage/i', $content);
        
        // Extract all star ratings in order of appearance
        $allRatings = [];
        
        // Comprehensive patterns for extracting star ratings from tournament brackets
        $bracketPatterns = [
            // BBCode list format: [b]SR=5.8[/b], [b]SR=5.3[/b]
            '/\[b\]SR=([0-9]+(?:\.[0-9]+)?)\[\/b\]/i',
            // BBCode format: [b][color=#FF8282]Qualifiers[/b][/color]: 7*
            '/\[b\]\[color=[^\]]*\]([^[]*)\[\/b\]\[\/color\]:\s*([0-9]+(?:\.[0-9]+)?)\*/i',
            // Simple bracket format: (8.0*), (8.2*), etc.
            '/\(([0-9]+(?:\.[0-9]+)?)\*\)/i',
            // Bold format: [b]Group Stage (8.0*)[/b], [b]Round of 32 (8.2*)[/b]
            '/\[b\][^(]*\(([0-9]+(?:\.[0-9]+)?)\*\)[^[]*\[\/b\]/i',
            // Direct star notation with context: Qualifiers | [b]SR=5.8[/b]
            '/\|\s*\[b\]SR=([0-9]+(?:\.[0-9]+)?)\[\/b\]/i',
            // Direct star notation: 5.8*, 6.5*, 8*
            '/([0-9]+(?:\.[0-9]+)?)\*/i'
        ];
        
        // Extract all ratings while preserving order
        $foundRatings = [];
        foreach ($bracketPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $rating = 0;
                    $offset = 0;
                    
                    if (isset($match[2])) {
                        // Pattern with stage name and rating
                        $rating = (float)$match[2][0];
                        $offset = $match[2][1];
                    } else {
                        // Pattern with just rating
                        $rating = (float)$match[1][0];
                        $offset = $match[1][1];
                    }
                    
                    if ($rating >= 0.5 && $rating <= 20.0) {
                        $foundRatings[] = ['rating' => $rating, 'offset' => $offset];
                    }
                }
            }
        }
        
        // Sort by offset to maintain order of appearance
        usort($foundRatings, function($a, $b) {
            return $a['offset'] <=> $b['offset'];
        });
        
        // Extract just the ratings in order
        $allRatings = array_map(function($item) {
            return $item['rating'];
        }, $foundRatings);
        
        // Remove duplicates while preserving order
        $allRatings = array_values(array_unique($allRatings));
        
        if (!empty($allRatings)) {
            if ($hasQualifier && count($allRatings) > 0) {
                // First rating is qualifier
                $result['qualifier'] = $allRatings[0];
                // Remaining ratings are for min/max calculation
                $remainingRatings = array_slice($allRatings, 1);
            } else {
                // No qualifier mentioned, all ratings are for min/max
                $result['qualifier'] = null;
                $remainingRatings = $allRatings;
            }
            
            // Calculate min/max from remaining ratings
            if (!empty($remainingRatings)) {
                $result['min'] = min($remainingRatings);
                $result['max'] = max($remainingRatings);
            }
        }
        
        // Fallback: try original patterns if no bracket ratings found
        if (empty($allRatings)) {
            return $this->extractStarRatingFallback($content);
        }
        
        return $result;
    }
    
    /**
     * Fallback star rating extraction using original patterns
     */
    private function extractStarRatingFallback(string $content): array
    {
        $result = [
            'min' => null,
            'max' => null,
            'qualifier' => null
        ];
        
        // Check if qualifier is mentioned (including Group Stage)
        $hasQualifier = preg_match('/qualifier|qualifiers?|group\s+stage/i', $content);
        
        if ($hasQualifier) {
            // Extract first star rating as qualifier
            $firstRatingPattern = '/([0-9]+(?:\.[0-9]+)?)\*/i';
            if (preg_match($firstRatingPattern, $content, $matches)) {
                $qualifierRating = (float)$matches[1];
                if ($qualifierRating >= 0.5 && $qualifierRating <= 20.0) {
                    $result['qualifier'] = $qualifierRating;
                }
            }
        }
        
        // Patterns for main tournament SR
        $patterns = [
            // Range patterns: SR=6.0-7.5, SR: 6.0 - 7.5, *6.0-*7.5
            '/(?:SR|Star Rating)[\s:=]*([0-9]+(?:\.[0-9]+)?)[\s]*[-~][\s]*([0-9]+(?:\.[0-9]+)?)/i',
            '/\*([0-9]+(?:\.[0-9]+)?)[\s]*[-~][\s]*\*([0-9]+(?:\.[0-9]+)?)/i',
            '/([0-9]+(?:\.[0-9]+)?)★[\s]*[-~][\s]*([0-9]+(?:\.[0-9]+)?)★/i',
            
            // Single star rating: SR=6.0, *6.0, 6.0★
            '/(?:SR|Star Rating)[\s:=]+([0-9]+(?:\.[0-9]+)?)/i',
            '/\*([0-9]+(?:\.[0-9]+)?)(?!\s*[-~])/i',
            '/([0-9]+(?:\.[0-9]+)?)★/i'
        ];
        
        // Look for range patterns first
        foreach (array_slice($patterns, 0, 3) as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $min = (float)$matches[1];
                $max = (float)$matches[2];
                
                // Ensure min is actually smaller than max
                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }
                
                // Validate ratings are in reasonable range
                if ($min >= 0.5 && $min <= 20.0 && $max >= 0.5 && $max <= 20.0) {
                    // Exclude qualifier from min/max if it exists
                    if ($result['qualifier'] !== null) {
                        $qualifierRating = $result['qualifier'];
                        if (abs($min - $qualifierRating) < 0.01) {
                            $result['min'] = $max;
                            $result['max'] = $max;
                        } elseif (abs($max - $qualifierRating) < 0.01) {
                            $result['min'] = $min;
                            $result['max'] = $min;
                        } else {
                            $result['min'] = $min;
                            $result['max'] = $max;
                        }
                    } else {
                        $result['min'] = $min;
                        $result['max'] = $max;
                    }
                }
                break;
            }
        }
        
        // Look for single star rating if no range found
        if ($result['min'] === null && $result['max'] === null) {
            foreach (array_slice($patterns, 3, 3) as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $rating = (float)$matches[1];
                    
                    // Validate rating is in reasonable range
                    if ($rating >= 0.5 && $rating <= 20.0) {
                        // Don't use qualifier rating as min/max
                        if ($result['qualifier'] === null || abs($rating - $result['qualifier']) > 0.01) {
                            $result['min'] = $rating;
                            $result['max'] = $rating;
                        }
                    }
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Extract registration dates from post content
     */
    private function extractRegistrationDates(string $content): array
    {
        $result = [
            'open' => null,
            'close' => null
        ];
        
        // Patterns for registration date ranges and single end dates
        $patterns = [
            // Range patterns: "Player Registration: Sep. 1st - Sep. 21st"
            '/(?:Player\s+)?(?:Registration|Registrations?)[\s:|]*([^-\n]+)\s*[-–—]\s*([^\n]+)/i',
            
            // Range with "to": "20 Aug 13:00 UTC to 3 Sept 13:00 UTC"  
            '/(?:Player\s+)?(?:Registration|Registrations?)[\s:|]*([^to\n]+)\s+to\s+([^\n]+)/i',
            
            // Single end date only: "Registrations End: August 30th at 23:59 UTC"
            '/(?:Registration|Registrations?)\s+(?:End|Ends?)[\s:]*([^\n]+)/i',
            
            // Registration with pipe separator: "Registration | 12. August - 31. August"
            '/(?:Registration|Registrations?)\s*\|\s*([^-\n]+)\s*[-–—]\s*([^\n]+)/i'
        ];
        
        foreach ($patterns as $i => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                if ($i === 2) {
                    // Single end date pattern - only extract close date
                    $closeDate = $this->parseDate(trim($matches[1]));
                    if ($closeDate) {
                        $result['close'] = $closeDate;
                        // For single end date, we'll set open date to forum post created date later
                        break;
                    }
                } else {
                    // Range patterns - extract both open and close dates
                    $openDate = $this->parseDate(trim($matches[1]));
                    $closeDate = $this->parseDate(trim($matches[2]));
                    
                    if ($openDate && $closeDate) {
                        // Ensure open date is before close date
                        if (strtotime($openDate) > strtotime($closeDate)) {
                            [$openDate, $closeDate] = [$closeDate, $openDate];
                        }
                        $result['open'] = $openDate;
                        $result['close'] = $closeDate;
                        break;
                    } elseif ($closeDate) {
                        // At least got close date
                        $result['close'] = $closeDate;
                        if ($openDate) {
                            $result['open'] = $openDate;
                        }
                        break;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Extract tournament end date (Grand Final date) from post content
     */
    private function extractEndDate(string $content): ?string
    {
        // Patterns for Grand Final mentions
        $patterns = [
            '/(?:Grand\s*Finals?|GF)\s*(?:date)?[\s:]*([^\n]+)/i',
            '/(?:Finals?)\s*(?:date)?[\s:]*([^\n]+)/i'
        ];
        
        $latestDate = null;
        $latestTimestamp = 0;
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $dateStr = trim($match[1]);
                    $parsedDate = $this->parseDate($dateStr);
                    if ($parsedDate) {
                        $timestamp = strtotime($parsedDate);
                        if ($timestamp !== false && $timestamp > $latestTimestamp) {
                            $latestTimestamp = $timestamp;
                            $latestDate = $parsedDate;
                        }
                    }
                }
            }
        }
        
        // Convert to Sunday of that week (그 주 일요일) if we found a date
        if ($latestDate) {
            $dayOfWeek = date('w', $latestTimestamp); // 0=Sunday, 6=Saturday
            $daysToSunday = (7 - $dayOfWeek) % 7;
            $sundayTimestamp = $latestTimestamp + ($daysToSunday * 24 * 60 * 60);
            return date('Y-m-d H:i:s', $sundayTimestamp);
        }
        
        return null;
    }
    
    /**
     * Parse date string into standardized format
     */
    private function parseDate(string $dateStr): ?string
    {
        // Clean up the date string
        $dateStr = trim($dateStr);
        $dateStr = preg_replace('/\s+/', ' ', $dateStr); // Normalize whitespace
        $dateStr = preg_replace('/\bat\b/i', '', $dateStr); // Remove "at" word
        $dateStr = preg_replace('/UTC|GMT/i', '', $dateStr); // Remove timezone indicators
        $dateStr = trim($dateStr);
        
        // Month name mappings
        $months = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12
        ];
        
        // Try different date patterns
        $patterns = [
            // Month name formats: "Sep. 1st", "September 7th", "August 30th"
            '/(\w+)\.?\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?(?:\s+(\d{1,2}):(\d{2}))?/i',
            // European month format: "12. August", "31. August"
            '/(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?(?:\s+(\d{1,2}):(\d{2}))?/i',
            // ISO format: 2025-09-15, 2025-09-15 14:30
            '/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?/',
            // US format: 09/15/2025, 9/15/25
            '/(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?/',
            // European format: 15.09.2025, 15/09/2025
            '/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?/'
        ];
        
        foreach ($patterns as $i => $pattern) {
            if (preg_match($pattern, $dateStr, $matches)) {
                $year = null;
                $month = null;
                $day = null;
                $hour = 0;
                $minute = 0;
                
                if ($i === 0) {
                    // Month name format: "Sep. 1st" 
                    $monthName = strtolower($matches[1]);
                    $month = $months[$monthName] ?? null;
                    $day = (int)$matches[2];
                    $year = isset($matches[3]) && $matches[3] ? (int)$matches[3] : date('Y');
                    $hour = isset($matches[4]) ? (int)$matches[4] : 0;
                    $minute = isset($matches[5]) ? (int)$matches[5] : 0;
                } elseif ($i === 1) {
                    // European month format: "12. August"
                    $day = (int)$matches[1];
                    $monthName = strtolower($matches[2]);
                    $month = $months[$monthName] ?? null;
                    $year = isset($matches[3]) && $matches[3] ? (int)$matches[3] : date('Y');
                    $hour = isset($matches[4]) ? (int)$matches[4] : 0;
                    $minute = isset($matches[5]) ? (int)$matches[5] : 0;
                } elseif ($i === 2) {
                    // ISO format: year-month-day
                    $year = (int)$matches[1];
                    $month = (int)$matches[2];
                    $day = (int)$matches[3];
                    $hour = isset($matches[4]) ? (int)$matches[4] : 0;
                    $minute = isset($matches[5]) ? (int)$matches[5] : 0;
                } elseif ($i === 3) {
                    // US format: month/day/year
                    $month = (int)$matches[1];
                    $day = (int)$matches[2];
                    $year = (int)$matches[3];
                    $hour = isset($matches[4]) ? (int)$matches[4] : 0;
                    $minute = isset($matches[5]) ? (int)$matches[5] : 0;
                } else {
                    // European format: day/month/year
                    $day = (int)$matches[1];
                    $month = (int)$matches[2];
                    $year = (int)$matches[3];
                    $hour = isset($matches[4]) ? (int)$matches[4] : 0;
                    $minute = isset($matches[5]) ? (int)$matches[5] : 0;
                }
                
                // Handle 2-digit years
                if ($year < 100) {
                    $year += ($year < 50) ? 2000 : 1900;
                }
                
                // Validate date components
                if ($month && $day && $year && checkdate($month, $day, $year) && $year >= 2020 && $year <= 2030) {
                    return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract banner URL from post content (first image found)
     */
    private function extractBannerUrl(string $content): ?string
    {
        $patterns = [
            // BBCode image formats
            '/\[img\](https?:\/\/[^\]]+)\[\/img\]/i',
            '/\[imagemap\]\s*(https?:\/\/[^\s\n]+)/i', // First URL in imagemap
            // HTML and Markdown
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            '/!\[.*?\]\((https?:\/\/[^)]+)\)/i', // Markdown image
            // Direct image URLs
            '/(https?:\/\/[^\s]+\.(?:jpg|jpeg|png|gif|webp)(?:\?[^\s]*)?)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $url = trim($matches[1]);
                if ($this->isValidImageUrl($url)) {
                    return $url;
                }
            }
        }
        
        return null;
    }

    
    /**
     * Get osu! username from user ID using osu! API v2
     * 
     * @param int $userId osu! user ID
     * @return string|null Username if found, null otherwise
     * @throws \Exception When API request fails
     */
    private function getOsuUsername(int $userId): ?string
    {
        // Get client credentials access token
        $accessToken = $this->getClientCredentialsToken();
        
        if (!$accessToken) {
            throw new \Exception('Failed to obtain osu! API access token');
        }
        
        // Make API request to get user info
        $userEndpoint = "https://osu.ppy.sh/api/v2/users/{$userId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $userEndpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'TourneyMethod/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Clear access token from memory for security
        $accessToken = null;
        
        if ($curlError) {
            throw new \Exception('osu! API request failed: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            if ($httpCode === 404) {
                return null; // User not found
            }
            throw new \Exception('osu! API request failed with HTTP ' . $httpCode);
        }
        
        $userData = json_decode($response, true);
        
        if (!$userData || !isset($userData['username'])) {
            throw new \Exception('Invalid user data response from osu! API');
        }
        
        return $userData['username'];
    }
    
    /**
     * Get osu! API access token using client credentials
     * 
     * @return string|null Access token if successful, null otherwise
     * @throws \Exception When token request fails
     */
    private function getClientCredentialsToken(): ?string
    {
        // Check if environment variables are set
        $clientId = getenv('OSU_CLIENT_ID');
        $clientSecret = getenv('OSU_CLIENT_SECRET');
        
        if (!$clientId || !$clientSecret) {
            throw new \Exception('OSU_CLIENT_ID or OSU_CLIENT_SECRET environment variables not set');
        }
        
        $tokenData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => 'public'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://osu.ppy.sh/oauth/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'TourneyMethod/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception('OAuth token request failed: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('OAuth token request failed with HTTP ' . $httpCode);
        }
        
        $tokenResponse = json_decode($response, true);
        
        if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
            throw new \Exception('Invalid OAuth token response');
        }
        
        // Clear sensitive data from memory
        $tokenData = null;
        $clientSecret = null;
        
        return $tokenResponse['access_token'];
    }

    
    /**
     * Validate and sanitize input content (SEC-001 requirement)
     */
    private function validateAndSanitizeInput(string $input): string
    {
        if (strlen($input) > 100000) {
            throw new InvalidArgumentException('Input content exceeds maximum allowed length');
        }
        
        // Remove potential script tags and dangerous content
        $input = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $input);
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/on\w+\s*=/i', '', $input);
        
        // Validate UTF-8 encoding
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        }
        
        return trim($input);
    }
    
    /**
     * Validate tournament title
     */
    private function isValidTournamentTitle(string $title): bool
    {
        $title = trim($title);
        return strlen($title) >= 3 && strlen($title) <= 200 && 
               !preg_match('/^(Re:|Subject:|제목:)/i', $title);
    }
    
    /**
     * Clean tournament title
     */
    private function cleanTournamentTitle(string $title): string
    {
        // Remove common prefixes
        $title = preg_replace('/^\[?(Tournament|토너먼트|대회|TWC|MWC|CWC|OWC)\]?\s*/i', '', $title);
        
        // Remove BBCode and HTML tags
        $title = strip_tags($title);
        $title = preg_replace('/\[\/?\w+(?:[^\]]*)\]/i', '', $title);
        
        // Normalize whitespace
        $title = preg_replace('/\s+/', ' ', trim($title));
        
        return $title;
    }
    
    /**
     * Clean topic title with fallback method
     */
    private function cleanTopicTitle(string $topicTitle): string
    {
        // Remove forum-specific prefixes
        $topicTitle = preg_replace('/^\[.*?\]\s*/', '', $topicTitle);
        
        return $this->cleanTournamentTitle($topicTitle);
    }
    
    /**
     * Validate host name
     */
    private function isValidHostName(string $hostName): bool
    {
        $hostName = trim($hostName);
        return strlen($hostName) >= 2 && strlen($hostName) <= 50 &&
               !preg_match('/^(https?:|www\.|\.com)/i', $hostName);
    }
    
    /**
     * Validate rank range format
     */
    private function isValidRankRange(string $rankRange): bool
    {
        return preg_match('/^(Open|오픈|제한없음|[0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?|#[0-9,]+-#?[0-9,]*)$/i', trim($rankRange));
    }
    
    /**
     * Normalize rank range format
     */
    private function normalizeRankRange(string $rankRange): string
    {
        $rankRange = trim($rankRange);
        
        // Convert Korean terms to English
        $rankRange = str_ireplace(['오픈', '제한없음'], 'Open', $rankRange);
        
        // Normalize number formatting
        $rankRange = preg_replace('/([0-9]+),([0-9]+)/', '$1$2', $rankRange); // Remove commas in numbers
        
        return $rankRange;
    }
    
    /**
     * Validate image URL
     */
    private function isValidImageUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $url) &&
               strlen($url) <= 500;
    }
    
    /**
     * Calculate extraction confidence level (TECH-001 mitigation)
     */
    private function calculateConfidence(string $field, $value, string $content): string
    {
        if ($value === null) {
            return self::CONFIDENCE_FAILED;
        }
        
        switch ($field) {
            case 'title':
                return $this->calculateTitleConfidence($value, $content);
            case 'host_name':
                return $this->calculateHostConfidence($value, $content);
            case 'rank_range':
                return $this->calculateRankConfidence($value, $content);
            default:
                return self::CONFIDENCE_MEDIUM;
        }
    }
    
    /**
     * Calculate title extraction confidence
     */
    private function calculateTitleConfidence(string $title, string $content): string
    {
        $score = 0;
        
        // Higher confidence for structured extraction
        if (preg_match('/^#\s*' . preg_quote($title, '/') . '$/m', $content)) $score += 3;
        if (preg_match('/^\*\*' . preg_quote($title, '/') . '\*\*$/m', $content)) $score += 3;
        
        // Medium confidence for labeled extraction
        if (preg_match('/Tournament.*?:\s*' . preg_quote($title, '/') . '$/im', $content)) $score += 2;
        
        // Lower confidence for topic title fallback
        if ($score === 0) $score = 1;
        
        if ($score >= 3) return self::CONFIDENCE_HIGH;
        if ($score >= 2) return self::CONFIDENCE_MEDIUM;
        return self::CONFIDENCE_LOW;
    }
    
    /**
     * Calculate host extraction confidence
     */
    private function calculateHostConfidence(string $host, string $content): string
    {
        if (preg_match('/(?:Host|호스트).*?:\s*' . preg_quote($host, '/') . '/im', $content)) {
            return self::CONFIDENCE_HIGH;
        }
        
        if (preg_match('/(?:Staff|진행).*?:\s*' . preg_quote($host, '/') . '/im', $content)) {
            return self::CONFIDENCE_MEDIUM;
        }
        
        return self::CONFIDENCE_LOW;
    }
    
    /**
     * Calculate rank range extraction confidence
     */
    private function calculateRankConfidence(string $rankRange, string $content): string
    {
        if (preg_match('/(?:Rank|BWS).*?:\s*' . preg_quote($rankRange, '/') . '/im', $content)) {
            return self::CONFIDENCE_HIGH;
        }
        
        if (preg_match('/\[' . preg_quote($rankRange, '/') . '\]/', $content)) {
            return self::CONFIDENCE_MEDIUM;
        }
        
        return self::CONFIDENCE_LOW;
    }
    
    /**
     * Initialize compiled regex patterns for performance
     */
    private function initializePatterns(): void
    {
        // Pre-compile frequently used patterns to improve performance (PERF-001)
        $this->compiledPatterns = [
            'title_header' => '/^#\s*(.+?)$/m',
            'title_bold' => '/^\*\*(.+?)\*\*$/m',
            'host_standard' => '/(?:Host|호스트|주최자|진행자).*?:\s*(.+?)(?:\n|$)/im',
            'rank_standard' => '/(?:Rank|랭크|등급).*?(?:Range|범위)?.*?:\s*([0-9,]+K?\+?(?:\s*-\s*[0-9,]+K?\+?)?|Open|오픈|제한없음|#[0-9,]+-#?[0-9,]*)/im'
        ];
    }
}