<?php

namespace TourneyMethod\Config;

/**
 * osu! OAuth 2.0 Configuration
 * 
 * Provides OAuth settings for secure admin authentication with osu! API v2.
 * Uses environment variables for secure credential management.
 */
class OAuth
{
    // osu! OAuth 2.0 Endpoints
    public const AUTHORIZE_URL = 'https://osu.ppy.sh/oauth/authorize';
    public const TOKEN_URL = 'https://osu.ppy.sh/oauth/token';
    public const USER_INFO_URL = 'https://osu.ppy.sh/api/v2/me';
    
    // OAuth Flow Parameters
    public const RESPONSE_TYPE = 'code';
    public const SCOPE = 'public';
    
    // Rate Limits
    public const API_RATE_LIMIT = 1000; // 1000 requests per minute
    
    /**
     * Get OAuth client ID from environment
     * 
     * @return string OAuth client ID
     * @throws \RuntimeException When client ID is not configured
     */
    public static function getClientId(): string
    {
        $clientId = getenv('OSU_CLIENT_ID') ?: '';
        
        if (empty($clientId)) {
            throw new \RuntimeException('OSU_CLIENT_ID environment variable is not set');
        }
        
        return $clientId;
    }
    
    /**
     * Get OAuth client secret from environment
     * 
     * @return string OAuth client secret
     * @throws \RuntimeException When client secret is not configured
     */
    public static function getClientSecret(): string
    {
        $clientSecret = getenv('OSU_CLIENT_SECRET') ?: '';
        
        if (empty($clientSecret)) {
            throw new \RuntimeException('OSU_CLIENT_SECRET environment variable is not set');
        }
        
        return $clientSecret;
    }
    
    /**
     * Get OAuth redirect URI based on environment
     * 
     * @return string Fully qualified redirect URI
     */
    public static function getRedirectUri(): string
    {
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
        return $appUrl . '/api/auth/callback.php';
    }
    
    /**
     * Get complete OAuth authorization URL with state parameter
     * 
     * @param string $state CSRF protection state parameter
     * @return string OAuth authorization URL
     */
    public static function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => self::getClientId(),
            'redirect_uri' => self::getRedirectUri(),
            'response_type' => self::RESPONSE_TYPE,
            'scope' => self::SCOPE,
            'state' => $state
        ]);
        
        return self::AUTHORIZE_URL . '?' . $params;
    }
    
    /**
     * Validate OAuth configuration
     * 
     * @return bool True if OAuth is properly configured
     */
    public static function isConfigured(): bool
    {
        try {
            self::getClientId();
            self::getClientSecret();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }
}