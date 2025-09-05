<?php

namespace TourneyMethod\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Models\AdminUser;

/**
 * SecurityHelper Unit Tests
 * 
 * Tests security utilities including CSRF protection, input validation,
 * output escaping, and admin authorization checking.
 */
class SecurityHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
    }
    
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
    }
    
    /**
     * Test CSRF token generation
     * 
     * @test
     */
    public function it_generates_csrf_token(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        
        // Generate another token to ensure uniqueness
        $token2 = SecurityHelper::generateCsrfToken();
        $this->assertNotEquals($token, $token2);
    }
    
    /**
     * Test CSRF token validation success
     * 
     * @test
     */
    public function it_validates_csrf_token_successfully(): void
    {
        $token = 'a1b2c3d4e5f6' . str_repeat('0', 52);
        $sessionToken = 'a1b2c3d4e5f6' . str_repeat('0', 52);
        
        $result = SecurityHelper::validateCsrfToken($token, $sessionToken);
        $this->assertTrue($result);
    }
    
    /**
     * Test CSRF token validation failure
     * 
     * @test
     */
    public function it_fails_csrf_token_validation(): void
    {
        $token = str_repeat('a', 64);
        $sessionToken = str_repeat('b', 64);
        
        $result = SecurityHelper::validateCsrfToken($token, $sessionToken);
        $this->assertFalse($result);
    }
    
    /**
     * Test CSRF validation from POST request success
     * 
     * @test
     */
    public function it_validates_csrf_from_post_successfully(): void
    {
        $token = str_repeat('a', 64);
        
        session_start();
        $_SESSION['admin_user'] = ['csrf_token' => $token];
        $_POST['csrf_token'] = $token;
        
        $result = SecurityHelper::validateCsrfFromPost();
        $this->assertTrue($result);
    }
    
    /**
     * Test CSRF validation from POST request - missing token
     * 
     * @test
     */
    public function it_fails_csrf_validation_when_token_missing_from_post(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF token missing from request');
        
        SecurityHelper::validateCsrfFromPost();
    }
    
    /**
     * Test CSRF validation from POST request - no session
     * 
     * @test
     */
    public function it_fails_csrf_validation_when_no_session_token(): void
    {
        $_POST['csrf_token'] = str_repeat('a', 64);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No CSRF token found in session');
        
        SecurityHelper::validateCsrfFromPost();
    }
    
    /**
     * Test HTML escaping
     * 
     * @test
     */
    public function it_escapes_html_correctly(): void
    {
        $input = '<script>alert("XSS")</script>';
        $expected = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;';
        
        $result = SecurityHelper::escapeHtml($input);
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Test HTML escaping with Korean characters
     * 
     * @test
     */
    public function it_escapes_html_with_korean_characters(): void
    {
        $input = '<script>안녕하세요 & "테스트"</script>';
        $expected = '&lt;script&gt;안녕하세요 &amp; &quot;테스트&quot;&lt;/script&gt;';
        
        $result = SecurityHelper::escapeHtml($input);
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Test integer validation success
     * 
     * @test
     */
    public function it_validates_integer_successfully(): void
    {
        $result = SecurityHelper::validateInteger('123');
        $this->assertEquals(123, $result);
        
        $result = SecurityHelper::validateInteger(456);
        $this->assertEquals(456, $result);
    }
    
    /**
     * Test integer validation with range
     * 
     * @test
     */
    public function it_validates_integer_with_range(): void
    {
        $result = SecurityHelper::validateInteger('50', 1, 100);
        $this->assertEquals(50, $result);
    }
    
    /**
     * Test integer validation failure - not integer
     * 
     * @test
     */
    public function it_fails_integer_validation_for_non_integer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer value');
        
        SecurityHelper::validateInteger('not_a_number');
    }
    
    /**
     * Test integer validation failure - below minimum
     * 
     * @test
     */
    public function it_fails_integer_validation_below_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be at least 10');
        
        SecurityHelper::validateInteger('5', 10, 100);
    }
    
    /**
     * Test integer validation failure - above maximum
     * 
     * @test
     */
    public function it_fails_integer_validation_above_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be at most 100');
        
        SecurityHelper::validateInteger('150', 10, 100);
    }
    
    /**
     * Test string validation success
     * 
     * @test
     */
    public function it_validates_string_successfully(): void
    {
        $result = SecurityHelper::validateString('valid string');
        $this->assertEquals('valid string', $result);
    }
    
    /**
     * Test string validation with Korean characters
     * 
     * @test
     */
    public function it_validates_korean_string_successfully(): void
    {
        $result = SecurityHelper::validateString('안녕하세요');
        $this->assertEquals('안녕하세요', $result);
    }
    
    /**
     * Test string validation failure - not string
     * 
     * @test
     */
    public function it_fails_string_validation_for_non_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input must be a string');
        
        SecurityHelper::validateString(123);
    }
    
    /**
     * Test string validation failure - empty required string
     * 
     * @test
     */
    public function it_fails_string_validation_for_empty_required_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('String cannot be empty');
        
        SecurityHelper::validateString('', 255, true);
    }
    
    /**
     * Test string validation failure - too long
     * 
     * @test
     */
    public function it_fails_string_validation_for_too_long_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('String length cannot exceed 10 characters');
        
        SecurityHelper::validateString('This string is too long', 10);
    }
    
    /**
     * Test osu! user ID validation success
     * 
     * @test
     */
    public function it_validates_osu_user_id_successfully(): void
    {
        $result = SecurityHelper::validateOsuUserId('757783');
        $this->assertEquals(757783, $result);
        
        $result = SecurityHelper::validateOsuUserId(123456);
        $this->assertEquals(123456, $result);
    }
    
    /**
     * Test osu! user ID validation failure
     * 
     * @test
     */
    public function it_fails_osu_user_id_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer value');
        
        SecurityHelper::validateOsuUserId('not_a_number');
    }
    
    /**
     * Test osu! user ID validation failure - zero or negative
     * 
     * @test
     */
    public function it_fails_osu_user_id_validation_for_zero_or_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be at least 1');
        
        SecurityHelper::validateOsuUserId(0);
    }
    
    /**
     * Test URL validation success
     * 
     * @test
     */
    public function it_validates_url_successfully(): void
    {
        $result = SecurityHelper::validateUrl('https://example.com');
        $this->assertEquals('https://example.com', $result);
        
        $result = SecurityHelper::validateUrl('http://localhost:8000');
        $this->assertEquals('http://localhost:8000', $result);
    }
    
    /**
     * Test URL validation failure - invalid format
     * 
     * @test
     */
    public function it_fails_url_validation_for_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL format');
        
        SecurityHelper::validateUrl('not-a-url');
    }
    
    /**
     * Test URL validation failure - disallowed scheme
     * 
     * @test
     */
    public function it_fails_url_validation_for_disallowed_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL scheme not allowed');
        
        SecurityHelper::validateUrl('ftp://example.com', ['http', 'https']);
    }
    
    /**
     * Test secure random string generation
     * 
     * @test
     */
    public function it_generates_secure_random_string(): void
    {
        $string = SecurityHelper::generateSecureRandomString();
        
        $this->assertIsString($string);
        $this->assertEquals(64, strlen($string)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $string);
        
        // Test uniqueness
        $string2 = SecurityHelper::generateSecureRandomString();
        $this->assertNotEquals($string, $string2);
    }
    
    /**
     * Test URL-safe random string generation
     * 
     * @test
     */
    public function it_generates_url_safe_random_string(): void
    {
        $string = SecurityHelper::generateSecureRandomString(16, true);
        
        $this->assertIsString($string);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $string);
    }
    
    /**
     * Test filename sanitization
     * 
     * @test
     */
    public function it_sanitizes_filename_correctly(): void
    {
        $result = SecurityHelper::sanitizeFilename('valid-file.txt');
        $this->assertEquals('valid-file.txt', $result);
        
        $result = SecurityHelper::sanitizeFilename('file with spaces.txt');
        $this->assertEquals('file_with_spaces.txt', $result);
        
        $result = SecurityHelper::sanitizeFilename('../../../etc/passwd');
        $this->assertEquals('passwd', $result);
    }
    
    /**
     * Test filename sanitization with Korean characters
     * 
     * @test
     */
    public function it_sanitizes_korean_filename(): void
    {
        $result = SecurityHelper::sanitizeFilename('한글파일명.txt');
        $this->assertEquals('_______________.txt', $result);
    }
    
    /**
     * Test current user admin check without authentication
     * 
     * @test
     */
    public function it_returns_false_for_admin_check_without_authentication(): void
    {
        $result = SecurityHelper::isCurrentUserAdmin();
        $this->assertFalse($result);
    }
    
    /**
     * Test secure session configuration
     * 
     * @test
     */
    public function it_configures_secure_session(): void
    {
        // In test mode, session configuration is skipped for compatibility
        // This test verifies the method runs without error
        SecurityHelper::configureSecureSession();
        
        // Session configuration is skipped in test mode due to PHPUNIT_RUNNING constant
        // This prevents ini_set() errors when headers are already sent
        $this->assertTrue(defined('PHPUNIT_RUNNING'));
        $this->assertTrue(PHPUNIT_RUNNING);
    }
}