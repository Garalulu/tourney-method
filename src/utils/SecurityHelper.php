<?php

namespace TourneyMethod\Utils;

use TourneyMethod\Models\AdminUser;

/**
 * Security Helper Utilities
 * 
 * Provides security functions for CSRF protection, input validation,
 * output escaping, and admin authorization checking.
 */
class SecurityHelper
{
    /**
     * Generate cryptographically secure CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate CSRF token using hash_equals to prevent timing attacks
     * 
     * @param string $token Token to validate
     * @param string $sessionToken Token from session
     * @return bool True if tokens match
     */
    public static function validateCsrfToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Validate CSRF token from POST request against session
     * 
     * @throws \RuntimeException When CSRF token validation fails
     * @return bool True if validation passes
     */
    public static function validateCsrfFromPost(): bool
    {
        if (!isset($_POST['csrf_token'])) {
            throw new \RuntimeException('CSRF token missing from request');
        }
        
        try {
            $sessionToken = AdminUser::getCsrfToken();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('No CSRF token found in session');
        }
        
        if (!self::validateCsrfToken($_POST['csrf_token'], $sessionToken)) {
            throw new \RuntimeException('CSRF token mismatch - potential CSRF attack');
        }
        
        return true;
    }
    
    /**
     * Escape output for safe HTML display
     * 
     * @param string $string String to escape
     * @param int $flags htmlspecialchars flags
     * @param string $encoding Character encoding
     * @param bool $doubleEncode Whether to encode existing HTML entities
     * @return string Escaped string
     */
    public static function escapeHtml(
        string $string, 
        int $flags = ENT_QUOTES, 
        string $encoding = 'UTF-8',
        bool $doubleEncode = true
    ): string {
        return htmlspecialchars($string, $flags, $encoding, $doubleEncode);
    }
    
    /**
     * Validate and sanitize integer input
     * 
     * @param mixed $input Input to validate
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return int Validated integer
     * @throws \InvalidArgumentException When validation fails
     */
    public static function validateInteger($input, ?int $min = null, ?int $max = null): int
    {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            throw new \InvalidArgumentException('Invalid integer value');
        }
        
        if ($min !== null && $value < $min) {
            throw new \InvalidArgumentException("Value must be at least {$min}");
        }
        
        if ($max !== null && $value > $max) {
            throw new \InvalidArgumentException("Value must be at most {$max}");
        }
        
        return $value;
    }
    
    /**
     * Validate and sanitize string input
     * 
     * @param mixed $input Input to validate
     * @param int $maxLength Maximum string length
     * @param bool $required Whether string is required (non-empty)
     * @return string Validated string
     * @throws \InvalidArgumentException When validation fails
     */
    public static function validateString($input, int $maxLength = 255, bool $required = true): string
    {
        if (!is_string($input)) {
            throw new \InvalidArgumentException('Input must be a string');
        }
        
        $value = trim($input);
        
        if ($required && empty($value)) {
            throw new \InvalidArgumentException('String cannot be empty');
        }
        
        if (strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("String length cannot exceed {$maxLength} characters");
        }
        
        // Remove any null bytes and control characters
        $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        
        return $value;
    }
    
    /**
     * Validate osu! user ID format
     * 
     * @param mixed $input Input to validate
     * @return int Valid osu! user ID
     * @throws \InvalidArgumentException When validation fails
     */
    public static function validateOsuUserId($input): int
    {
        return self::validateInteger($input, 1, PHP_INT_MAX);
    }
    
    /**
     * Require admin authentication for current request
     * 
     * @param bool $redirect Whether to redirect to login if not authenticated
     * @param string $redirectUrl URL to redirect to if not authenticated
     * @throws \RuntimeException When user is not authenticated as admin
     * @return void
     */
    public static function requireAdminAuth(bool $redirect = true, string $redirectUrl = '/admin/login.php'): void
    {
        if (!AdminUser::isAuthenticated()) {
            if ($redirect) {
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                throw new \RuntimeException('Admin authentication required');
            }
        }
    }
    
    /**
     * Check if current user has admin privileges
     * 
     * @return bool True if current user is authenticated admin
     */
    public static function isCurrentUserAdmin(): bool
    {
        return AdminUser::isAuthenticated();
    }
    
    /**
     * Get current authenticated admin user
     * 
     * @return AdminUser Current admin user
     * @throws \RuntimeException When no admin user is authenticated
     */
    public static function getCurrentAdminUser(): AdminUser
    {
        $user = AdminUser::getCurrentUser();
        
        if ($user === null || !$user->isAdmin()) {
            throw new \RuntimeException('No authenticated admin user found');
        }
        
        return $user;
    }
    
    /**
     * Configure secure session parameters for production
     * 
     * @return void
     */
    public static function configureSecureSession(): void
    {
        // Only configure session settings if not in test environment
        if (!defined('PHPUNIT_RUNNING')) {
            // Configure session security settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Lax');
            
            // Set secure flag in production (HTTPS)
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', '1');
            }
            
            // Session timeout configuration
            ini_set('session.gc_maxlifetime', '3600'); // 1 hour
            ini_set('session.cookie_lifetime', '3600'); // 1 hour
        }
        
        // Regenerate session ID periodically
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Validate and sanitize URL input
     * 
     * @param string $url URL to validate
     * @param array $allowedSchemes Allowed URL schemes
     * @return string Validated URL
     * @throws \InvalidArgumentException When URL validation fails
     */
    public static function validateUrl(string $url, array $allowedSchemes = ['http', 'https']): string
    {
        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        
        if ($validatedUrl === false) {
            throw new \InvalidArgumentException('Invalid URL format');
        }
        
        $parsedUrl = parse_url($validatedUrl);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], $allowedSchemes)) {
            throw new \InvalidArgumentException('URL scheme not allowed');
        }
        
        return $validatedUrl;
    }
    
    /**
     * Generate secure random string for various security purposes
     * 
     * @param int $length Length of random string
     * @param bool $urlSafe Whether to make string URL-safe
     * @return string Random string
     */
    public static function generateSecureRandomString(int $length = 32, bool $urlSafe = false): string
    {
        $bytes = random_bytes($length);
        
        if ($urlSafe) {
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        }
        
        return bin2hex($bytes);
    }
    
    /**
     * Sanitize filename for safe file operations
     * 
     * @param string $filename Original filename
     * @param int $maxLength Maximum filename length
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename, int $maxLength = 255): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9\-_.()]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > $maxLength) {
            $filename = substr($filename, 0, $maxLength);
        }
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }
}