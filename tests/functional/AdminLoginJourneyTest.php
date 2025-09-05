<?php

namespace TourneyMethod\Tests\Functional;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Tests\Helpers\TestHelper;
use TourneyMethod\Tests\Helpers\OsuApiMock;
use TourneyMethod\Utils\EnvLoader;

/**
 * Admin Login User Journey Tests
 * 
 * Tests complete user journeys from login page through admin authentication
 * to admin panel access. Simulates real browser behavior and HTTP requests.
 */
class AdminLoginJourneyTest extends TestCase
{
    private string $baseUrl = 'http://localhost:8000';
    private array $tempFiles = [];
    
    protected function setUp(): void
    {
        TestHelper::setupTestEnvironment();
        TestHelper::clearTestSession();
        
        // Ensure clean state for functional tests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    protected function tearDown(): void
    {
        TestHelper::cleanupTestEnvironment();
        TestHelper::clearTestSession();
        TestHelper::cleanupTempFiles($this->tempFiles);
    }
    
    /**
     * Test complete admin login journey - success path
     * 
     * @test
     * @group functional
     */
    public function it_completes_successful_admin_login_journey(): void
    {
        // Step 1: User visits admin login page
        $loginResponse = $this->simulateGetRequest('/admin/login.php');
        
        $this->assertEquals(200, $loginResponse['status']);
        $this->assertStringContainsString('관리자 로그인', $loginResponse['body']);
        $this->assertStringContainsString('osu!로 로그인하기', $loginResponse['body']); // Updated to match actual Korean text
        
        // Step 2: User clicks "Login with osu!" button
        $oauthResponse = $this->simulateGetRequest('/admin/login.php?login=osu');
        
        $this->assertEquals(302, $oauthResponse['status']);
        $this->assertArrayHasKey('Location', $oauthResponse['headers']);
        
        $redirectUrl = $oauthResponse['headers']['Location'];
        $this->assertStringContainsString('https://osu.ppy.sh/oauth/authorize', $redirectUrl);
        $this->assertStringContainsString('client_id=test_client_id', $redirectUrl);
        $this->assertStringContainsString('state=', $redirectUrl);
        
        // Step 3: Extract OAuth state from redirect URL
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'];
        
        // Step 4: Simulate OAuth provider callback (admin user)
        $authCode = OsuApiMock::generateAuthorizationCode();
        $callbackUrl = "/api/auth/callback.php?code={$authCode}&state={$state}";
        
        $callbackResponse = $this->simulateOAuthCallback($callbackUrl, 'admin');
        
        // Should redirect to admin dashboard
        $this->assertEquals(302, $callbackResponse['status']);
        $this->assertEquals('/admin/', $callbackResponse['headers']['Location']);
        
        // Step 5: User accesses admin dashboard
        $dashboardResponse = $this->simulateGetRequest('/admin/index.php');
        
        $this->assertEquals(200, $dashboardResponse['status']);
        $this->assertStringContainsString('관리자 대시보드', $dashboardResponse['body']);
        $this->assertStringContainsString('TestAdmin', $dashboardResponse['body']);
    }
    
    /**
     * Test admin login journey - unauthorized user
     * 
     * @test
     * @group functional
     */
    public function it_rejects_unauthorized_user_login_journey(): void
    {
        // Steps 1-3: Same as successful journey
        $loginResponse = $this->simulateGetRequest('/admin/login.php');
        $this->assertEquals(200, $loginResponse['status']);
        
        $oauthResponse = $this->simulateGetRequest('/admin/login.php?login=osu');
        $this->assertEquals(302, $oauthResponse['status']);
        
        $redirectUrl = $oauthResponse['headers']['Location'];
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'];
        
        // Step 4: Simulate OAuth callback for regular user (not admin)
        $authCode = OsuApiMock::generateAuthorizationCode();
        $callbackUrl = "/api/auth/callback.php?code={$authCode}&state={$state}";
        
        $callbackResponse = $this->simulateOAuthCallback($callbackUrl, 'regular');
        
        // Should redirect back to login with error
        $this->assertEquals(302, $callbackResponse['status']);
        $this->assertStringContainsString('/admin/login.php?error=not_authorized', $callbackResponse['headers']['Location']);
        
        // Step 5: User sees error message on login page
        $errorResponse = $this->simulateGetRequest('/admin/login.php?error=not_authorized');
        
        $this->assertEquals(200, $errorResponse['status']);
        $this->assertStringContainsString('관리자 권한이 없습니다', $errorResponse['body']);
    }
    
    /**
     * Test login journey with CSRF attack attempt
     * 
     * @test
     * @group functional
     * @group security
     */
    public function it_prevents_csrf_attack_in_login_journey(): void
    {
        // Step 1: User starts legitimate OAuth flow
        $loginResponse = $this->simulateGetRequest('/admin/login.php');
        $oauthResponse = $this->simulateGetRequest('/admin/login.php?login=osu');
        
        $redirectUrl = $oauthResponse['headers']['Location'];
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $queryParams);
        $legitimateState = $queryParams['state'];
        
        // Step 2: Attacker attempts to use wrong state parameter
        $authCode = OsuApiMock::generateAuthorizationCode();
        $attackerState = bin2hex(random_bytes(32));
        $maliciousCallbackUrl = "/api/auth/callback.php?code={$authCode}&state={$attackerState}";
        
        $callbackResponse = $this->simulateOAuthCallback($maliciousCallbackUrl, 'admin');
        
        // Should redirect to login with CSRF error
        $this->assertEquals(302, $callbackResponse['status']);
        $this->assertStringContainsString('/admin/login.php?error=csrf_error', $callbackResponse['headers']['Location']);
        
        // Step 3: User sees CSRF error message
        $errorResponse = $this->simulateGetRequest('/admin/login.php?error=csrf_error');
        
        $this->assertEquals(200, $errorResponse['status']);
        $this->assertStringContainsString('보안 오류가 발생했습니다', $errorResponse['body']);
    }
    
