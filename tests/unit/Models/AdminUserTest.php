<?php

namespace TourneyMethod\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Models\AdminUser;

/**
 * AdminUser Model Unit Tests
 * 
 * Tests admin user validation, session management, and authorization
 * with hard-coded admin list and secure session handling.
 */
class AdminUserTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clear any session data
        $_SESSION = [];
    }
    
    protected function tearDown(): void
    {
        // Clean up session after each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $_SESSION = [];
    }
    
    /**
     * Test AdminUser creation with valid data
     * 
     * @test
     */
    public function it_creates_admin_user_with_valid_data(): void
    {
        $createdAt = new \DateTime('2025-01-01 12:00:00', new \DateTimeZone('Asia/Seoul'));
        
        $adminUser = new AdminUser(
            userId: 1,
            osuId: 757783,
            username: 'TestAdmin',
            isAdmin: true,
            createdAt: $createdAt
        );
        
        $this->assertEquals(1, $adminUser->getUserId());
        $this->assertEquals(757783, $adminUser->getOsuId());
        $this->assertEquals('TestAdmin', $adminUser->getUsername());
        $this->assertTrue($adminUser->isAdmin());
        $this->assertEquals($createdAt, $adminUser->getCreatedAt());
        $this->assertNull($adminUser->getUpdatedAt());
    }
    
    /**
     * Test creating AdminUser from osu! API data with authorized admin
     * 
     * @test
     */
    public function it_creates_admin_user_from_osu_api_data_for_authorized_admin(): void
    {
        $userData = [
            'id' => 757783, // This ID should be in AUTHORIZED_ADMINS
            'username' => 'AdminUser'
        ];
        
        $adminUser = AdminUser::fromOsuApiData($userData);
        
        $this->assertEquals(0, $adminUser->getUserId()); // Not set yet
        $this->assertEquals(757783, $adminUser->getOsuId());
        $this->assertEquals('AdminUser', $adminUser->getUsername());
        $this->assertTrue($adminUser->isAdmin()); // Should be admin based on hard-coded list
    }
    
    /**
     * Test creating AdminUser from osu! API data with non-authorized user
     * 
     * @test
     */
    public function it_creates_user_from_osu_api_data_for_non_authorized_user(): void
    {
        $userData = [
            'id' => 999999, // This ID should NOT be in AUTHORIZED_ADMINS
            'username' => 'RegularUser'
        ];
        
        $adminUser = AdminUser::fromOsuApiData($userData);
        
        $this->assertEquals(999999, $adminUser->getOsuId());
        $this->assertEquals('RegularUser', $adminUser->getUsername());
        $this->assertFalse($adminUser->isAdmin()); // Should NOT be admin
    }
    
    /**
     * Test osu! API data validation - missing ID
     * 
     * @test
     */
    public function it_throws_exception_when_osu_api_data_missing_id(): void
    {
        $userData = [
            'username' => 'TestUser'
            // Missing 'id' field
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid osu! user data: missing id or username');
        
        AdminUser::fromOsuApiData($userData);
    }
    
    /**
     * Test osu! API data validation - missing username
     * 
     * @test
     */
    public function it_throws_exception_when_osu_api_data_missing_username(): void
    {
        $userData = [
            'id' => 123456
            // Missing 'username' field
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid osu! user data: missing id or username');
        
        AdminUser::fromOsuApiData($userData);
    }
    
    /**
     * Test osu! API data validation - invalid ID format
     * 
     * @test
     */
    public function it_throws_exception_when_osu_id_is_invalid(): void
    {
        $userData = [
            'id' => 'not_a_number',
            'username' => 'TestUser'
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid osu! user ID');
        
        AdminUser::fromOsuApiData($userData);
    }
    
    /**
     * Test osu! API data validation - empty username
     * 
     * @test
     */
    public function it_throws_exception_when_username_is_empty(): void
    {
        $userData = [
            'id' => 123456,
            'username' => ''
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid osu! username');
        
        AdminUser::fromOsuApiData($userData);
    }
    
    /**
     * Test authorized admin checking with valid admin ID
     * 
     * @test
     */
    public function it_identifies_authorized_admin_correctly(): void
    {
        // Test with the hard-coded admin ID
        $this->assertTrue(AdminUser::isAuthorizedAdmin(757783));
    }
    
    /**
     * Test authorized admin checking with non-admin ID
     * 
     * @test
     */
    public function it_identifies_non_authorized_user_correctly(): void
    {
        $this->assertFalse(AdminUser::isAuthorizedAdmin(999999));
    }
    
    /**
     * Test admin authorization success
     * 
     * @test
     */
    public function it_authorizes_admin_user_successfully(): void
    {
        $userData = [
            'id' => 757783,
            'username' => 'AdminUser'
        ];
        
        $adminUser = AdminUser::authorizeAdmin($userData);
        
        $this->assertTrue($adminUser->isAdmin());
        $this->assertEquals(757783, $adminUser->getOsuId());
    }
    
    /**
     * Test admin authorization failure
     * 
     * @test
     */
    public function it_throws_exception_when_user_not_authorized_as_admin(): void
    {
        $userData = [
            'id' => 999999, // Not in authorized list
            'username' => 'RegularUser'
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User is not authorized as admin');
        
        AdminUser::authorizeAdmin($userData);
    }
    
    /**
     * Test session creation for admin user
     * 
     * @test
     */
    public function it_creates_secure_session_for_admin(): void
    {
        $adminUser = new AdminUser(
            userId: 1,
            osuId: 757783,
            username: 'AdminUser',
            isAdmin: true
        );
        
        $sessionId = $adminUser->createSession();
        
        $this->assertNotEmpty($sessionId);
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
        
        // Check session data
        $this->assertArrayHasKey('admin_user', $_SESSION);
        $this->assertEquals(1, $_SESSION['admin_user']['user_id']);
        $this->assertEquals(757783, $_SESSION['admin_user']['osu_id']);
        $this->assertEquals('AdminUser', $_SESSION['admin_user']['username']);
        $this->assertTrue($_SESSION['admin_user']['is_admin']);
        $this->assertArrayHasKey('csrf_token', $_SESSION['admin_user']);
        $this->assertArrayHasKey('login_time', $_SESSION['admin_user']);
        
        // CSRF token should be 64 hex characters
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['admin_user']['csrf_token']);
    }
    
    /**
     * Test getting current user from session
     * 
     * @test
     */
    public function it_gets_current_user_from_session(): void
    {
        // Set up session data manually
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_user'] = [
            'user_id' => 1,
            'osu_id' => 757783,
            'username' => 'AdminUser',
            'is_admin' => true,
            'login_time' => time(),
            'csrf_token' => str_repeat('a', 64)
        ];
        
        $currentUser = AdminUser::getCurrentUser();
        
        $this->assertNotNull($currentUser);
        $this->assertEquals(1, $currentUser->getUserId());
        $this->assertEquals(757783, $currentUser->getOsuId());
        $this->assertEquals('AdminUser', $currentUser->getUsername());
        $this->assertTrue($currentUser->isAdmin());
    }
    
    /**
     * Test getting current user when no session exists
     * 
     * @test
     */
    public function it_returns_null_when_no_session_exists(): void
    {
        $currentUser = AdminUser::getCurrentUser();
        $this->assertNull($currentUser);
    }
    
    /**
     * Test session timeout handling
     * 
     * @test
     */
    public function it_handles_session_timeout_correctly(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_user'] = [
            'user_id' => 1,
            'osu_id' => 757783,
            'username' => 'AdminUser',
            'is_admin' => true,
            'login_time' => time() - 3700, // More than 1 hour ago
            'csrf_token' => str_repeat('a', 64)
        ];
        
        $currentUser = AdminUser::getCurrentUser();
        
        $this->assertNull($currentUser);
        $this->assertArrayNotHasKey('admin_user', $_SESSION);
    }
    
    /**
     * Test authentication status checking
     * 
     * @test
     */
    public function it_checks_authentication_status_correctly(): void
    {
        // Not authenticated initially
        $this->assertFalse(AdminUser::isAuthenticated());
        
        // Set up authenticated session
        if (session_status() === PHP_SESSION_NONE) {
            if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        }
        $_SESSION['admin_user'] = [
            'user_id' => 1,
            'osu_id' => 757783,
            'username' => 'AdminUser',
            'is_admin' => true,
            'login_time' => time(),
            'csrf_token' => str_repeat('a', 64)
        ];
        
        $this->assertTrue(AdminUser::isAuthenticated());
    }
    
    /**
     * Test session destruction
     * 
     * @test
     */
    public function it_destroys_session_completely(): void
    {
        // Set up session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_user'] = [
            'user_id' => 1,
            'osu_id' => 757783,
            'username' => 'AdminUser',
            'is_admin' => true,
            'login_time' => time(),
            'csrf_token' => str_repeat('a', 64)
        ];
        
        AdminUser::destroySession();
        
        $this->assertEquals(PHP_SESSION_NONE, session_status());
    }
    
    /**
     * Test CSRF token retrieval
     * 
     * @test
     */
    public function it_gets_csrf_token_from_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expectedToken = str_repeat('a', 64);
        $_SESSION['admin_user'] = [
            'csrf_token' => $expectedToken
        ];
        
        $token = AdminUser::getCsrfToken();
        $this->assertEquals($expectedToken, $token);
    }
    
    /**
     * Test CSRF token retrieval failure
     * 
     * @test
     */
    public function it_throws_exception_when_no_csrf_token_in_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // No admin_user in session
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No CSRF token found in session');
        
        AdminUser::getCsrfToken();
    }
    
    /**
     * Test toArray method
     * 
     * @test
     */
    public function it_converts_to_array_correctly(): void
    {
        $createdAt = new \DateTime('2025-01-01 12:00:00');
        $updatedAt = new \DateTime('2025-01-02 15:30:00');
        
        $adminUser = new AdminUser(
            userId: 1,
            osuId: 757783,
            username: 'AdminUser',
            isAdmin: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
        
        $array = $adminUser->toArray();
        
        $expected = [
            'user_id' => 1,
            'osu_id' => 757783,
            'username' => 'AdminUser',
            'is_admin' => true,
            'created_at' => '2025-01-01 12:00:00',
            'updated_at' => '2025-01-02 15:30:00'
        ];
        
        $this->assertEquals($expected, $array);
    }
}