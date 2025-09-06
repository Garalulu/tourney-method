<?php

namespace TourneyMethod\Utils;

use InvalidArgumentException;

/**
 * URL ID Extraction Utility
 * 
 * Extracts unique IDs or slugs from common tournament-related URLs:
 * - Google Sheets, Google Forms, osu! Forums, Challonge, YouTube, Twitch
 * 
 * Implements strict validation per QA requirements (SEC-001, SEC-002).
 * All extractions return only the unique ID/slug, not full URLs.
 */
class UrlExtractor
{
    /** Maximum URL length for security validation */
    private const MAX_URL_LENGTH = 2048;
    
    /** URL type identification patterns (lenient for detection) */
    private array $identificationPatterns = [
        'google_sheets' => '/docs\.google\.com\/spreadsheets\/d\/[a-zA-Z0-9-_]+/i',
        'google_forms' => '/(?:docs\.google\.com\/forms\/d\/|forms\.gle\/)/i',
        'osu_forum' => '/osu\.ppy\.sh\/(?:community\/forums\/topics|forum\/t)\/[0-9]+/i',
        'challonge' => '/challonge\.com\/(?:tournaments\/)?[a-zA-Z0-9_-]+/i',
        'youtube' => '/(?:youtube\.com\/(?:watch|embed|v\/)|youtu\.be\/)/i',
        'twitch' => '/twitch\.tv\//i'
    ];

    /** URL extraction patterns for each supported service */
    private array $extractionPatterns = [
        'google_sheets' => [
            'patterns' => [
                '/docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9-_]{3,})/i'
            ],
            'id_validation' => '/^[a-zA-Z0-9-_]{28,}$/',
            'max_length' => 50
        ],
        'google_forms' => [
            'patterns' => [
                '/docs\.google\.com\/forms\/d\/(?:e\/)?([a-zA-Z0-9-_]{20,})/i',
                '/forms\.gle\/([a-zA-Z0-9-_]+)/i'
            ],
            'id_validation' => '/^[a-zA-Z0-9-_]{10,}$/',
            'max_length' => 100
        ],
        'osu_forum' => [
            'patterns' => [
                '/osu\.ppy\.sh\/community\/forums\/topics\/([0-9]+)/i',
                '/osu\.ppy\.sh\/forum\/t\/([0-9]+)/i'
            ],
            'id_validation' => '/^[0-9]{1,10}$/',
            'max_length' => 10
        ],
        'challonge' => [
            'patterns' => [
                '/challonge\.com\/(?:tournaments\/)?([a-zA-Z0-9_-]+)(?:\/|$)/i'
            ],
            'id_validation' => '/^[a-zA-Z0-9_-]{3,30}$/',
            'max_length' => 30
        ],
        'youtube' => [
            'patterns' => [
                '/youtube\.com\/watch\?(?:[^&]*&)*v=([a-zA-Z0-9_-]{11})(?:&|$)/i',
                '/youtu\.be\/([a-zA-Z0-9_-]{11})(?:\?|$)/i',
                '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:\?|$)/i'
            ],
            'id_validation' => '/^[a-zA-Z0-9_-]{11}$/',
            'max_length' => 11
        ],
        'twitch' => [
            'patterns' => [
                '/twitch\.tv\/videos\/([0-9]{8,12})/',
                '/twitch\.tv\/([a-zA-Z0-9_]{4,25})(?:\/|$)/i'
            ],
            'id_validation' => '/^(?:[a-zA-Z0-9_]{4,25}|[0-9]{8,12})$/',
            'max_length' => 25
        ]
    ];
    
    /**
     * Extract Google Sheets document ID from URL
     * 
     * @param string $url Google Sheets URL
     * @return string|null Document ID or null if not found/invalid
     */
    public function extractGoogleSheetsId(string $url): ?string
    {
        return $this->extractUrlId($url, 'google_sheets');
    }
    
    /**
     * Extract Google Forms form ID from URL
     * 
     * @param string $url Google Forms URL
     * @return string|null Form ID or null if not found/invalid
     */
    public function extractGoogleFormsId(string $url): ?string
    {
        return $this->extractUrlId($url, 'google_forms');
    }
    
    /**
     * Extract osu! forum topic ID from URL
     * 
     * @param string $url osu! forum topic URL
     * @return string|null Topic ID or null if not found/invalid
     */
    public function extractOsuForumId(string $url): ?string
    {
        return $this->extractUrlId($url, 'osu_forum');
    }
    
    /**
     * Extract Challonge tournament slug from URL
     * 
     * @param string $url Challonge tournament URL
     * @return string|null Tournament slug or null if not found/invalid
     */
    public function extractChallongeSlug(string $url): ?string
    {
        return $this->extractUrlId($url, 'challonge');
    }
    
    /**
     * Extract YouTube video ID from URL
     * 
     * @param string $url YouTube video URL
     * @return string|null Video ID or null if not found/invalid
     */
    public function extractYouTubeId(string $url): ?string
    {
        return $this->extractUrlId($url, 'youtube');
    }
    
    /**
     * Extract Twitch username or video ID from URL
     * 
     * @param string $url Twitch channel or video URL
     * @return string|null Username/ID or null if not found/invalid
     */
    public function extractTwitchId(string $url): ?string
    {
        return $this->extractUrlId($url, 'twitch');
    }
    
