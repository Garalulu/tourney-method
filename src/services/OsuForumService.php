<?php

namespace TourneyMethod\Services;

use TourneyMethod\Config\OsuApi;
use PDO;

/**
 * osu! Forum API Service for tournament data retrieval
 * 
 * Uses admin user's OAuth token to access forum topics with
 * proper rate limiting and error handling.
 */
class OsuForumService
{
    private float $lastApiCall = 0;
    private array $config;
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->config = OsuApi::getConfig();
        $this->db = $db;
    }
    
    /**
     * Get topics from the Standard tournament forum with retry logic
     * 
     * @return array Forum topics data
     * @throws \Exception On authentication or API failures
     */
    public function getTopicsWithRetry(): array
    {
        $maxRetries = 2;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Get client credentials access token
                $accessToken = $this->getClientCredentialsToken();
                
                $response = $this->makeSecureApiRequest(
                    OsuApi::getTopicsEndpoint(),
                    $accessToken
                );
                
                return $this->validateTopicsResponse($response);
                
            } catch (AuthenticationException $e) {
                $attempt++;
                error_log("Forum API authentication failed (attempt {$attempt}): " . $e->getMessage());
                
                if ($attempt >= $maxRetries) {
                    throw new \Exception('Forum API access failed: ' . $e->getMessage());
                }
                
                sleep(2);
                
            } catch (RateLimitException $e) {
                throw new \Exception('Rate limit exceeded: ' . $e->getMessage());
            }
        }
        
        throw new \Exception('Failed to fetch forum topics after maximum retries');
    }
    
    /**
     * Get access token using OAuth client credentials flow
     * 
     * @return string Access token for API requests
     * @throws AuthenticationException When token request fails
     */
    private function getClientCredentialsToken(): string
    {
        if (!isset($_ENV['OSU_CLIENT_ID']) || !isset($_ENV['OSU_CLIENT_SECRET'])) {
            throw new AuthenticationException('Missing osu! API client credentials in environment');
        }
        
        $tokenData = [
            'client_id' => $_ENV['OSU_CLIENT_ID'],
            'client_secret' => $_ENV['OSU_CLIENT_SECRET'],
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TourneyMethod/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new AuthenticationException('OAuth token request failed: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new AuthenticationException('OAuth token request failed with HTTP ' . $httpCode);
        }
        
        $tokenResponse = json_decode($response, true);
        
        if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
            throw new AuthenticationException('Invalid OAuth token response');
        }
        
        $accessToken = $tokenResponse['access_token'];
        
        // Clear sensitive data from memory (basic approach - sodium_memzero not available)
        $tokenData = null;
        $tokenResponse = null;
        
        return $accessToken;
    }
    
    /**
     * Make secure API request with rate limiting and retry logic
     */
    private function makeSecureApiRequest(string $endpoint, string $token): array
    {
        $attempt = 0;
        
        while ($attempt < $this->config['max_retries']) {
            // Enforce rate limiting
            $this->enforceRateLimit();
            
            try {
                $response = $this->performApiRequest($endpoint, $token);
                
                return $response;
                
            } catch (RateLimitException $e) {
                $attempt++;
                // Exponential backoff for rate limit violations
                $backoffDelay = pow(2, $attempt) * $this->config['backoff_base']; // 2s, 4s, 8s
                error_log("Rate limit hit, backing off for {$backoffDelay}ms");
                usleep($backoffDelay * 1000);
                
                if ($attempt >= $this->config['max_retries']) {
                    throw $e;
                }
                
            } catch (NetworkException $e) {
                $attempt++;
                if ($attempt >= $this->config['max_retries']) {
                    throw new \Exception("API request failed after {$this->config['max_retries']} attempts: " . $e->getMessage());
                }
                
                // 1 second delay before retry
                sleep(1);
            }
        }
        
        throw new \Exception('Max retry attempts exceeded');
    }
    
    /**
     * Enforce rate limiting between API calls
     */
    private function enforceRateLimit(): void
    {
        $currentTime = microtime(true) * 1000;
        $timeSinceLastCall = $currentTime - $this->lastApiCall;
        
        if ($timeSinceLastCall < $this->config['rate_limit_delay']) {
            $sleepTime = ($this->config['rate_limit_delay'] - $timeSinceLastCall) * 1000;
            usleep((int)$sleepTime);
        }
        
        $this->lastApiCall = microtime(true) * 1000;
    }
    
    /**
     * Perform the actual API request
     */
    private function performApiRequest(string $endpoint, string $token): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\n" .
                           "Accept: application/json\r\n" .
                           "User-Agent: TourneyMethod/1.0",
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($endpoint, false, $context);
        
        if ($response === false) {
            // Check for rate limit response in HTTP headers
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (stripos($header, '429') !== false || stripos($header, 'rate limit') !== false) {
                        throw new RateLimitException('API rate limit exceeded');
                    }
                    if (stripos($header, '401') !== false || stripos($header, 'unauthorized') !== false) {
                        throw new AuthenticationException('OAuth token invalid or expired');
                    }
                }
            }
            
            throw new NetworkException('Failed to connect to osu! API');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from API');
        }
        
        return $data;
    }
    
    /**
     * Validate topics response structure
     */
    private function validateTopicsResponse(array $response): array
    {
        if (!isset($response['topics']) || !is_array($response['topics'])) {
            throw new \Exception('Invalid topics response structure');
        }
        
        return $response;
    }
}

/**
 * Custom exceptions for specific error handling
 */
class AuthenticationException extends \Exception {}
class RateLimitException extends \Exception {}
class NetworkException extends \Exception {}