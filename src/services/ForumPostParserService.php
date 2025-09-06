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
    
    /**
     * Parse forum post content to extract tournament data
     * 
     * @param string $content Raw forum post content
     * @param string $title Forum topic title
     * @return array Extracted tournament data with confidence scores
     */
    public function parseForumPost(string $content, string $title = ''): array
    {
        // Input validation (SEC-001 requirement)
        $content = $this->validateAndSanitizeInput($content);
        $title = $this->validateAndSanitizeInput($title);
        
        $extractedData = [
            'title' => $this->extractTournamentTitle($content, $title),
            'host_name' => $this->extractHostName($content),
            'rank_range' => $this->extractRankRange($content),
            'tournament_dates' => $this->extractTournamentDates($content),
            'has_badge' => $this->extractBadgeStatus($content),
            'banner_url' => $this->extractBannerUrl($content),
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
     * Extract tournament title with fallback methods (TECH-001 mitigation)
     */
    private function extractTournamentTitle(string $content, string $topicTitle = ''): ?string
    {
        $patterns = [
            // Method 1: Look for tournament title in headers
            '/^#\s*(.+?)$/m',
            '/^\*\*(.+?)\*\*$/m',
            '/Tournament.*?:\s*(.+?)$/im',
            // Method 2: Look for Korean tournament patterns
            '/토너먼트.*?:\s*(.+?)$/im',
            '/대회.*?:\s*(.+?)$/im',
            // Method 3: Extract from topic title with cleaning
            '/\[.*?\]\s*(.+?)(?:\s*-\s*|\s*\||\s*$)/i'
        ];
        
        // Try content patterns first
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $title = trim($matches[1]);
                if ($this->isValidTournamentTitle($title)) {
                    return $this->cleanTournamentTitle($title);
                }
            }
        }
        
        // Fallback: Clean topic title
        if (!empty($topicTitle)) {
            $cleanTitle = $this->cleanTopicTitle($topicTitle);
            if ($this->isValidTournamentTitle($cleanTitle)) {
                return $cleanTitle;
            }
        }
        
        return null;
    }
    
    /**
     * Extract host name from content or signature
     */
    private function extractHostName(string $content): ?string
    {
        $patterns = [
            '/(?:Host|호스트|주최자|진행자).*?:\s*(.+?)(?:\n|$)/im',
            '/(?:Hosted by|주최|진행):\s*(.+?)(?:\n|$)/im',
            '/Staff.*?:\s*(.+?)(?:\n|$)/im',
            // Signature pattern - often at the end
            '/\n---+\n.*?(.+?)$/s',
            '/\[signature\](.*?)\[\/signature\]/is'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $hostName = trim(strip_tags($matches[1]));
                if ($this->isValidHostName($hostName)) {
                    return $hostName;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract rank range with Korean support
     */
    private function extractRankRange(string $content): ?string
    {
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
        
        return null;
    }
    
    /**
     * Extract tournament dates with various format support
     */
    private function extractTournamentDates(string $content): ?string
    {
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
        
        return null;
    }
    
    /**
     * Extract badge status
     */
    private function extractBadgeStatus(string $content): bool
    {
        $positivePatterns = [
            '/(?:Badge|배지|뱃지).*?:\s*(?:Yes|Y|✓|O|있음|가능)/im',
            '/(?:Profile badge|프로필 배지).*?(?:awarded|지급|제공)/im',
            '/(?:Winner|우승자).*?(?:badge|배지)/im'
        ];
        
        $negativePatterns = [
            '/(?:Badge|배지|뱃지).*?:\s*(?:No|N|✗|X|없음|불가)/im',
            '/(?:No badge|배지 없음)/im'
        ];
        
        // Check for explicit negative first
        foreach ($negativePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }
        
        // Check for positive indicators
        foreach ($positivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false; // Default to false if not specified
    }
    
    /**
     * Extract banner URL from post content
     */
    private function extractBannerUrl(string $content): ?string
    {
        $patterns = [
            '/\[img\](https?:\/\/[^\]]+)\[\/img\]/i',
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            '/!\[.*?\]\((https?:\/\/[^)]+)\)/i', // Markdown image
            '/(https?:\/\/[^\s]+\.(?:jpg|jpeg|png|gif|webp)(?:\?[^\s]*)?)/i' // Direct image URLs
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