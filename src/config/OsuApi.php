<?php

namespace TourneyMethod\Config;

/**
 * osu! API configuration for forum data retrieval
 * 
 * Handles osu! API endpoints and rate limiting for forum access
 * using user OAuth tokens (not client credentials).
 */
class OsuApi
{
    public const API_BASE = 'https://osu.ppy.sh/api/v2';
    public const STANDARD_FORUM_ID = 55; // Standard tournament forum
    
    // Rate limiting - 300/min for Forum API (conservative 250/min = 240ms delay)
    public const RATE_LIMIT_DELAY_MS = 250;
    public const MAX_RETRIES = 3;
    public const BACKOFF_BASE_MS = 2000; // 2s, 4s, 8s exponential backoff

    /**
     * Get API configuration
     */
    public static function getConfig(): array
    {
        return [
            'api_base' => self::API_BASE,
            'forum_id' => self::STANDARD_FORUM_ID,
            'rate_limit_delay' => self::RATE_LIMIT_DELAY_MS,
            'max_retries' => self::MAX_RETRIES,
            'backoff_base' => self::BACKOFF_BASE_MS
        ];
    }
    
    /**
     * Get topics endpoint URL for Standard tournament forum
     */
    public static function getTopicsEndpoint(): string
    {
        return self::API_BASE . '/forums/topics?forum_id=' . self::STANDARD_FORUM_ID;
    }
    
    /**
     * Get topic detail endpoint URL
     */
    public static function getTopicEndpoint(int $topicId): string
    {
        return self::API_BASE . '/forums/topics/' . $topicId;
    }
    
    /**
     * Validate API configuration (always valid since no credentials needed)
     */
    public static function validateConfig(): bool
    {
        return filter_var(self::API_BASE, FILTER_VALIDATE_URL) !== false;
    }
}