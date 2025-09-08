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
                host_name VARCHAR(255),
                mode VARCHAR(50),
                rank_range VARCHAR(255),
                registration_status VARCHAR(50),
                badge_prize BOOLEAN DEFAULT 0,
                start_date TEXT,
                end_date TEXT,
                banner_url TEXT,
                registration_link TEXT,
                discord_link TEXT,
                sheet_link TEXT,
                stream_link TEXT,
                forum_link TEXT NOT NULL DEFAULT 'https://osu.ppy.sh/community/forums/topics/',
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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                language_detected TEXT NOT NULL DEFAULT 'en',
                parsed_terms_used TEXT,
                
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

    
    public function testFindPendingReviewReturnsEmptyWhenNoPendingTournaments()
    {
        $result = $this->tournament->findPendingReview();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testFindPendingReviewReturnsPendingTournaments()
    {
        // Create test tournaments with different statuses
        $this->testDb->exec("
            INSERT INTO tournaments (osu_topic_id, title, status, parsed_at) VALUES 
            (12345, 'Test Tournament 1', 'pending_review', '2025-09-08 10:00:00'),
            (12346, 'Test Tournament 2', 'approved', '2025-09-08 11:00:00'),
            (12347, 'Test Tournament 3', 'pending_review', '2025-09-08 12:00:00'),
            (12348, 'Test Tournament 4', 'rejected', '2025-09-08 13:00:00')
        ");
        
        $result = $this->tournament->findPendingReview();
        
        // Should return only pending_review tournaments
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Check that only pending tournaments are returned
        foreach ($result as $tournament) {
            $this->assertArrayHasKey('id', $tournament);
            $this->assertArrayHasKey('title', $tournament);
            $this->assertArrayHasKey('parsed_at', $tournament);
        }
        
        // Check ordering (newest first by parsed_at)
        $this->assertEquals('Test Tournament 3', $result[0]['title']);
        $this->assertEquals('Test Tournament 1', $result[1]['title']);
    }
    
    public function testFindPendingReviewHandlesKoreanTitles()
    {
        // Test with Korean tournament titles
        $this->testDb->exec("
            INSERT INTO tournaments (osu_topic_id, title, status, parsed_at) VALUES 
            (12345, '한국 토너먼트 테스트', 'pending_review', '2025-09-08 10:00:00'),
            (12346, '오스 토너먼트 2025', 'pending_review', '2025-09-08 11:00:00')
        ");
        
        $result = $this->tournament->findPendingReview();
        
        $this->assertCount(2, $result);
        $this->assertEquals('오스 토너먼트 2025', $result[0]['title']);
        $this->assertEquals('한국 토너먼트 테스트', $result[1]['title']);
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
    
    // ====== STORY 1.6 TESTS: Tournament Edit & Approve Functionality ======
    
    public function testUpdateTournamentSuccess()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Original Tournament Title',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Update tournament data
        $updateData = [
            'title' => 'Updated Tournament Title',
            'rank_range' => '#1,000 - #10,000',
            'team_size' => '4',
            'max_teams' => '32',
            'start_date' => '2025-10-01T10:00',
            'registration_close' => '2025-09-25T23:59',
            'sheet_link' => 'https://docs.google.com/spreadsheets/d/test123456'
        ];
        
        $result = $this->tournament->update($tournamentId, $updateData);
        $this->assertTrue($result);
        
        // Verify the update
        $updatedTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('Updated Tournament Title', $updatedTournament['title']);
        $this->assertEquals(1000, $updatedTournament['rank_range_min']);
        $this->assertEquals(10000, $updatedTournament['rank_range_max']);
        $this->assertEquals(4, $updatedTournament['team_size']);
        $this->assertEquals(32, $updatedTournament['max_teams']);
        $this->assertEquals('2025-10-01 10:00:00', $updatedTournament['tournament_start']);
        $this->assertEquals('2025-09-25 23:59:00', $updatedTournament['registration_close']);
        $this->assertEquals('test123456', $updatedTournament['google_sheet_id']);
    }
    
    public function testUpdateTournamentWithEmptyTitleThrowsException()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Original Tournament Title',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tournament title is required');
        
        $this->tournament->update($tournamentId, ['title' => '']);
    }
    
    public function testUpdateTournamentWithInvalidTeamSizeThrowsException()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Team size must be a positive integer');
        
        $this->tournament->update($tournamentId, [
            'title' => 'Test Tournament',
            'team_size' => '-1'
        ]);
    }
    
    public function testUpdateTournamentWithInvalidMaxTeamsThrowsException()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max teams must be a positive integer');
        
        $this->tournament->update($tournamentId, [
            'title' => 'Test Tournament',
            'max_teams' => '0'
        ]);
    }
    
    public function testUpdateTournamentWithInvalidURLThrowsException()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL format for sheet_link');
        
        $this->tournament->update($tournamentId, [
            'title' => 'Test Tournament',
            'sheet_link' => 'not-a-valid-url'
        ]);
    }
    
    public function testUpdateTournamentWithKoreanCharacters()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Original Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Update with Korean text
        $updateData = [
            'title' => '한국 토너먼트 테스트 2025',
            'rank_range' => '#1,000 - #10,000'
        ];
        
        $result = $this->tournament->update($tournamentId, $updateData);
        $this->assertTrue($result);
        
        // Verify Korean text was saved correctly
        $updatedTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('한국 토너먼트 테스트 2025', $updatedTournament['title']);
        $this->assertEquals(1000, $updatedTournament['rank_range_min']);
        $this->assertEquals(10000, $updatedTournament['rank_range_max']);
    }
    
    public function testUpdateTournamentWithNullFieldsHandling()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Update with some null/empty fields
        $updateData = [
            'title' => 'Updated Tournament',
            'rank_range' => '#1,000 - #10,000',
            'team_size' => '4',
            'max_teams' => '32'
        ];
        
        $result = $this->tournament->update($tournamentId, $updateData);
        $this->assertTrue($result);
        
        // Verify the update was successful
        $updatedTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('Updated Tournament', $updatedTournament['title']);
        $this->assertEquals(1000, $updatedTournament['rank_range_min']);
        $this->assertEquals(10000, $updatedTournament['rank_range_max']);
        $this->assertEquals(4, $updatedTournament['team_size']);
        $this->assertEquals(32, $updatedTournament['max_teams']);
    }
    
    public function testApproveTournamentSuccess()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        $adminUserId = 1;
        
        // Verify initial status
        $tournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('pending_review', $tournament['status']);
        $this->assertNull($tournament['approved_at']);
        
        // Approve tournament
        $result = $this->tournament->approve($tournamentId, $adminUserId);
        $this->assertTrue($result);
        
        // Verify approval
        $approvedTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('approved', $approvedTournament['status']);
        $this->assertNotNull($approvedTournament['approved_at']);
        $this->assertEquals($adminUserId, $approvedTournament['approved_by']);
    }
    
    public function testApproveTournamentWithoutAdminId()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Approve tournament without specifying admin ID
        $result = $this->tournament->approve($tournamentId);
        $this->assertTrue($result);
        
        // Verify approval without admin ID
        $approvedTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('approved', $approvedTournament['status']);
        $this->assertNotNull($approvedTournament['approved_at']);
        $this->assertNull($approvedTournament['approved_by']);
    }
    
    public function testUpdateAndApproveTournamentWorkflow()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // First, update tournament data
        $updateData = [
            'title' => 'Updated and Approved Tournament',
            'rank_range' => '#1,000 - #10,000',
            'team_size' => '4',
            'max_teams' => '32'
        ];
        
        $updateResult = $this->tournament->update($tournamentId, $updateData);
        $this->assertTrue($updateResult);
        
        // Then, approve the tournament
        $approveResult = $this->tournament->approve($tournamentId, 1);
        $this->assertTrue($approveResult);
        
        // Verify final state
        $finalTournament = $this->tournament->findById($tournamentId);
        $this->assertEquals('Updated and Approved Tournament', $finalTournament['title']);
        $this->assertEquals(1000, $finalTournament['rank_range_min']);
        $this->assertEquals(10000, $finalTournament['rank_range_max']);
        $this->assertEquals(4, $finalTournament['team_size']);
        $this->assertEquals(32, $finalTournament['max_teams']);
        $this->assertEquals('approved', $finalTournament['status']);
        $this->assertNotNull($finalTournament['approved_at']);
        $this->assertEquals(1, $finalTournament['approved_by']);
    }
    
    public function testSystemLogsCreatedOnTournamentUpdate()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Update tournament
        $updateData = [
            'title' => 'Updated Tournament',
            'host_name' => 'New Host'
        ];
        
        $this->tournament->update($tournamentId, $updateData);
        
        // Check that update log entry was created
        $stmt = $this->testDb->prepare("SELECT COUNT(*) FROM system_logs WHERE source = ?");
        $stmt->execute(['Tournament::update']);
        
        $logCount = $stmt->fetchColumn();
        $this->assertEquals(1, $logCount);
    }
    
    public function testSystemLogsCreatedOnTournamentApproval()
    {
        // Create test tournament
        $forumPostData = [
            'id' => 123456,
            'title' => 'Test Tournament',
            'content' => 'Tournament content here'
        ];
        
        $tournamentId = $this->tournament->createFromForumPost($forumPostData);
        
        // Approve tournament
        $this->tournament->approve($tournamentId, 1);
        
        // Check that approval log entry was created
        $stmt = $this->testDb->prepare("SELECT COUNT(*) FROM system_logs WHERE source = ?");
        $stmt->execute(['Tournament::approve']);
        
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