    /**
     * Test login journey with OAuth error
     * 
     * @test
     * @group functional
     */
    public function it_handles_oauth_errors_in_login_journey(): void
    {
        // Step 1: Start OAuth flow
        $loginResponse = $this->simulateGetRequest('/admin/login.php');
        $oauthResponse = $this->simulateGetRequest('/admin/login.php?login=osu');
        
        $redirectUrl = $oauthResponse['headers']['Location'];
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $queryParams);
        $state = $queryParams['state'];
        
        // Step 2: Simulate OAuth error callback
        $errorCallbackUrl = "/api/auth/callback.php?error=access_denied&error_description=User+denied+authorization&state={$state}";
        
        $callbackResponse = $this->simulateOAuthCallback($errorCallbackUrl, null);
        
        // Should redirect to login with OAuth error
        $this->assertEquals(302, $callbackResponse['status']);
        $this->assertStringContainsString('/admin/login.php?error=oauth_error', $callbackResponse['headers']['Location']);
        
        // Step 3: User sees OAuth error message
        $errorResponse = $this->simulateGetRequest('/admin/login.php?error=oauth_error');
        
        $this->assertEquals(200, $errorResponse['status']);
        $this->assertStringContainsString('osu! 인증 서비스에 문제가 발생했습니다', $errorResponse['body']);
    }
    
    /**
     * Test admin access protection without authentication
     * 
     * @test
     * @group functional
     * @group security
     */
    public function it_protects_admin_areas_from_unauthenticated_access(): void
    {
        // Try to access admin dashboard without authentication
        $response = $this->simulateGetRequest('/admin/index.php');
        
        // Should redirect to login
        $this->assertEquals(302, $response['status']);
        $this->assertEquals('/admin/login.php', $response['headers']['Location']);
    }
    
    /**
     * Test logout journey
     * 
     * @test
     * @group functional
     */
    public function it_completes_admin_logout_journey(): void
    {
        // Step 1: Simulate authenticated admin session
        TestHelper::setupTestAdminSession();
        
        // Step 2: Admin accesses dashboard (should work)
        $dashboardResponse = $this->simulateGetRequest('/admin/index.php');
        $this->assertEquals(200, $dashboardResponse['status']);
        
        // Step 3: Admin clicks logout
        $logoutResponse = $this->simulateGetRequest('/admin/logout.php');
        
        // Should redirect to login with success message
        $this->assertEquals(302, $logoutResponse['status']);
        $this->assertStringContainsString('/admin/login.php?message=logged_out', $logoutResponse['headers']['Location']);
        
        // Step 4: User sees logout success message
        $loginResponse = $this->simulateGetRequest('/admin/login.php?message=logged_out');
        
        $this->assertEquals(200, $loginResponse['status']);
        $this->assertStringContainsString('성공적으로 로그아웃되었습니다', $loginResponse['body']);
        
        // Step 5: Try to access admin area again (should redirect to login)
        $protectedResponse = $this->simulateGetRequest('/admin/index.php');
        $this->assertEquals(302, $protectedResponse['status']);
        $this->assertEquals('/admin/login.php', $protectedResponse['headers']['Location']);
    }
    
    /**
     * Test session timeout during admin session
     * 
     * @test
     * @group functional
     */
    public function it_handles_session_timeout_in_admin_journey(): void
    {
        // Step 1: Set up admin session
        TestHelper::setupTestAdminSession();
        
        // Step 2: Simulate session timeout by manipulating login time
        $_SESSION['admin_user']['login_time'] = time() - 3700; // Over 1 hour ago
        
        // Step 3: Try to access admin area
        $response = $this->simulateGetRequest('/admin/index.php');
        
        // Should redirect to login due to timeout
        $this->assertEquals(302, $response['status']);
        $this->assertEquals('/admin/login.php', $response['headers']['Location']);
    }
    
    /**
     * Simulate HTTP GET request
     * 
     * @param string $path Request path
     * @return array Response data
     */
    private function simulateGetRequest(string $path): array
    {
        // Set up request environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['HTTPS'] = '';
        
        // Parse query parameters
        $urlParts = parse_url($path);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $_GET);
        } else {
            $_GET = [];
        }
        
        // Load environment for the request
        EnvLoader::load();
        
        // Capture output
        ob_start();
        
        try {
            // Route request to appropriate file
            $filePath = $this->getFilePathForRoute($urlParts['path']);
            
            if (!file_exists($filePath)) {
                http_response_code(404);
                echo '404 Not Found';
                $output = ob_get_clean();
                return [
                    'status' => 404,
                    'body' => $output,
                    'headers' => []
                ];
            }
            
            // Execute the PHP file
            include $filePath;
            
            $output = ob_get_clean();
            
            // Extract response headers and status
            $headers = [];
            $status = http_response_code() ?: 200;
            
            // Check for Location header (redirect)
            if (function_exists('headers_list')) {
                foreach (headers_list() as $header) {
                    if (strpos($header, 'Location:') === 0) {
                        $headers['Location'] = trim(substr($header, 9));
                    }
                }
            }
            
            return [
                'status' => $status,
                'body' => $output,
                'headers' => $headers
            ];
            
        } catch (\Throwable $e) {
            ob_get_clean();
            
            return [
                'status' => 500,
                'body' => 'Internal Server Error: ' . $e->getMessage(),
                'headers' => []
            ];
        }
    }
    
    /**
     * Simulate OAuth callback with mock API responses
     * 
     * @param string $callbackUrl Callback URL with parameters
     * @param string|null $userType Type of user response ('admin', 'regular', null for error)
     * @return array Response data
     */
    private function simulateOAuthCallback(string $callbackUrl, ?string $userType): array
    {
        // Mock the API responses based on user type
        if ($userType === 'admin') {
            $this->mockOAuthApiResponses(
                OsuApiMock::getSuccessfulTokenResponse(),
                OsuApiMock::getAdminUserResponse()
            );
        } elseif ($userType === 'regular') {
            $this->mockOAuthApiResponses(
                OsuApiMock::getSuccessfulTokenResponse(),
                OsuApiMock::getRegularUserResponse()
            );
        } else {
            // For error scenarios, we just simulate the callback without API calls
        }
        
        return $this->simulateGetRequest($callbackUrl);
    }
    
    /**
     * Mock OAuth API responses
     * 
     * @param array $tokenResponse Mock token response
     * @param array $userResponse Mock user response
     * @return void
     */
    private function mockOAuthApiResponses(array $tokenResponse, array $userResponse): void
    {
        // In a real implementation, this would use a HTTP client mock or VCR
        // For now, we'll simulate the behavior by setting up the expected responses
        // This is a placeholder for the architecture demonstration
    }
    
    /**
     * Get file path for route
     * 
     * @param string $route URL route
     * @return string File path
     */
    private function getFilePathForRoute(string $route): string
    {
        $basePath = dirname(__DIR__, 2) . '/public';
        
        // Handle root path
        if ($route === '/') {
            return $basePath . '/index.php';
        }
        
        // Handle admin routes
        if (strpos($route, '/admin') === 0) {
            if ($route === '/admin' || $route === '/admin/') {
                return $basePath . '/admin/index.php';
            }
            return $basePath . $route;
        }
        
        // Handle API routes
        if (strpos($route, '/api') === 0) {
            return $basePath . $route;
        }
        
        // Default to public directory
        return $basePath . $route;
    }
    
    /**
     * Test error handling for malformed requests
     * 
     * @test
     * @group functional
     * @group security
     */
    public function it_handles_malformed_oauth_callback_requests(): void
    {
        // Test callback without required parameters
        $response = $this->simulateGetRequest('/api/auth/callback.php');
        
        $this->assertEquals(302, $response['status']);
        $this->assertStringContainsString('/admin/login.php?error=invalid_request', $response['headers']['Location']);
        
        // Test callback with malformed authorization code
        $response = $this->simulateGetRequest('/api/auth/callback.php?code=invalid&state=test');
        
        $this->assertEquals(302, $response['status']);
        $this->assertStringContainsString('/admin/login.php?error=', $response['headers']['Location']);
    }
    
    /**
     * Test Korean localization in error messages
     * 
     * @test
     * @group functional
     * @group localization
     */
    public function it_displays_korean_error_messages_correctly(): void
    {
        $testCases = [
            'not_authorized' => '관리자 권한이 없습니다',
            'csrf_error' => '보안 오류가 발생했습니다',
            'oauth_error' => 'osu! 인증 서비스에 문제가 발생했습니다',
            'server_error' => '서버 오류가 발생했습니다'
        ];
        
        foreach ($testCases as $errorType => $expectedMessage) {
            $response = $this->simulateGetRequest("/admin/login.php?error={$errorType}");
            
            $this->assertEquals(200, $response['status']);
            $this->assertStringContainsString($expectedMessage, $response['body']);
        }
    }
}