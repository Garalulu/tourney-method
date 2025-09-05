<?php

namespace TourneyMethod\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Services\AuthService;
use TourneyMethod\Models\AdminUser;
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Config\OAuth;
use TourneyMethod\Tests\Helpers\TestHelper;
use TourneyMethod\Tests\Helpers\OsuApiMock;

/**
 * OAuth Flow Integration Tests
 * 
 * Tests the complete OAuth 2.0 authentication flow including state parameter validation,
 * token exchange, user info retrieval, and session management integration.
 */
class OAuthFlowTest extends TestCase
{
    private AuthService $authService;
    
    protected function setUp(): void
    {
        TestHelper::setupTestEnvironment();
        TestHelper::clearTestSession();
        $this->authService = new AuthService();
    }
    
    protected function tearDown(): void
    {
        TestHelper::cleanupTestEnvironment();
        TestHelper::clearTestSession();
    }
    
    /**
     * Test complete OAuth flow integration for admin user
     * 
     * @test
     * @group integration
     */
    public function it_completes_full_oauth_flow_for_admin_user(): void
    {
        // Step 1: Generate authorization URL
        $authUrl = $this->authService->generateAuthorizationUrl();
        
        $this->assertStringContainsString('https://osu.ppy.sh/oauth/authorize', $authUrl);
        $this->assertStringContainsString('client_id=test_client_id', $authUrl);
        $this->assertStringContainsString('state=', $authUrl);
        
        // Extract state parameter from URL
        $urlParts = parse_url($authUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'];
        
        // Verify state is stored in session
        $this->assertEquals($state, $_SESSION['oauth_state']);
        
        // Step 2: Simulate OAuth callback with mock authorization code
        $authCode = OsuApiMock::generateAuthorizationCode();
        
        // Step 3: Test state validation
        $this->assertTrue($this->authService->validateOAuthState($state));
        
        // Verify state is cleared after validation
        $this->assertArrayNotHasKey('oauth_state', $_SESSION);
    }
    
    /**
     * Test OAuth state parameter CSRF protection
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_prevents_csrf_attacks_with_state_parameter(): void
    {
        // Generate valid state
        $authUrl = $this->authService->generateAuthorizationUrl();
        $urlParts = parse_url($authUrl);
        parse_str($urlParts['query'], $queryParams);
        $validState = $queryParams['state'];
        
        // Test with wrong state parameter
        $wrongState = bin2hex(random_bytes(32));
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth state mismatch - potential CSRF attack');
        
        $this->authService->validateOAuthState($wrongState);
    }
    
    /**
     * Test OAuth state validation without session state
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_fails_validation_when_session_state_missing(): void
    {
        // Clear any existing session state
        if (isset($_SESSION['oauth_state'])) {
            unset($_SESSION['oauth_state']);
        }
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth state not found in session - potential CSRF attack');
        
        $this->authService->validateOAuthState('any_state');
    }
    
    /**
     * Test OAuth configuration integration
     * 
     * @test
     * @group integration
     */
    public function it_integrates_oauth_configuration_correctly(): void
    {
        $this->assertTrue(OAuth::isConfigured());
        $this->assertEquals('test_client_id', OAuth::getClientId());
        $this->assertEquals('test_client_secret', OAuth::getClientSecret());
        $this->assertEquals('http://localhost:8000/api/auth/callback.php', OAuth::getRedirectUri());
    }
    
    /**
     * Test admin user authorization integration
     * 
     * @test
     * @group integration
     */
    public function it_authorizes_admin_user_successfully(): void
    {
        $mockUserData = OsuApiMock::getAdminUserResponse();
        
        // Test admin user creation from API data
        $adminUser = AdminUser::fromOsuApiData($mockUserData);
        
        $this->assertInstanceOf(AdminUser::class, $adminUser);
        $this->assertTrue($adminUser->isAdmin());
        $this->assertEquals(757783, $adminUser->getOsuId());
        $this->assertEquals('TestAdmin', $adminUser->getUsername());
    }
    
    /**
     * Test regular user authorization rejection
     * 
     * @test
     * @group integration
     */
    public function it_rejects_non_admin_user_authorization(): void
    {
        $mockUserData = OsuApiMock::getRegularUserResponse();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User is not authorized as admin');
        
        AdminUser::authorizeAdmin($mockUserData);
    }
    
    /**
     * Test admin session creation integration
     * 
     * @test
     * @group integration
     */
    public function it_creates_admin_session_successfully(): void
    {
        $adminUser = TestHelper::createTestAdminUser();
        
        $sessionId = $adminUser->createSession();
        
        $this->assertNotEmpty($sessionId);
        $this->assertArrayHasKey('admin_user', $_SESSION);
        $this->assertEquals($adminUser->getOsuId(), $_SESSION['admin_user']['osu_id']);
        $this->assertTrue($_SESSION['admin_user']['is_admin']);
        $this->assertArrayHasKey('csrf_token', $_SESSION['admin_user']);
        $this->assertArrayHasKey('login_time', $_SESSION['admin_user']);
    }
    
    /**
     * Test session-based authentication integration
     * 
     * @test
     * @group integration
     */
    public function it_validates_session_authentication(): void
    {
        // Initially not authenticated
        $this->assertFalse(AdminUser::isAuthenticated());
        
        // Set up admin session
        TestHelper::setupTestAdminSession();
        
        // Should be authenticated as admin
        $this->assertTrue(AdminUser::isAuthenticated());
        
        $currentUser = AdminUser::getCurrentUser();
        $this->assertInstanceOf(AdminUser::class, $currentUser);
        $this->assertTrue($currentUser->isAdmin());
    }
    
    /**
     * Test session timeout integration
     * 
     * @test
     * @group integration
     */
    public function it_handles_session_timeout_correctly(): void
    {
        TestHelper::setupTestAdminSession();
        
        // Simulate session timeout by setting old login time
        $_SESSION['admin_user']['login_time'] = time() - 3700; // Over 1 hour ago
        
        // Should return null due to timeout
        $currentUser = AdminUser::getCurrentUser();
        $this->assertNull($currentUser);
        
        // Should not be authenticated
        $this->assertFalse(AdminUser::isAuthenticated());
        
        // Session should be destroyed
        $this->assertArrayNotHasKey('admin_user', $_SESSION);
    }
    
    /**
     * Test CSRF token integration with SecurityHelper
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_integrates_csrf_protection_properly(): void
    {
        TestHelper::setupTestAdminSession();
        
        $csrfToken = AdminUser::getCsrfToken();
        $this->assertNotEmpty($csrfToken);
        $this->assertEquals(64, strlen($csrfToken)); // 32 bytes = 64 hex chars
        
        // Test CSRF validation
        $this->assertTrue(SecurityHelper::validateCsrfToken($csrfToken, $csrfToken));
        
        $wrongToken = bin2hex(random_bytes(32));
        $this->assertFalse(SecurityHelper::validateCsrfToken($wrongToken, $csrfToken));
    }
    
    /**
     * Test security helper admin authentication integration
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_integrates_security_helper_admin_auth(): void
    {
        // Should throw exception when not authenticated
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No authenticated admin user found');
        
        SecurityHelper::getCurrentAdminUser();
    }
    
    /**
     * Test security helper with authenticated admin
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_gets_current_admin_user_when_authenticated(): void
    {
        TestHelper::setupTestAdminSession();
        
        $this->assertTrue(SecurityHelper::isCurrentUserAdmin());
        
        $currentAdmin = SecurityHelper::getCurrentAdminUser();
        $this->assertInstanceOf(AdminUser::class, $currentAdmin);
        $this->assertTrue($currentAdmin->isAdmin());
    }
    
    /**
     * Test input validation integration
     * 
     * @test
     * @group integration
     * @group security
     */
    public function it_validates_oauth_parameters_correctly(): void
    {
        // Test valid OAuth parameters
        $validCode = OsuApiMock::generateAuthorizationCode();
        $validState = TestHelper::generateTestOAuthState();
        
        $sanitizedCode = SecurityHelper::validateString($validCode, 2000);
        $sanitizedState = SecurityHelper::validateString($validState, 64);
        
        $this->assertEquals($validCode, $sanitizedCode);
        $this->assertEquals($validState, $sanitizedState);
        
        // Test invalid parameters
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validateString('', 10, true); // Empty required string
    }
    
    /**
     * Test OAuth configuration error handling
     * 
     * @test
     * @group integration
     */
    public function it_handles_missing_oauth_configuration(): void
    {
        // Clear OAuth configuration
        putenv('OSU_CLIENT_ID');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OSU_CLIENT_ID environment variable is not set');
        
        OAuth::getClientId();
    }
    
    /**
     * Test environment loader integration
     * 
     * @test
     * @group integration
     */
    public function it_loads_environment_variables_correctly(): void
    {
        // Environment should be set up by TestHelper
        $this->assertEquals('test_client_id', getenv('OSU_CLIENT_ID'));
        $this->assertEquals('test_client_secret', getenv('OSU_CLIENT_SECRET'));
        $this->assertEquals('http://localhost:8000', getenv('APP_URL'));
    }
    
    /**
     * Test session destruction integration
     * 
     * @test
     * @group integration
     */
    public function it_destroys_session_completely(): void
    {
        TestHelper::setupTestAdminSession();
        
        // Verify session exists
        $this->assertArrayHasKey('admin_user', $_SESSION);
        $this->assertTrue(AdminUser::isAuthenticated());
        
        // Destroy session
        AdminUser::destroySession();
        
        // Verify session is completely destroyed
        $this->assertArrayNotHasKey('admin_user', $_SESSION);
        $this->assertFalse(AdminUser::isAuthenticated());
        $this->assertNull(AdminUser::getCurrentUser());
    }
    
    /**
     * Test complete authentication flow integration
     * 
     * @test
     * @group integration
     */
    public function it_completes_authentication_flow_end_to_end(): void
    {
        // Step 1: Start OAuth flow
        $authUrl = $this->authService->generateAuthorizationUrl();
        $this->assertNotEmpty($authUrl);
        $this->assertArrayHasKey('oauth_state', $_SESSION);
        
        // Step 2: Extract state and simulate callback
        $urlParts = parse_url($authUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'];
        
        // Step 3: Simulate admin user authentication
        $mockUserData = OsuApiMock::getAdminUserResponse();
        $adminUser = AdminUser::authorizeAdmin($mockUserData);
        
        // Step 4: Create session
        $sessionId = $adminUser->createSession();
        
        // Step 5: Verify complete authentication
        $this->assertNotEmpty($sessionId);
        $this->assertTrue(AdminUser::isAuthenticated());
        $this->assertTrue(SecurityHelper::isCurrentUserAdmin());
        
        $currentUser = SecurityHelper::getCurrentAdminUser();
        $this->assertEquals(757783, $currentUser->getOsuId());
        $this->assertEquals('TestAdmin', $currentUser->getUsername());
    }
    
    /**
     * Test Korean timezone integration
     * 
     * @test
     * @group integration
     */
    public function it_uses_korean_timezone_correctly(): void
    {
        $adminUser = TestHelper::createTestAdminUser();
        
        $createdAt = $adminUser->getCreatedAt();
        $this->assertEquals('Asia/Seoul', $createdAt->getTimezone()->getName());
    }
    
    /**
     * Test error handling integration across components
     * 
     * @test
     * @group integration
     */
    public function it_handles_errors_consistently(): void
    {
        // Test invalid user data handling
        $incompleteUserData = OsuApiMock::getIncompleteUserResponse();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid osu! user data: missing id or username');
        
        AdminUser::fromOsuApiData($incompleteUserData);
    }
}