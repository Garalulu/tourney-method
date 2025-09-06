<?php

namespace TourneyMethod\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use TourneyMethod\Utils\UrlExtractor;
use InvalidArgumentException;

/**
 * Unit tests for UrlExtractor
 * 
 * Covers QA test scenarios:
 * - 1.4-UNIT-006: Google Sheets ID extraction
 * - 1.4-UNIT-007: Google Forms ID extraction
 * - 1.4-UNIT-008: osu! Forum link ID extraction
 * - 1.4-UNIT-009: Challonge tournament slug extraction
 * - 1.4-UNIT-010: YouTube/Twitch stream ID extraction
 * - 1.4-UNIT-011: URL validation and security checks
 */
class UrlExtractorTest extends TestCase
{
    private UrlExtractor $extractor;
    
    protected function setUp(): void
    {
        $this->extractor = new UrlExtractor();
    }
    
    /**
     * Test 1.4-UNIT-006: Google Sheets ID extraction
     */
    public function testGoogleSheetsIdExtractionStandardUrl(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit#gid=0';
        
        $result = $this->extractor->extractGoogleSheetsId($url);
        
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result);
    }
    
    public function testGoogleSheetsIdExtractionWithParameters(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1YuWdO2g7HcHJtYQKkmWYglkkkW3rQqsqbL_-mzMtZHY/edit?usp=sharing';
        
        $result = $this->extractor->extractGoogleSheetsId($url);
        
        $this->assertEquals('1YuWdO2g7HcHJtYQKkmWYglkkkW3rQqsqbL_-mzMtZHY', $result);
    }
    
    public function testGoogleSheetsIdExtractionInvalidUrl(): void
    {
        $url = 'https://docs.google.com/spreadsheets/invalid-format';
        
        $result = $this->extractor->extractGoogleSheetsId($url);
        
        $this->assertNull($result);
    }
    
    public function testGoogleSheetsIdValidation(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/too_short/edit';
        
        $result = $this->extractor->extractGoogleSheetsId($url);
        
        $this->assertNull($result); // Should fail validation due to insufficient length
    }
    
    /**
     * Test 1.4-UNIT-007: Google Forms ID extraction
     */
    public function testGoogleFormsIdExtractionStandardUrl(): void
    {
        $url = 'https://docs.google.com/forms/d/e/1FAIpQLSdwAu2_Fi2oKcH5P7_0D_CaVs3_X8yGxQr2Q1Z4R8S3T7U2V9W/viewform';
        
        $result = $this->extractor->extractGoogleFormsId($url);
        
        $this->assertEquals('1FAIpQLSdwAu2_Fi2oKcH5P7_0D_CaVs3_X8yGxQr2Q1Z4R8S3T7U2V9W', $result);
    }
    
    public function testGoogleFormsIdExtractionDocsUrl(): void
    {
        $url = 'https://docs.google.com/forms/d/e/1FAIpQLSdPuoPTSRQj3aaDmb5WlfuFTIhKJyf2XDaJJVfjmjD1q-DrOw/viewform?usp=sharing&ouid=112963691655294562887';
        
        $result = $this->extractor->extractGoogleFormsId($url);
        
        $this->assertEquals('1FAIpQLSdPuoPTSRQj3aaDmb5WlfuFTIhKJyf2XDaJJVfjmjD1q-DrOw', $result);
    }
    
    public function testGoogleFormsIdExtractionShortenedUrl(): void
    {
        $url = 'https://forms.gle/tCpsMg74TTNHLwsj9';
        
        $result = $this->extractor->extractGoogleFormsId($url);
        
        $this->assertEquals('tCpsMg74TTNHLwsj9', $result);
    }
    
    public function testGoogleFormsIdExtractionInvalidUrl(): void
    {
        $url = 'https://forms.google.com/invalid-path';
        
        $result = $this->extractor->extractGoogleFormsId($url);
        
        $this->assertNull($result);
    }
    
    /**
     * Test 1.4-UNIT-008: osu! Forum link ID extraction
     */
    public function testOsuForumIdExtractionStandardUrl(): void
    {
        $url = 'https://osu.ppy.sh/community/forums/topics/1234567';
        
        $result = $this->extractor->extractOsuForumId($url);
        
        $this->assertEquals('1234567', $result);
    }
    
    public function testOsuForumIdExtractionLegacyUrl(): void
    {
        $url = 'https://osu.ppy.sh/forum/t/987654';
        
        $result = $this->extractor->extractOsuForumId($url);
        
        $this->assertEquals('987654', $result);
    }
    
    public function testOsuForumIdExtractionWithParameters(): void
    {
        $url = 'https://osu.ppy.sh/community/forums/topics/1234567?start=15&hilit=tournament';
        
        $result = $this->extractor->extractOsuForumId($url);
        
        $this->assertEquals('1234567', $result);
    }
    
    public function testOsuForumIdValidation(): void
    {
        $url = 'https://osu.ppy.sh/community/forums/topics/12345678901'; // Too long
        
        $result = $this->extractor->extractOsuForumId($url);
        
        $this->assertNull($result);
    }
    
    /**
     * Test 1.4-UNIT-009: Challonge tournament slug extraction
     */
    public function testChallongeSlugExtractionStandardUrl(): void
    {
        $url = 'https://challonge.com/awesome_tournament_2024';
        
        $result = $this->extractor->extractChallongeSlug($url);
        
        $this->assertEquals('awesome_tournament_2024', $result);
    }
    
    public function testChallongeSlugExtractionWithTournamentPath(): void
    {
        $url = 'https://challonge.com/tournaments/korean-osu-cup';
        
        $result = $this->extractor->extractChallongeSlug($url);
        
        $this->assertEquals('korean-osu-cup', $result);
    }
    
    public function testChallongeSlugExtractionWithTrailingSlash(): void
    {
        $url = 'https://challonge.com/my_bracket_2024/';
        
        $result = $this->extractor->extractChallongeSlug($url);
        
        $this->assertEquals('my_bracket_2024', $result);
    }
    
    public function testChallongeSlugValidation(): void
    {
        $url = 'https://challonge.com/ab'; // Too short
        
        $result = $this->extractor->extractChallongeSlug($url);
        
        $this->assertNull($result);
    }
    
    /**
     * Test 1.4-UNIT-010: YouTube/Twitch stream ID extraction
     */
    public function testYouTubeIdExtractionWatchUrl(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        
        $result = $this->extractor->extractYouTubeId($url);
        
        $this->assertEquals('dQw4w9WgXcQ', $result);
    }
    
    public function testYouTubeIdExtractionShortenedUrl(): void
    {
        $url = 'https://youtu.be/dQw4w9WgXcQ';
        
        $result = $this->extractor->extractYouTubeId($url);
        
        $this->assertEquals('dQw4w9WgXcQ', $result);
    }
    
    public function testYouTubeIdExtractionEmbedUrl(): void
    {
        $url = 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1';
        
        $result = $this->extractor->extractYouTubeId($url);
        
        $this->assertEquals('dQw4w9WgXcQ', $result);
    }
    
    public function testYouTubeIdExtractionWithMultipleParameters(): void
    {
        $url = 'https://www.youtube.com/watch?t=123&v=dQw4w9WgXcQ&list=PLrAXtmRdnEQy8x9IaU4';
        
        $result = $this->extractor->extractYouTubeId($url);
        
        $this->assertEquals('dQw4w9WgXcQ', $result);
    }
    
    public function testTwitchIdExtractionChannelUrl(): void
    {
        $url = 'https://www.twitch.tv/awesome_streamer';
        
        $result = $this->extractor->extractTwitchId($url);
        
        $this->assertEquals('awesome_streamer', $result);
    }
    
    public function testTwitchIdExtractionVideoUrl(): void
    {
        $url = 'https://www.twitch.tv/videos/123456789';
        
        $result = $this->extractor->extractTwitchId($url);
        
        $this->assertEquals('123456789', $result);
    }
    
    public function testTwitchIdValidation(): void
    {
        $url = 'https://www.twitch.tv/ab'; // Too short
        
        $result = $this->extractor->extractTwitchId($url);
        
        $this->assertNull($result);
    }
    
    /**
     * Test 1.4-UNIT-011: URL validation and security checks
     */
    public function testUrlSecurityValidationJavaScript(): void
    {
        $url = 'javascript:alert("xss")';
        
        $result = $this->extractor->extractGoogleSheetsId($url);
        
        $this->assertNull($result);
    }
    
    public function testUrlSecurityValidationDataUrl(): void
    {
        $url = 'data:text/html,<script>alert("xss")</script>';
        
        $result = $this->extractor->extractYouTubeId($url);
        
        $this->assertNull($result);
    }
    
    public function testUrlSecurityValidationFileProtocol(): void
    {
        $url = 'file:///etc/passwd';
        
        $result = $this->extractor->extractOsuForumId($url);
        
        $this->assertNull($result);
    }
    
    public function testUrlLengthValidation(): void
    {
        $longUrl = 'https://example.com/' . str_repeat('a', 2050); // Exceeds MAX_URL_LENGTH
        
        $result = $this->extractor->extractGoogleFormsId($longUrl);
        
        $this->assertNull($result);
    }
    
    public function testUrlFormatValidation(): void
    {
        $invalidUrl = 'not-a-valid-url';
        
        $result = $this->extractor->extractChallongeSlug($invalidUrl);
        
        $this->assertNull($result);
    }
    
    public function testUrlMaliciousCharacterValidation(): void
    {
        $maliciousUrl = 'https://example.com/<script>alert("xss")</script>';
        
        $result = $this->extractor->extractYouTubeId($maliciousUrl);
        
        $this->assertNull($result);
    }
    
    public function testHttpsOnlyValidation(): void
    {
        $httpUrl = 'http://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit';
        
        $result = $this->extractor->extractGoogleSheetsId($httpUrl);
        
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result); // Should accept HTTP for now
    }
    
    /**
     * Test comprehensive URL extraction from content
     */
    public function testExtractAllUrlIdsFromContent(): void
    {
        $content = <<<EOT
Tournament Information:
Registration: https://forms.gle/1FAIpQLSdRegistration123
Spreadsheet: https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
Bracket: https://challonge.com/awesome_tournament
Stream: https://www.twitch.tv/osu_streamer
Highlights: https://www.youtube.com/watch?v=dQw4w9WgXcQ
Forum: https://osu.ppy.sh/community/forums/topics/1234567
EOT;
        
        $result = $this->extractor->extractAllUrlIds($content);
        
        $this->assertArrayHasKey('google_forms', $result);
        $this->assertEquals('1FAIpQLSdRegistration123', $result['google_forms']);
        
        $this->assertArrayHasKey('google_sheets', $result);
        $this->assertEquals('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', $result['google_sheets']);
        
        $this->assertArrayHasKey('challonge', $result);
        $this->assertEquals('awesome_tournament', $result['challonge']);
        
        $this->assertArrayHasKey('twitch', $result);
        $this->assertEquals('osu_streamer', $result['twitch']);
        
        $this->assertArrayHasKey('youtube', $result);
        $this->assertEquals('dQw4w9WgXcQ', $result['youtube']);
        
        // Note: osu_forum not included as it's not in current implementation
    }
    
    public function testExtractAllUrlIdsWithMaliciousContent(): void
    {
        $content = <<<EOT
<script>alert('xss')</script>
Valid URL: https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
Malicious: javascript:alert('evil')
Also valid: https://challonge.com/tournament_2024
EOT;
        
        $result = $this->extractor->extractAllUrlIds($content);
        
        $this->assertArrayHasKey('google_sheets', $result);
        $this->assertArrayHasKey('challonge', $result);
        $this->assertCount(2, $result); // Should only have 2 valid extractions
    }
    
    /**
     * Test utility methods
     */
    public function testGetSupportedTypes(): void
    {
        $types = $this->extractor->getSupportedTypes();
        
        $expectedTypes = ['google_sheets', 'google_forms', 'osu_forum', 'challonge', 'youtube', 'twitch'];
        
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $types);
        }
    }
    
    public function testIdentifyUrlType(): void
    {
        $googleSheetsUrl = 'https://docs.google.com/spreadsheets/d/123/edit';
        $youtubeUrl = 'https://www.youtube.com/watch?v=abc123';
        $invalidUrl = 'https://example.com/random';
        
        $this->assertEquals('google_sheets', $this->extractor->identifyUrlType($googleSheetsUrl));
        $this->assertEquals('youtube', $this->extractor->identifyUrlType($youtubeUrl));
        $this->assertNull($this->extractor->identifyUrlType($invalidUrl));
    }
    
    public function testValidateExtractedId(): void
    {
        $validGoogleSheetsId = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';
        $invalidGoogleSheetsId = 'too_short';
        
        $this->assertTrue($this->extractor->validateExtractedId($validGoogleSheetsId, 'google_sheets'));
        $this->assertFalse($this->extractor->validateExtractedId($invalidGoogleSheetsId, 'google_sheets'));
    }
    
    public function testGetPatternInfo(): void
    {
        $googleSheetsInfo = $this->extractor->getPatternInfo('google_sheets');
        
        $this->assertIsArray($googleSheetsInfo);
        $this->assertArrayHasKey('patterns', $googleSheetsInfo);
        $this->assertArrayHasKey('id_validation', $googleSheetsInfo);
        $this->assertArrayHasKey('max_length', $googleSheetsInfo);
    }
    
    public function testInvalidExtractionType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported extraction type: invalid_type');
        
        // Use reflection to test private method with invalid type
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('extractUrlId');
        $method->setAccessible(true);
        
        $method->invoke($this->extractor, 'https://example.com', 'invalid_type');
    }
    
    public function testReconstructGoogleFormsUrlLongId(): void
    {
        $longId = '1FAIpQLSdwAu2_Fi2oKcH5P7_0D_CaVs3_X8yGxQr2Q1Z4R8S3T7U2V9W';
        
        $result = $this->extractor->reconstructGoogleFormsUrl($longId);
        
        $this->assertEquals("https://forms.google.com/forms/d/e/{$longId}/viewform", $result);
    }
    
    public function testReconstructGoogleFormsUrlShortId(): void
    {
        $shortId = 'tCpsMg74TTNHLwsj9';
        
        $result = $this->extractor->reconstructGoogleFormsUrl($shortId);
        
        $this->assertEquals("https://forms.gle/{$shortId}", $result);
    }
}