    /**
     * Extract all supported URL IDs from text content
     * 
     * @param string $content Text content containing URLs
     * @return array Associative array of extracted IDs by type
     */
    public function extractAllUrlIds(string $content): array
    {
        $content = $this->sanitizeContent($content);
        $extractedIds = [];
        
        // Find all potential URLs in content
        $urls = $this->findUrls($content);
        
        foreach ($urls as $url) {
            // Try each extraction type
            foreach (array_keys($this->extractionPatterns) as $type) {
                $id = $this->extractUrlId($url, $type);
                if ($id !== null && !isset($extractedIds[$type])) {
                    $extractedIds[$type] = $id;
                    break; // Found match, don't try other patterns for this URL
                }
            }
        }
        
        return $extractedIds;
    }
    
    /**
     * Validate URL format and extract ID using specified patterns
     * 
     * @param string $url URL to extract ID from
     * @param string $type Extraction type (google_sheets, google_forms, etc.)
     * @return string|null Extracted ID or null if invalid/not found
     */
    private function extractUrlId(string $url, string $type): ?string
    {
        // Input validation (SEC-001 requirement)
        if (!$this->isValidUrl($url)) {
            return null;
        }
        
        if (!isset($this->extractionPatterns[$type])) {
            throw new InvalidArgumentException("Unsupported extraction type: {$type}");
        }
        
        $config = $this->extractionPatterns[$type];
        
        // Try each pattern for this type
        foreach ($config['patterns'] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $extractedId = $matches[1];
                
                // Validate extracted ID format
                if (preg_match($config['id_validation'], $extractedId) && 
                    strlen($extractedId) <= $config['max_length']) {
                    return $extractedId;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Validate URL format and security (SEC-001, SEC-002 requirements)
     * 
     * @param string $url URL to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidUrl(string $url): bool
    {
        // Length check
        if (strlen($url) > self::MAX_URL_LENGTH || strlen($url) < 10) {
            return false;
        }
        
        // Basic URL validation
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        
        // Security checks - prevent malicious URLs
        if (preg_match('/javascript:|data:|vbscript:|file:/i', $url)) {
            return false;
        }
        
        // Must be HTTP/HTTPS
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        
        // Check for suspicious characters that could indicate URL manipulation
        if (preg_match('/[<>"\'{}\\\\\r\n]/', $url)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Find all URLs in text content
     * 
     * @param string $content Text content to search
     * @return array Array of found URLs
     */
    private function findUrls(string $content): array
    {
        $urls = [];
        
        // Pattern to match HTTP/HTTPS URLs
        $pattern = '/https?:\/\/[^\s<>"\']+/i';
        
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $url) {
                // Clean up common trailing characters
                $url = rtrim($url, '.,!?;:)]}');
                
                if ($this->isValidUrl($url)) {
                    $urls[] = $url;
                }
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Sanitize content for URL extraction (SEC-001 requirement)
     * 
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    private function sanitizeContent(string $content): string
    {
        // Remove potential script content and dangerous elements
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/javascript:/i', '', $content);
        
        // Validate UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }
        
        // Limit content length for performance
        if (strlen($content) > 50000) {
            $content = substr($content, 0, 50000);
        }
        
        return $content;
    }
    
    /**
     * Get extraction pattern information for testing
     * 
     * @param string $type Pattern type
     * @return array|null Pattern configuration
     */
    public function getPatternInfo(string $type): ?array
    {
        return $this->extractionPatterns[$type] ?? null;
    }
    
    /**
     * Validate extracted ID format without URL context
     * 
     * @param string $id ID to validate
     * @param string $type Extraction type
     * @return bool True if ID format is valid
     */
    public function validateExtractedId(string $id, string $type): bool
    {
        if (!isset($this->extractionPatterns[$type])) {
            return false;
        }
        
        $config = $this->extractionPatterns[$type];
        
        return preg_match($config['id_validation'], $id) && 
               strlen($id) <= $config['max_length'];
    }
    
    /**
     * Get list of supported URL types
     * 
     * @return array Array of supported extraction types
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->extractionPatterns);
    }
    
    /**
     * Check if URL belongs to a supported service
     * 
     * @param string $url URL to check
     * @return string|null Service type if supported, null otherwise
     */
    public function identifyUrlType(string $url): ?string
    {
        if (!$this->isValidUrl($url)) {
            return null;
        }
        
        foreach ($this->identificationPatterns as $type => $pattern) {
            if (preg_match($pattern, $url)) {
                return $type;
            }
        }
        
        return null;
    }
    
    /**
     * Reconstruct Google Forms URL from stored ID
     * Uses appropriate format based on ID length (long vs short)
     * 
     * @param string $formId Google Forms ID
     * @return string Reconstructed URL
     */
    public function reconstructGoogleFormsUrl(string $formId): string
    {
        // If ID is longer (typical full forms.google.com ID), use full format
        if (strlen($formId) >= 20) {
            return "https://forms.google.com/forms/d/e/{$formId}/viewform";
        }
        // Otherwise use short format
        return "https://forms.gle/{$formId}";
    }
}