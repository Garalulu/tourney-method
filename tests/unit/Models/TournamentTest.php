<?php

namespace TourneyMethod\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Models\Tournament;
use PDO;

/**
 * Unit tests for Tournament model
 * 
 * Tests tournament CRUD operations, parser methods, and data validation
 */
class TournamentTest extends TestCase
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
    }
    
    protected function tearDown(): void
    {
        $this->testDb = null;
        $this->tournament = null;
    }
    
    private function createTestSchema(): void
    {
        // Create simplified test schema based on main schema
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
        
        $this->testDb->exec("
            CREATE TABLE system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level VARCHAR(10) NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                source VARCHAR(100),
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                CHECK (level IN ('DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY')),
                CHECK (message != '')
            )
        ");
    }
    
    public function testTournamentCanBeConstructed()
    {
        $this->assertInstanceOf(Tournament::class, $this->tournament);
    }
    
    public function testStatusConstants()
    {
        $this->assertEquals('pending_review', Tournament::STATUS_PENDING);
        $this->assertEquals('approved', Tournament::STATUS_APPROVED);
        $this->assertEquals('rejected', Tournament::STATUS_REJECTED);
        $this->assertEquals('archived', Tournament::STATUS_ARCHIVED);
    }
    
    public function testFindByTopicIdReturnsNullWhenNotFound()
    {
        $result = $this->tournament->findByTopicId(999999);
        $this->assertNull($result);
    }
    
    public function testCreateFromForumPostSuccess()
    {
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament 2025',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $this->assertIsInt($tournamentId);
        $this->assertGreaterThan(0, $tournamentId);
        
        // Verify tournament was created
        $tournament = $this->tournament->findByTopicId(123456);
        $this->assertNotNull($tournament);
        $this->assertEquals('Test Tournament 2025', $tournament['title']);
        $this->assertEquals('pending_review', $tournament['status']);
    }
    
    public function testCreateFromForumPostWithDuplicateThrowsException()
    {
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament 2025',
            'content' => 'Tournament content here'
        ];
        
        // Create first tournament
        $this->tournament->createFromForumPost($forumPostData);
        
        // Attempt to create duplicate should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tournament with topic ID 123456 already exists');
        
        $this->tournament->createFromForumPost($forumPostData);
    }
    
    public function testCreateFromForumPostWithInvalidDataThrowsException()
    {
        $invalidData = [
            'id' => 0, // Invalid topic ID
            'title' => 'Test Tournament'
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid topic ID: must be positive integer');
        
        $this->tournament->createFromForumPost($invalidData);
    }
    
    public function testCreateFromForumPostWithMissingTitleThrowsException()
    {
        $invalidData = [
            'id' => 123456,
            'title' => '' // Empty title
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tournament title cannot be empty');
        
        $this->tournament->createFromForumPost($invalidData);
    }
    
    public function testCreateFromForumPostWithMissingIdThrowsException()
    {
        $invalidData = [
            'title' => 'Test Tournament'
            // Missing 'id' field
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: id');
        
        $this->tournament->createFromForumPost($invalidData);
    }
    
    public function testUpdateStatusSuccess()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament 2025',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Update status
        $result = $this->tournament->updateStatus($tournamentId, Tournament::STATUS_APPROVED, 1);
        $this->assertTrue($result);
        
        // Verify status was updated
        $tournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('approved', $tournament['status']);
    }
    
    public function testUpdateStatusWithInvalidStatusThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tournament status');
        
        $this->tournament->updateStatus(1, 'invalid_status');
    }
    
    public function testGetAllTournamentsWithDefaults()
    {
        // Create test tournaments
        $this->createTestTournaments();
        
        $tournaments = $this->tournament->getAllTournaments();
        $this->assertIsArray($tournaments);
        $this->assertLessThanOrEqual(50, count($tournaments)); // Default limit
    }
    
    public function testGetAllTournamentsWithStatusFilter()
    {
        // Create test tournaments
        $this->createTestTournaments();
        
        $tournaments = $this->tournament->getAllTournaments(50, 0, Tournament::STATUS_PENDING);
        $this->assertIsArray($tournaments);
        
        // Verify all returned tournaments have pending status
        foreach ($tournaments as $tournament) {
            $this->assertEquals('pending_review', $tournament['status']);
        }
    }
    
    public function testGetCountByStatus()
    {
        // Create test tournaments
        $this->createTestTournaments();
        
        $totalCount = $this->tournament->getCountByStatus();
        $this->assertGreaterThan(0, $totalCount);
        
        $pendingCount = $this->tournament->getCountByStatus(Tournament::STATUS_PENDING);
        $this->assertGreaterThanOrEqual(0, $pendingCount);
    }
    
    public function testFindByIdReturnsNullWhenNotFound()
    {
        $result = $this->tournament->findById(999999);
        $this->assertNull($result);
    }
    
    public function testFindByIdReturnsValidData()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament 2025',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $tournament = $this->tournament->findById($tournamentId);
        $this->assertNotNull($tournament);
        $this->assertEquals($tournamentId, $tournament['id']);
        $this->assertEquals(123456, $tournament['osu_topic_id']);
        $this->assertEquals('Test Tournament 2025', $tournament['title']);
    }
    
    public function testTitleSanitization()
    {
        // Test with excessive whitespace
        $forumPostData = [
            'id' => 123456,
            'title' => '   Test    Tournament     2025   ',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        $tournament = $this->tournament->findById($tournamentId);
        
        $this->assertEquals('Test Tournament 2025', $tournament['title']);
    }
    
    public function testLongTitleTruncation()
    {
        // Test with very long title (over 500 chars)
        $longTitle = str_repeat('A', 505);
        
        $forumPostData = [
            'id' => 123456,
            'title' => $longTitle,
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        $tournament = $this->tournament->findById($tournamentId);
        
        $this->assertLessThanOrEqual(500, strlen($tournament['title']));
        $this->assertStringEndsWith('...', $tournament['title']);
    }
    
    public function testSystemLogsCreatedOnTournamentCreation()
    {
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament 2025',
            'content' => 'Tournament content here'
        ];
        
        $this->tournament->createFromForumPost($forumPostData);
        
        // Check that a log entry was created
        $stmt = $this->testDb->prepare("SELECT COUNT(*) FROM system_logs WHERE source = ?");
        $stmt->execute(['Tournament::createFromForumPost']);
        
        $logCount = $stmt->fetchColumn();
        $this->assertEquals(1, $logCount);
    }
    
    /**
     * Helper method to create test tournaments
     */
    private function createTestTournaments(): void
    {
        $testData = [
            ['id' => 111111, 'title' => 'Tournament A'],
            ['id' => 222222, 'title' => 'Tournament B'],
            ['id' => 333333, 'title' => 'Tournament C']
        ];
        
        foreach ($testData as $data) {
            $forumPostData = [
                'id' => $data['id'],
                'title' => $data['title'],
                'content' => 'Test content'
            ];
            
            $this->tournament->createFromForumPost($forumPostData);
        }
    }
}