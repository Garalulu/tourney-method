<?php

namespace TourneyMethod\Tests\Helpers;

use PDO;
use TourneyMethod\Models\AdminUser;

/**
 * Test Helper Utilities
 * 
 * Provides common testing utilities for database setup, session management,
 * and test data creation across all test suites.
 */
class TestHelper
{
    private static ?PDO $testDb = null;
    
    /**
     * Get test database connection
     * 
     * @return PDO Test database connection
     */
    public static function getTestDatabase(): PDO
    {
        if (self::$testDb === null) {
            $testDbPath = TEST_DB_PATH;
            
            // Remove existing test database
            if (file_exists($testDbPath)) {
                @unlink($testDbPath);
            }
            
            // Create fresh test database
            self::$testDb = new PDO('sqlite:' . $testDbPath);
            self::$testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Execute schema if available
            if (defined('SCHEMA_PATH') && file_exists(SCHEMA_PATH)) {
                $schema = file_get_contents(SCHEMA_PATH);
                self::$testDb->exec($schema);
            }
        }
        
        return self::$testDb;
    }
    
    /**
     * Clean up test database
     * 
     * @return void
     */
    public static function cleanupTestDatabase(): void
    {
        if (self::$testDb !== null) {
            self::$testDb = null;
        }
        
        if (defined('TEST_DB_PATH') && file_exists(TEST_DB_PATH)) {
            usleep(10000); // 10ms delay for Windows file handle release
            @unlink(TEST_DB_PATH);
        }
    }
    
    /**
     * Create test admin user
     * 
     * @param array $overrides Override default values
     * @return AdminUser Test admin user instance
     */
    public static function createTestAdminUser(array $overrides = []): AdminUser
    {
        $defaults = [
            'userId' => 1,
            'osuId' => 757783,
            'username' => 'TestAdmin',
            'isAdmin' => true,
            'createdAt' => new \DateTime('2025-01-01 12:00:00', new \DateTimeZone('Asia/Seoul'))
        ];
        
        $data = array_merge($defaults, $overrides);
        
        return new AdminUser(
            userId: $data['userId'],
            osuId: $data['osuId'],
            username: $data['username'],
            isAdmin: $data['isAdmin'],
            createdAt: $data['createdAt']
        );
    }
    
    /**
     * Create test regular user
     * 
     * @param array $overrides Override default values
     * @return AdminUser Test regular user instance
     */
    public static function createTestRegularUser(array $overrides = []): AdminUser
    {
        $defaults = [
            'userId' => 2,
            'osuId' => 123456,
            'username' => 'TestUser',
            'isAdmin' => false,
            'createdAt' => new \DateTime('2025-01-01 12:00:00', new \DateTimeZone('Asia/Seoul'))
        ];
        
        $data = array_merge($defaults, $overrides);
        
        return new AdminUser(
            userId: $data['userId'],
            osuId: $data['osuId'],
            username: $data['username'],
            isAdmin: $data['isAdmin'],
            createdAt: $data['createdAt']
        );
    }
    
    /**
     * Setup test session with admin user
     * 
     * @param AdminUser|null $adminUser Admin user to set in session
     * @return void
     */
    public static function setupTestAdminSession(?AdminUser $adminUser = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $adminUser = $adminUser ?: self::createTestAdminUser();
        
        $_SESSION['admin_user'] = [
            'user_id' => $adminUser->getUserId(),
            'osu_id' => $adminUser->getOsuId(),
            'username' => $adminUser->getUsername(),
            'is_admin' => $adminUser->isAdmin(),
            'login_time' => time(),
            'csrf_token' => bin2hex(random_bytes(32))
        ];
    }
    
