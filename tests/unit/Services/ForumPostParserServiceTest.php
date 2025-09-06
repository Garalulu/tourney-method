<?php

namespace TourneyMethod\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Services\ForumPostParserService;
use InvalidArgumentException;

/**
 * Unit tests for ForumPostParserService
 * 
 * Covers QA test scenarios:
 * - 1.4-UNIT-001: Tournament title extraction validation
 * - 1.4-UNIT-002: Rank range pattern matching
 * - 1.4-UNIT-003: Tournament date extraction patterns
 * - 1.4-UNIT-004: Host name extraction from signatures
 * - 1.4-UNIT-005: Korean character processing validation
 * - 1.4-UNIT-012: Graceful extraction failure handling
 */
class ForumPostParserServiceTest extends TestCase
{
    private ForumPostParserService $parser;
    
    protected function setUp(): void
    {
        $this->parser = new ForumPostParserService();
    }
    
    /**
     * Test 1.4-UNIT-001: Tournament title extraction validation
     */
    public function testTournamentTitleExtractionFromHeaders(): void
    {
        $content = "# Amazing osu! Tournament\n\nThis is a tournament description.";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Amazing osu! Tournament', $result['title']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['title']);
    }
    
    public function testTournamentTitleExtractionFromBold(): void
    {
        $content = "**Super Tournament 2024**\n\nRegistration is open!";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Super Tournament 2024', $result['title']);
    }
    
    public function testTournamentTitleExtractionFallbackToTopicTitle(): void
    {
        $content = "Some random content without structured title.";
        $topicTitle = "[Tournament] Korean Cup 2024 - Open Rank";
        
        $result = $this->parser->parseForumPost($content, $topicTitle);
        
        $this->assertEquals('Korean Cup 2024 - Open Rank', $result['title']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_LOW, $result['extraction_confidence']['title']);
    }
    
    public function testTournamentTitleCleaningRemovesPrefixes(): void
    {
        $topicTitle = "[Tournament] Amazing Cup 2024";
        
        $result = $this->parser->parseForumPost("No structured content", $topicTitle);
        
        $this->assertEquals('Amazing Cup 2024', $result['title']);
        $this->assertStringNotContainsString('[Tournament]', $result['title']);
    }
    
    /**
     * Test 1.4-UNIT-002: Rank range pattern matching
     */
    public function testRankRangeExtractionStandardFormat(): void
    {
        $content = "Rank Range: 100K+";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('100K+', $result['rank_range']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['rank_range']);
    }
    
    public function testRankRangeExtractionOpenFormat(): void
    {
        $content = "Rank: Open";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Open', $result['rank_range']);
    }
    
    public function testRankRangeExtractionBWSFormat(): void
    {
        $content = "BWS: 50K+";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('50K+', $result['rank_range']);
    }
    
    public function testRankRangeExtractionInBrackets(): void
    {
        $content = "Tournament for [25K+] players only";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('25K+', $result['rank_range']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_MEDIUM, $result['extraction_confidence']['rank_range']);
    }
    
    public function testRankRangeNormalizationKoreanToEnglish(): void
    {
        $content = "Rank Range: 오픈";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Open', $result['rank_range']);
    }
    
    /**
     * Test 1.4-UNIT-003: Tournament date extraction patterns
     */
    public function testDateExtractionStandardFormat(): void
    {
        $content = "Date: March 15, 2024 - March 20, 2024";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('March 15, 2024 - March 20, 2024', $result['tournament_dates']);
    }
    
    public function testDateExtractionNumericFormat(): void
    {
        $content = "Schedule: 03/15/2024 - 03/20/2024";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('03/15/2024 - 03/20/2024', $result['tournament_dates']);
    }
    
    public function testDateExtractionKoreanLabels(): void
    {
        $content = "일정: 2024년 3월 15일부터 20일까지";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('2024년 3월 15일부터 20일까지', $result['tournament_dates']);
    }
    
    /**
     * Test 1.4-UNIT-004: Host name extraction from signatures
     */
    public function testHostExtractionStandardFormat(): void
    {
        $content = "Host: PlayerName\nTournament details here...";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('PlayerName', $result['host_name']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['host_name']);
    }
    
    public function testHostExtractionKoreanFormat(): void
    {
        $content = "주최자: 한국플레이어\nDetails...";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('한국플레이어', $result['host_name']);
    }
    
    public function testHostExtractionFromSignature(): void
    {
        $content = "Tournament content\n\n---\nHosted by SignatureUser";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertNotNull($result['host_name']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_LOW, $result['extraction_confidence']['host_name']);
    }
    
    public function testHostValidationRejectsUrls(): void
    {
        $content = "Host: https://example.com\nMore content...";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertNull($result['host_name']); // Should reject URLs as host names
    }
    
