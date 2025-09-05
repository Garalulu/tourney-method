<?php

namespace TourneyMethod\Tests\Helpers;

/**
 * osu! API Mock Service
 * 
 * Provides mock responses for osu! API endpoints used in OAuth flow
 * and user data retrieval for comprehensive testing.
 */
class OsuApiMock
{
    /**
     * Mock successful OAuth token response
     * 
     * @return array Mock token response data
     */
    public static function getSuccessfulTokenResponse(): array
    {
        return [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'mock_access_token_' . bin2hex(random_bytes(16)),
            'refresh_token' => 'mock_refresh_token_' . bin2hex(random_bytes(16))
        ];
    }
    
    /**
     * Mock OAuth error response
     * 
     * @param string $error Error type
     * @param string $description Error description
     * @return array Mock error response
     */
    public static function getOAuthErrorResponse(string $error = 'invalid_request', string $description = 'Invalid authorization code'): array
    {
        return [
            'error' => $error,
            'error_description' => $description
        ];
    }
    
    /**
     * Mock successful user info response for admin user
     * 
     * @return array Mock admin user data
     */
    public static function getAdminUserResponse(): array
    {
        return [
            'id' => 757783,
            'username' => 'TestAdmin',
            'country_code' => 'KR',
            'avatar_url' => 'https://a.ppy.sh/757783?1640995200.jpeg',
            'country' => [
                'code' => 'KR',
                'name' => 'South Korea'
            ],
            'cover' => [
                'custom_url' => null,
                'url' => 'https://assets.ppy.sh/user-covers/default/0.jpg',
                'id' => null
            ],
            'default_group' => 'default',
            'is_active' => true,
            'is_bot' => false,
            'is_deleted' => false,
            'is_online' => true,
            'is_supporter' => false,
            'last_visit' => '2025-09-05T10:30:00+00:00',
            'pm_friends_only' => false,
            'profile_colour' => null,
            'username_colour' => null,
            'playmode' => 'osu',
            'playstyle' => ['mouse', 'keyboard'],
            'profile_order' => ['me', 'recent_activity', 'beatmaps', 'historical', 'kudosu', 'top_ranks', 'medals']
        ];
    }
    
    /**
     * Mock successful user info response for regular user
     * 
     * @return array Mock regular user data
     */
    public static function getRegularUserResponse(): array
    {
        return [
            'id' => 123456,
            'username' => 'RegularUser',
            'country_code' => 'US',
            'avatar_url' => 'https://a.ppy.sh/123456?1640995200.jpeg',
            'country' => [
                'code' => 'US',
                'name' => 'United States'
            ],
            'cover' => [
                'custom_url' => null,
                'url' => 'https://assets.ppy.sh/user-covers/default/1.jpg',
                'id' => null
            ],
            'default_group' => 'default',
            'is_active' => true,
            'is_bot' => false,
            'is_deleted' => false,
            'is_online' => false,
            'is_supporter' => true,
            'last_visit' => '2025-09-04T15:20:00+00:00',
            'pm_friends_only' => false,
            'profile_colour' => null,
            'username_colour' => null,
            'playmode' => 'osu',
            'playstyle' => ['tablet'],
            'profile_order' => ['me', 'recent_activity', 'beatmaps', 'historical', 'kudosu', 'top_ranks', 'medals']
        ];
    }
    
    /**
     * Mock user info response with missing required fields
     * 
     * @return array Mock incomplete user data
     */
    public static function getIncompleteUserResponse(): array
    {
        return [
            'country_code' => 'JP',
            'is_active' => true,
            'is_online' => false
            // Missing 'id' and 'username' fields
        ];
    }
    
    /**
     * Mock HTTP 401 Unauthorized response
     * 
     * @return array Mock 401 error response
     */
    public static function getUnauthorizedResponse(): array
    {
        return [
            'error' => 'Unauthorized',
            'message' => 'The access token provided is expired, revoked, malformed, or invalid for other reasons.'
        ];
    }
    
    /**
     * Mock HTTP 429 Rate Limited response
     * 
     * @return array Mock rate limit response
     */
    public static function getRateLimitedResponse(): array
    {
        return [
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => 60
        ];
    }
    
    /**
     * Mock HTTP 500 Server Error response
     * 
     * @return array Mock server error response
     */
    public static function getServerErrorResponse(): array
    {
        return [
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ];
    }
    