    /**
     * Setup test session with regular user (non-admin)
     * 
     * @param AdminUser|null $user User to set in session
     * @return void
     */
    public static function setupTestUserSession(?AdminUser $user = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $user ?: self::createTestRegularUser();
        
        $_SESSION['admin_user'] = [
            'user_id' => $user->getUserId(),
            'osu_id' => $user->getOsuId(),
            'username' => $user->getUsername(),
            'is_admin' => $user->isAdmin(),
            'login_time' => time(),
            'csrf_token' => bin2hex(random_bytes(32))
        ];
    }
    
    /**
     * Clear test session
     * 
     * @return void
     */
    public static function clearTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
    
    /**
     * Get CSRF token from current session
     * 
     * @return string CSRF token
     */
    public static function getCsrfToken(): string
    {
        if (!isset($_SESSION['admin_user']['csrf_token'])) {
            throw new \RuntimeException('No CSRF token found in test session');
        }
        
        return $_SESSION['admin_user']['csrf_token'];
    }
    
    /**
     * Create test environment variables
     * 
     * @return void
     */
    public static function setupTestEnvironment(): void
    {
        putenv('OSU_CLIENT_ID=test_client_id');
        putenv('OSU_CLIENT_SECRET=test_client_secret');
        putenv('APP_URL=http://localhost:8000');
        putenv('SESSION_SECRET=test_session_secret');
    }
    
    /**
     * Clean up test environment variables
     * 
     * @return void
     */
    public static function cleanupTestEnvironment(): void
    {
        putenv('OSU_CLIENT_ID');
        putenv('OSU_CLIENT_SECRET');
        putenv('APP_URL');
        putenv('SESSION_SECRET');
    }
    
    /**
     * Assert array contains expected keys
     * 
     * @param array $expectedKeys Keys that should exist
     * @param array $actualArray Array to check
     * @param string $message Failure message
     * @return void
     */
    public static function assertArrayHasKeys(array $expectedKeys, array $actualArray, string $message = ''): void
    {
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $actualArray)) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    $message ?: "Array should contain key '{$key}'"
                );
            }
        }
    }
    
    /**
     * Assert string matches pattern
     * 
     * @param string $pattern Regular expression pattern
     * @param string $string String to test
     * @param string $message Failure message
     * @return void
     */
    public static function assertStringMatchesPattern(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                $message ?: "String '{$string}' does not match pattern '{$pattern}'"
            );
        }
    }
    
    /**
     * Generate test OAuth state parameter
     * 
     * @return string Test state parameter
     */
    public static function generateTestOAuthState(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate test OAuth authorization code
     * 
     * @return string Test authorization code
     */
    public static function generateTestOAuthCode(): string
    {
        // Simulate the length and format of osu! OAuth codes
        return base64_encode(random_bytes(512));
    }
    
    /**
     * Create mock osu! user data
     * 
     * @param array $overrides Override default values
     * @return array Mock user data
     */
    public static function createMockOsuUserData(array $overrides = []): array
    {
        $defaults = [
            'id' => 757783,
            'username' => 'TestAdmin',
            'country_code' => 'KR',
            'is_online' => true,
            'avatar_url' => 'https://a.ppy.sh/757783?1640995200.jpeg',
            'cover_url' => 'https://assets.ppy.sh/user-covers/default/0.jpg',
            'playmode' => 'osu',
            'playstyle' => ['mouse'],
            'profile_colour' => null
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Mock cURL response for testing
     * 
     * @param string $response Mock response body
     * @param int $httpCode HTTP status code
     * @return void
     */
    public static function mockCurlResponse(string $response, int $httpCode = 200): void
    {
        // This would require a cURL mocking library in a real implementation
        // For now, we'll use this as a placeholder for the architecture
    }
    
    /**
     * Create temporary test file
     * 
     * @param string $content File content
     * @param string $extension File extension
     * @return string Path to temporary file
     */
    public static function createTempFile(string $content, string $extension = '.tmp'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . $extension;
        file_put_contents($tempFile, $content);
        return $tempFile;
    }
    
    /**
     * Clean up temporary files
     * 
     * @param array $files Array of file paths to clean up
     * @return void
     */
    public static function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}