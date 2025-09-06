<?php

namespace TourneyMethod\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Services\OsuForumService;
use TourneyMethod\Config\OsuApi;
use PDO;

/**
 * Unit tests for OsuForumService
 * 
 * Tests user OAuth token handling, rate limiting, and error handling
 */
class OsuForumServiceTest extends TestCase
{
    private ?PDO $testDb = null;
    private ?OsuForumService $service = null;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->testDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Create test schema (simplified)
        $this->testDb->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                osu_id INTEGER UNIQUE NOT NULL,
                username VARCHAR(255) NOT NULL,
                is_admin BOOLEAN DEFAULT FALSE,
                access_token TEXT,
                refresh_token TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME
            )
        ");
        
        $this->service = new OsuForumService($this->testDb);
    }
    
    protected function tearDown(): void
    {
        $this->testDb = null;
        $this->service = null;
    }
    
    public function testServiceCanBeConstructed()
    {
        $this->assertInstanceOf(OsuForumService::class, $this->service);
    }
    
    public function testConfigurationValidation()
    {
        // Test valid configuration (no credentials needed now)
        $this->assertTrue(OsuApi::validateConfig());
    }
    
    public function testGetTopicsEndpoint()
    {
        $endpoint = OsuApi::getTopicsEndpoint();
        $this->assertEquals('https://osu.ppy.sh/api/v2/forums/topics?forum_id=55', $endpoint);
    }
    
    public function testGetTopicEndpoint()
    {
        $endpoint = OsuApi::getTopicEndpoint(123456);
        $this->assertEquals('https://osu.ppy.sh/api/v2/forums/topics/123456', $endpoint);
    }
    
    public function testRateLimitConfiguration()
    {
        $config = OsuApi::getConfig();
        
        $this->assertEquals(250, $config['rate_limit_delay']);
        $this->assertEquals(3, $config['max_retries']);
        $this->assertEquals(2000, $config['backoff_base']);
    }
    
    public function testApiConfiguration()
    {
        $config = OsuApi::getConfig();
        
        $this->assertEquals('https://osu.ppy.sh/api/v2', $config['api_base']);
        $this->assertEquals(55, $config['forum_id']);
        $this->assertEquals(250, $config['rate_limit_delay']);
        $this->assertEquals(3, $config['max_retries']);
        $this->assertEquals(2000, $config['backoff_base']);
    }
    
    public function testGetTopicsWithRetryRequiresAdminUser()
    {
        // Test without any admin users
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Forum API access failed');
        
        $this->service->getTopicsWithRetry();
    }
    
    public function testGetTopicsWithRetryFindsAdminUser()
    {
        // Add a test admin user
        $stmt = $this->testDb->prepare("
            INSERT INTO users (osu_id, username, is_admin, access_token) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([123456, 'TestAdmin', true, 'test_access_token']);
        
        // This will still fail because we can't actually call the API,
        // but it should get past the admin user check
        $this->expectException(\Exception::class);
        // The error should be a network error, not an admin user error
        
        try {
            $this->service->getTopicsWithRetry();
        } catch (\Exception $e) {
            // Should not be the admin user error
            $this->assertStringNotContainsString('No admin user with valid OAuth token found', $e->getMessage());
            throw $e; // Re-throw for the expectException
        }
    }
    
    /**
     * Test Korean timezone handling
     */
    public function testKoreanTimezoneSupport()
    {
        // Verify Korea timezone is properly set in test environment
        date_default_timezone_set('Asia/Seoul');
        $this->assertEquals('Asia/Seoul', date_default_timezone_get());
        
        // Test timestamp formatting
        $timestamp = date('Y-m-d H:i:s');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp);
    }
}