    /**
     * Get mock cURL response info for successful request
     * 
     * @return array Mock cURL info
     */
    public static function getSuccessfulCurlInfo(): array
    {
        return [
            'http_code' => 200,
            'content_type' => 'application/json',
            'total_time' => 0.5,
            'namelookup_time' => 0.1,
            'connect_time' => 0.2,
            'pretransfer_time' => 0.3,
            'starttransfer_time' => 0.4,
            'redirect_count' => 0,
            'url' => 'https://osu.ppy.sh/api/v2/me'
        ];
    }
    
    /**
     * Get mock cURL response info for error
     * 
     * @param int $httpCode HTTP status code
     * @return array Mock cURL info
     */
    public static function getErrorCurlInfo(int $httpCode = 400): array
    {
        return [
            'http_code' => $httpCode,
            'content_type' => 'application/json',
            'total_time' => 0.3,
            'namelookup_time' => 0.1,
            'connect_time' => 0.2,
            'pretransfer_time' => 0.3,
            'starttransfer_time' => 0.3,
            'redirect_count' => 0,
            'url' => 'https://osu.ppy.sh/oauth/token'
        ];
    }
    
    /**
     * Generate mock authorization URL
     * 
     * @param string $state OAuth state parameter
     * @return string Mock authorization URL
     */
    public static function generateAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => 'test_client_id',
            'redirect_uri' => 'http://localhost:8000/api/auth/callback.php',
            'response_type' => 'code',
            'scope' => 'public',
            'state' => $state
        ]);
        
        return 'https://osu.ppy.sh/oauth/authorize?' . $params;
    }
    
    /**
     * Generate mock authorization code
     * 
     * @return string Mock authorization code (similar length to real osu! codes)
     */
    public static function generateAuthorizationCode(): string
    {
        // osu! authorization codes are typically very long base64-encoded strings
        return base64_encode(random_bytes(512));
    }
    
    /**
     * Create mock HTTP response
     * 
     * @param array $data Response data
     * @param int $httpCode HTTP status code
     * @param array $headers Response headers
     * @return array Mock HTTP response
     */
    public static function createMockHttpResponse(array $data, int $httpCode = 200, array $headers = []): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, private',
            'Date' => gmdate('D, d M Y H:i:s \G\M\T')
        ];
        
        return [
            'body' => json_encode($data),
            'http_code' => $httpCode,
            'headers' => array_merge($defaultHeaders, $headers),
            'info' => $httpCode === 200 ? self::getSuccessfulCurlInfo() : self::getErrorCurlInfo($httpCode)
        ];
    }
    
    /**
     * Simulate network timeout
     * 
     * @return array Mock timeout response
     */
    public static function getTimeoutResponse(): array
    {
        return [
            'error' => 'Network timeout',
            'curl_error' => 'Operation timed out after 30000 milliseconds with 0 bytes received',
            'http_code' => 0
        ];
    }
    
    /**
     * Simulate DNS resolution failure
     * 
     * @return array Mock DNS error response
     */
    public static function getDnsErrorResponse(): array
    {
        return [
            'error' => 'DNS resolution failed',
            'curl_error' => 'Could not resolve host: osu.ppy.sh',
            'http_code' => 0
        ];
    }
    
    /**
     * Get comprehensive test scenarios for OAuth flow
     * 
     * @return array Test scenarios with mock responses
     */
    public static function getOAuthTestScenarios(): array
    {
        return [
            'successful_admin_login' => [
                'token_response' => self::getSuccessfulTokenResponse(),
                'user_response' => self::getAdminUserResponse(),
                'expected_outcome' => 'admin_authenticated'
            ],
            'successful_regular_user' => [
                'token_response' => self::getSuccessfulTokenResponse(),
                'user_response' => self::getRegularUserResponse(),
                'expected_outcome' => 'not_authorized'
            ],
            'invalid_authorization_code' => [
                'token_response' => self::getOAuthErrorResponse('invalid_grant', 'Invalid authorization code'),
                'user_response' => null,
                'expected_outcome' => 'oauth_error'
            ],
            'expired_access_token' => [
                'token_response' => self::getSuccessfulTokenResponse(),
                'user_response' => self::getUnauthorizedResponse(),
                'expected_outcome' => 'auth_failed'
            ],
            'rate_limited' => [
                'token_response' => self::getRateLimitedResponse(),
                'user_response' => null,
                'expected_outcome' => 'oauth_error'
            ],
            'network_timeout' => [
                'token_response' => self::getTimeoutResponse(),
                'user_response' => null,
                'expected_outcome' => 'server_error'
            ]
        ];
    }
}