    /**
     * Test 1.4-UNIT-005: Korean character processing validation
     */
    public function testKoreanCharacterTitleExtraction(): void
    {
        $content = "# 한국 토너먼트 2024\n대회 설명입니다.";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('한국 토너먼트 2024', $result['title']);
        $this->assertIsString($result['title']); // Verify UTF-8 handling
    }
    
    public function testKoreanTermNormalization(): void
    {
        $content = "Rank: 제한없음\nBadge: 있음";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Open', $result['rank_range']);
        $this->assertTrue($result['has_badge']);
    }
    
    public function testMixedKoreanEnglishContent(): void
    {
        $content = "# Korean English Tournament\nHost: 한국호스트\nRank Range: Open\n날짜: March 2024";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('Korean English Tournament', $result['title']);
        $this->assertEquals('한국호스트', $result['host_name']);
        $this->assertEquals('Open', $result['rank_range']);
        $this->assertEquals('March 2024', $result['tournament_dates']);
    }
    
    /**
     * Test badge extraction
     */
    public function testBadgeExtractionPositive(): void
    {
        $content = "Badge: Yes\nProfile badge will be awarded.";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertTrue($result['has_badge']);
    }
    
    public function testBadgeExtractionNegative(): void
    {
        $content = "Badge: No\nNo profile badge.";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertFalse($result['has_badge']);
    }
    
    public function testBadgeExtractionKorean(): void
    {
        $content = "배지: 있음\n우승자에게 프로필 배지가 지급됩니다.";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertTrue($result['has_badge']);
    }
    
    /**
     * Test banner URL extraction
     */
    public function testBannerUrlExtractionBBCode(): void
    {
        $content = "[img]https://example.com/banner.png[/img]\nTournament details...";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('https://example.com/banner.png', $result['banner_url']);
    }
    
    public function testBannerUrlExtractionHTML(): void
    {
        $content = '<img src="https://example.com/tournament-banner.jpg" alt="Banner">';
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertEquals('https://example.com/tournament-banner.jpg', $result['banner_url']);
    }
    
    public function testBannerUrlValidation(): void
    {
        $content = "[img]not-a-valid-url[/img]";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertNull($result['banner_url']);
    }
    
    /**
     * Test 1.4-UNIT-012: Graceful extraction failure handling
     */
    public function testGracefulFailureWithEmptyContent(): void
    {
        $result = $this->parser->parseForumPost('');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('host_name', $result);
        $this->assertArrayHasKey('rank_range', $result);
        $this->assertArrayHasKey('extraction_confidence', $result);
        
        // All fields should be null for empty content
        $this->assertNull($result['title']);
        $this->assertNull($result['host_name']);
        $this->assertNull($result['rank_range']);
    }
    
    public function testGracefulFailureWithMalformedContent(): void
    {
        $malformedContent = "<<<>>><script>alert('test')</script>Some random text";
        
        $result = $this->parser->parseForumPost($malformedContent);
        
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('<script>', json_encode($result)); // Ensure XSS protection
    }
    
    public function testInputValidationLargeContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input content exceeds maximum allowed length');
        
        $largeContent = str_repeat('A', 100001); // Exceeds 100KB limit
        $this->parser->parseForumPost($largeContent);
    }
    
    public function testInputSanitization(): void
    {
        $maliciousContent = 'Tournament<script>alert("xss")</script> Title\nHost: javascript:alert("xss")';
        
        $result = $this->parser->parseForumPost($maliciousContent);
        
        $this->assertStringNotContainsString('<script>', json_encode($result));
        $this->assertStringNotContainsString('javascript:', json_encode($result));
    }
    
    public function testConfidenceScoringSystem(): void
    {
        $highConfidenceContent = "# Clear Tournament Title\nHost: DefiniteHost\nRank Range: 100K+";
        
        $result = $this->parser->parseForumPost($highConfidenceContent);
        
        $this->assertArrayHasKey('extraction_confidence', $result);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['title']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['host_name']);
        $this->assertEquals(ForumPostParserService::CONFIDENCE_HIGH, $result['extraction_confidence']['rank_range']);
    }
    
    /**
     * Test edge cases and boundary conditions
     */
    public function testVeryShortValidContent(): void
    {
        $content = "ABC Tournament\nHost: XYZ";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertNull($result['title']); // Too short for title validation
        $this->assertEquals('XYZ', $result['host_name']);
    }
    
    public function testSpecialCharactersInContent(): void
    {
        $content = "Tournament: ™️ Special Cup® 2024™\nHost: Player_123-ABC\nRank: #1000-#5000";
        
        $result = $this->parser->parseForumPost($content);
        
        $this->assertIsString($result['title']);
        $this->assertEquals('Player_123-ABC', $result['host_name']);
        $this->assertEquals('#1000-#5000', $result['rank_range']);
    }
}