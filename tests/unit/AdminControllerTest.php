<?php

namespace TourneyMethod\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Models\Tournament;
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;
use PDO;

/**
 * Unit tests for Admin Controller functionality
 * 
 * Tests admin dashboard, tournament review table, and Korean timezone formatting
 */
class AdminControllerTest extends TestCase
{
    private ?PDO $testDb = null;
    private ?Tournament $tournament = null;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->testDb = new PDO('sqlite::memory:');
        $this->testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->testDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Create test schema
        $this->createTestSchema();
        
        $this->tournament = new Tournament($this->testDb);
        
        // Start session for CSRF token testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    protected function tearDown(): void
    {
        $this->testDb = null;
        $this->tournament = null;
        
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }
    
    private function createTestSchema(): void
    {
        // Create tournaments table matching production schema
        $this->testDb->exec("
            CREATE TABLE tournaments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                osu_topic_id INTEGER UNIQUE NOT NULL,
                title VARCHAR(500) NOT NULL,
                status VARCHAR(20) DEFAULT 'pending_review',
                rank_range_min INTEGER,
                rank_range_max INTEGER,
                team_size INTEGER,
                max_teams INTEGER,
                registration_open DATETIME,
                registration_close DATETIME,
                tournament_start DATETIME,
                google_sheet_id VARCHAR(100),
                forum_url_slug VARCHAR(100),
                google_form_id VARCHAR(100),
                challonge_slug VARCHAR(100),
                youtube_id VARCHAR(20),
                twitch_username VARCHAR(50),
                raw_post_content TEXT,
                parsed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved_at DATETIME,
                approved_by INTEGER,
                
                CHECK (status IN ('pending_review', 'approved', 'rejected', 'archived')),
                CHECK (osu_topic_id > 0),
                CHECK (title != ''),
                CHECK (rank_range_min IS NULL OR rank_range_min >= 0),
                CHECK (rank_range_max IS NULL OR rank_range_max >= 0),
                CHECK (rank_range_min IS NULL OR rank_range_max IS NULL OR rank_range_min <= rank_range_max)
            )
        ");
    }
    
    public function testDashboardDisplaysPendingTournaments()
    {
        // Create test tournaments with different statuses
        $this->testDb->exec("
            INSERT INTO tournaments (osu_topic_id, title, status, parsed_at) VALUES 
            (12345, 'Test Tournament 1', 'pending_review', '2025-09-08 10:00:00'),
            (12346, 'Test Tournament 2', 'approved', '2025-09-08 11:00:00'),
            (12347, 'Test Tournament 3', 'pending_review', '2025-09-08 12:00:00')
        ");
        
        $pendingTournaments = $this->tournament->findPendingReview();
        
        // Verify dashboard would show correct pending tournaments
        $this->assertCount(2, $pendingTournaments);
        $this->assertEquals('Test Tournament 3', $pendingTournaments[0]['title']);
        $this->assertEquals('Test Tournament 1', $pendingTournaments[1]['title']);
    }
    
    public function testDashboardHandlesEmptyPendingTournaments()
    {
        // No pending tournaments in database
        $pendingTournaments = $this->tournament->findPendingReview();
        
        $this->assertIsArray($pendingTournaments);
        $this->assertEmpty($pendingTournaments);
    }
    
    public function testDashboardKoreanTitleDisplay()
    {
        // Test with Korean tournament titles (real Korean tournament names)
        $this->testDb->exec("
            INSERT INTO tournaments (osu_topic_id, title, status, parsed_at) VALUES 
            (12345, '한국 osu! 토너먼트 2025', 'pending_review', '2025-09-08 10:00:00'),
            (12346, 'osu! 태극기 컵', 'pending_review', '2025-09-08 11:00:00')
        ");
        
        $pendingTournaments = $this->tournament->findPendingReview();
        
        $this->assertCount(2, $pendingTournaments);
        
        // Test that Korean text is properly handled
        foreach ($pendingTournaments as $tournament) {
            $escapedTitle = SecurityHelper::escapeHtml($tournament['title']);
            $this->assertNotEmpty($escapedTitle);
            $this->assertStringContainsString('osu!', $escapedTitle);
        }
    }
    
    public function testKoreanTimezoneFormatting()
    {
        // Test DateHelper KST formatting
        $utcDateTime = '2025-09-08 03:00:00'; // UTC time
        $kstFormatted = DateHelper::formatToKST($utcDateTime);
        
        // Should be converted to KST (UTC+9)
        $this->assertEquals('2025-09-08 12:00:00', $kstFormatted);
        
        // Test date-only formatting
        $kstDateOnly = DateHelper::formatToKSTDate($utcDateTime);
        $this->assertEquals('2025-09-08', $kstDateOnly);
    }
    
    public function testEditLinkGeneration()
    {
        // Create test tournament
        $this->testDb->exec("
            INSERT INTO tournaments (osu_topic_id, title, status, parsed_at) VALUES 
            (12345, 'Test Tournament', 'pending_review', '2025-09-08 10:00:00')
        ");
        
        // Get tournament
        $tournaments = $this->tournament->findPendingReview();
        $tournament = $tournaments[0];
        
        // Test edit link generation (simulating template logic)
        $_SESSION['csrf_token'] = 'test_token_123';
        $editUrl = "/admin/edit.php?id=" . (int)$tournament['id'] . "&csrf_token=" . $_SESSION['csrf_token'];
        
        $this->assertStringContainsString('/admin/edit.php?id=', $editUrl);
        $this->assertStringContainsString('csrf_token=test_token_123', $editUrl);
        $this->assertIsNumeric($tournament['id']);
    }
    
    public function testSecurityEscaping()
    {
        // Test with potentially malicious input
        $maliciousTitle = '<script>alert("XSS")</script>Tournament';
        
        $stmt = $this->testDb->prepare("
            INSERT INTO tournaments (osu_topic_id, title, status) VALUES 
            (12345, ?, 'pending_review')
        ");
        $stmt->execute([$maliciousTitle]);
        
        $tournaments = $this->tournament->findPendingReview();
        $tournament = $tournaments[0];
        
        // Test that SecurityHelper properly escapes the title
        $escapedTitle = SecurityHelper::escapeHtml($tournament['title']);
        
        $this->assertStringNotContainsString('<script>', $escapedTitle);
        $this->assertStringContainsString('&lt;script&gt;', $escapedTitle);
        $this->assertStringContainsString('Tournament', $escapedTitle);
    }
    
    public function testCSRFTokenGeneration()
    {
        // Test CSRF token generation
        $token1 = SecurityHelper::generateCsrfToken();
        $token2 = SecurityHelper::generateCsrfToken();
        
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2); // Should be unique
        $this->assertEquals(64, strlen($token1)); // Should be 32 bytes = 64 hex chars
    }
    
    public function testCSRFTokenValidation()
    {
        // Generate a token
        $validToken = SecurityHelper::generateCsrfToken();
        $_SESSION['csrf_token'] = $validToken;
        
        // Test valid token
        $this->assertTrue(SecurityHelper::validateCsrfToken($validToken, $_SESSION['csrf_token']));
        
        // Test invalid token
        $this->assertFalse(SecurityHelper::validateCsrfToken('invalid_token', $_SESSION['csrf_token']));
        
        // Test empty token
        $this->assertFalse(SecurityHelper::validateCsrfToken('', $_SESSION['csrf_token']));
    }
}