<?php

namespace TourneyMethod\Services;

use TourneyMethod\Config\OAuth;

/**
 * Authentication Service for osu! OAuth 2.0 Integration
 * 
 * Handles secure admin authentication using osu! OAuth 2.0 flow with CSRF protection.
 * Implements state parameter validation and secure token handling.
 */
class AuthService
{
    /**
     * Generate OAuth authorization URL with CSRF protection state parameter
     * 
     * @return string OAuth authorization URL with state parameter
     */
    public function generateAuthorizationUrl(): string
    {
        // Generate cryptographically secure state parameter for CSRF protection
        $state = bin2hex(random_bytes(32));
        
        // Store state in session for later validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_state'] = $state;
        
        return OAuth::getAuthorizationUrl($state);
    }
    
    /**
     * Validate OAuth state parameter for CSRF protection
     * 
     * @param string $receivedState State parameter from OAuth callback
     * @return bool True if state is valid
     * @throws \RuntimeException When state validation fails
     */
    public function validateOAuthState(string $receivedState): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['oauth_state'])) {
            throw new \RuntimeException('OAuth state not found in session - potential CSRF attack');
        }
        
        $sessionState = $_SESSION['oauth_state'];
        
        // Clear state from session after validation
        unset($_SESSION['oauth_state']);
        
        // Use hash_equals to prevent timing attacks
        if (!hash_equals($sessionState, $receivedState)) {
            throw new \RuntimeException('OAuth state mismatch - potential CSRF attack');
        }
        
        return true;
    }
    
    /**
     * Exchange OAuth authorization code for access token
     * 
     * @param string $authorizationCode Authorization code from OAuth callback
     * @return string Access token for API requests
     * @throws \RuntimeException When token exchange fails
     */
    public function exchangeCodeForToken(string $authorizationCode): string
    {
        $tokenData = [
            'client_id' => OAuth::getClientId(),
            'client_secret' => OAuth::getClientSecret(),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => OAuth::getRedirectUri()
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => OAuth::TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TourneyMethod/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \RuntimeException('OAuth token request failed due to network error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("OAuth token request failed with HTTP {$httpCode}. Please check API credentials and try again.");
        }
        
        $tokenResponse = json_decode($response, true);
        
        if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
            throw new \RuntimeException('Invalid OAuth token response');
        }
        
        return $tokenResponse['access_token'];
    }
    
    /**
     * Retrieve user information from osu! API using access token
     * 
     * @param string $accessToken OAuth access token
     * @return array User information from osu! API
     * @throws \RuntimeException When user info request fails
     */
    public function getUserInfo(string $accessToken): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => OAuth::USER_INFO_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TourneyMethod/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Clear access token from memory for security
        $accessToken = null;
        
        if ($curlError) {
            throw new \RuntimeException('User info request failed due to network error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("User info request failed with HTTP {$httpCode}. Access token may have expired.");
        }
        
        $userInfo = json_decode($response, true);
        
        if (!$userInfo || !isset($userInfo['id'])) {
            throw new \RuntimeException('Invalid user info response');
        }
        
        return $userInfo;
    }
    
    /**
     * Complete OAuth authentication flow
     * 
     * @param string $authorizationCode Authorization code from callback
     * @param string $state State parameter from callback
     * @return array User information if authentication successful
     * @throws \RuntimeException When authentication fails at any step
     */
    public function authenticateUser(string $authorizationCode, string $state): array
    {
        // Step 1: Validate state parameter for CSRF protection
        $this->validateOAuthState($state);
        
        // Step 2: Exchange authorization code for access token
        $accessToken = $this->exchangeCodeForToken($authorizationCode);
        
        // Step 3: Get user information from osu! API
        $userInfo = $this->getUserInfo($accessToken);
        
        // Clear access token from memory
        $accessToken = null;
        
        return $userInfo;
    }
}