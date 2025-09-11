#!/usr/bin/env php
<?php
/**
 * Basic Tournament Parser Script
 * 
 * Fetches latest topics from osu! Standard tournament forum API
 * and saves new, unique topics to the tournaments table for review.
 * 
 * Usage: php scripts/parser/basic_parser.php
 */

declare(strict_types=1);

// Set timezone for Korean market
date_default_timezone_set('Asia/Seoul');

// Bootstrap autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\EnvLoader;
use TourneyMethod\Services\OsuForumService;
use TourneyMethod\Models\Tournament;
use TourneyMethod\Models\ParserStatus;
use TourneyMethod\Config\OsuApi;
use PDO;

/**
 * Basic Tournament Parser
 */
class BasicTournamentParser
{
    private ?PDO $db;
    private OsuForumService $forumService;
    private Tournament $tournamentModel;
    private ParserStatus $parserStatus;
    private int $processedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    
    public function __construct()
    {
        $this->initializeDatabase();
        
        if (!$this->db) {
            throw new \Exception("Failed to initialize database connection");
        }
        
        $this->forumService = new OsuForumService($this->db);
        $this->tournamentModel = new Tournament($this->db);
        $this->parserStatus = new ParserStatus($this->db);
    }
    
    /**
     * Run the parser
     */
    public function run(): void
    {
        $this->logInfo("Basic Tournament Parser starting...");
        
        try {
            // Validate API configuration
            if (!OsuApi::validateConfig()) {
                throw new Exception('osu! API configuration is invalid.');
            }
            
            $this->logInfo("API configuration validated successfully");
            
            // Fetch topics from forum API
            $topicsData = $this->forumService->getTopicsWithRetry();
            
            if (!isset($topicsData['topics']) || empty($topicsData['topics'])) {
                $this->logWarning("No topics found in API response");
                // Still consider this a successful run, just with no data
                $this->parserStatus->recordRunSuccess([
                    'processed' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ]);
                return;
            }
            
            $this->logInfo("Fetched " . count($topicsData['topics']) . " topics from forum API");
            
            // Process each topic
            foreach ($topicsData['topics'] as $topic) {
                $this->processTopic($topic);
            }
            
            $this->logInfo("Parser completed. Processed: {$this->processedCount}, Skipped: {$this->skippedCount}, Errors: {$this->errorCount}");
            
            // Record successful completion
            $this->parserStatus->recordRunSuccess([
                'processed' => $this->processedCount,
                'skipped' => $this->skippedCount,
                'errors' => $this->errorCount
            ]);
            
        } catch (Exception $e) {
            $this->logError("Parser failed: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Record failed run
            $this->parserStatus->recordRunFailure($e->getMessage());
            
            exit(1);
        } finally {
            $this->cleanup();
        }
    }
    
    /**
     * Process individual forum topic with overwrite logic
     */
    private function processTopic(array $topic): void
    {
        try {
            // Validate topic structure
            if (!isset($topic['id']) || !isset($topic['title'])) {
                $this->logWarning("Invalid topic structure - missing ID or title", ['topic' => $topic]);
                $this->errorCount++;
                return;
            }
            
            $topicId = (int)$topic['id'];
            $title = trim($topic['title']);
            
            if ($topicId <= 0 || empty($title)) {
                $this->logWarning("Invalid topic data", [
                    'topic_id' => $topicId,
                    'title_empty' => empty($title)
                ]);
                $this->errorCount++;
                return;
            }
            
            // Check for duplicates and determine if we should overwrite
            $existingTournament = $this->tournamentModel->findByTopicId($topicId);
            
            if ($existingTournament) {
                // Check if we should overwrite with potentially better parsed data
                if ($this->shouldOverwriteTournament($existingTournament, $topic)) {
                    $this->logInfo("Overwriting existing tournament with better parsed data", [
                        'topic_id' => $topicId,
                        'existing_id' => $existingTournament['id'],
                        'title' => $title
                    ]);
                    
                    // Update existing tournament with new data
                    $this->updateTournamentFromTopic($existingTournament, $topic);
                    $this->processedCount++;
                } else {
                    $this->logDebug("Topic already exists, skipping (no improvement)", [
                        'topic_id' => $topicId,
                        'existing_id' => $existingTournament['id'],
                        'title' => $title
                    ]);
                    $this->skippedCount++;
                }
                return;
            }
            
            // Process new tournament
            $this->createTournamentFromTopic($topic);
            $this->processedCount++;
            
            // Rate limiting delay (handled by OsuForumService, but add extra safety)
            usleep(100000); // 100ms additional delay
            
        } catch (Exception $e) {
            $this->logError("Failed to process topic", [
                'topic_id' => $topic['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            $this->errorCount++;
        }
    }

    
    /**
     * Determine if existing tournament should be overwritten with new data
     * 
     * @param array $existingTournament Current tournament data from database
     * @param array $topic New topic data from API
     * @return bool True if should overwrite, false if should skip
     */
    private function shouldOverwriteTournament(array $existingTournament, array $topic): bool
    {
        // Always overwrite if we have no raw content or very minimal content
        $currentContent = $existingTournament['raw_post_content'] ?? '';
        
        if (empty($currentContent) || 
            str_starts_with($currentContent, 'No content extracted') ||
            str_starts_with($currentContent, 'Tournament Title:') ||
            strlen($currentContent) < 100) {
            
            $this->logDebug("Will overwrite - existing has minimal content", [
                'topic_id' => $topic['id'],
                'current_content_length' => strlen($currentContent),
                'current_content_preview' => substr($currentContent, 0, 50)
            ]);
            return true;
        }
        
        // Extract new content to compare
        $newContent = $this->extractTopicContent($topic);
        
        // Overwrite if new content is significantly longer and more detailed
        if (strlen($newContent) > strlen($currentContent) * 1.5 && 
            !str_starts_with($newContent, 'No content extracted') &&
            !str_starts_with($newContent, 'Tournament Title:')) {
            
            $this->logDebug("Will overwrite - new content is significantly better", [
                'topic_id' => $topic['id'],
                'current_length' => strlen($currentContent),
                'new_length' => strlen($newContent),
                'improvement_ratio' => round(strlen($newContent) / strlen($currentContent), 2)
            ]);
            return true;
        }
        
        // For now, also overwrite if it's been more than 1 day since last parse
        // (in case parsing algorithms have improved)
        $parsedAt = $existingTournament['parsed_at'] ?? '';
        if (!empty($parsedAt)) {
            $lastParsed = new DateTime($parsedAt);
            $daysSinceLastParse = (new DateTime())->diff($lastParsed)->days;
            
            if ($daysSinceLastParse >= 1) {
                $this->logDebug("Will overwrite - data is old, may have improved parsing", [
                    'topic_id' => $topic['id'],
                    'days_since_parse' => $daysSinceLastParse,
                    'last_parsed' => $parsedAt
                ]);
                return true;
            }
        }
        
        // Don't overwrite by default
        return false;
    }
    
    /**
     * Update existing tournament with new data from topic
     * 
     * @param array $existingTournament Current tournament data
     * @param array $topic New topic data from API
     */
    private function updateTournamentFromTopic(array $existingTournament, array $topic): void
    {
        try {
            $topicId = (int)$topic['id'];
            
            // Prepare forum post data structure for Tournament model
            $forumPostData = [
                'id' => $topic['id'],
                'title' => $topic['title'],
                'content' => $this->extractTopicContent($topic)
            ];
            
            // Update using the Tournament model's update method
            $this->tournamentModel->updateFromRawData($existingTournament['id'], $forumPostData);
            
            $this->logInfo("Tournament updated with new data", [
                'tournament_id' => $existingTournament['id'],
                'topic_id' => $topicId,
                'title' => $topic['title'],
                'update_method' => 'updateFromRawData'
            ]);
            
        } catch (Exception $e) {
            // Log detailed error for update failures
            $this->logError("Failed to update tournament from topic", [
                'tournament_id' => $existingTournament['id'] ?? 'unknown',
                'topic_id' => $topic['id'] ?? 'unknown',
                'title' => $topic['title'] ?? 'unknown',
                'error' => $e->getMessage(),
                'update_failed' => true
            ]);
            throw new Exception("Failed to update tournament from topic {$topic['id']}: " . $e->getMessage());
        }
    }
    
    /**
     * Create tournament from forum topic with data extraction (Story 1.4)
     */
    private function createTournamentFromTopic(array $topic): void
    {
        try {
            // Prepare forum post data structure for Tournament model
            $forumPostData = [
                'id' => $topic['id'],
                'title' => $topic['title'],
                'content' => $this->extractTopicContent($topic)
            ];
            
            // Use the new extraction method from Story 1.4
            $tournamentId = $this->tournamentModel->extractAndSaveFromRawData($forumPostData);
            
            $this->logInfo("Tournament created with data extraction", [
                'tournament_id' => $tournamentId,
                'topic_id' => $topic['id'],
                'title' => $topic['title'],
                'extraction_method' => 'extractAndSaveFromRawData'
            ]);
            
        } catch (Exception $e) {
            // Log detailed error for data extraction failures
            $this->logError("Failed to extract and save tournament from topic", [
                'topic_id' => $topic['id'] ?? 'unknown',
                'title' => $topic['title'] ?? 'unknown',
                'error' => $e->getMessage(),
                'extraction_failed' => true
            ]);
            throw new Exception("Failed to create tournament from topic {$topic['id']}: " . $e->getMessage());
        }
    }
    
    /**
     * Extract content from topic data (Enhanced for Story 1.4)
     */
    private function extractTopicContent(array $topic): string
    {
        // Debug: Log the topic structure to understand what we're getting
        $this->logDebug("Topic structure analysis", [
            'topic_id' => $topic['id'] ?? 'unknown',
            'available_keys' => array_keys($topic),
            'has_first_post' => isset($topic['first_post']),
            'has_posts' => isset($topic['posts']),
            'has_body' => isset($topic['body'])
        ]);
        
        // If first_post exists, log its structure
        if (isset($topic['first_post'])) {
            $this->logDebug("First post structure", [
                'topic_id' => $topic['id'],
                'first_post_keys' => array_keys($topic['first_post']),
                'has_body' => isset($topic['first_post']['body']),
                'body_keys' => isset($topic['first_post']['body']) ? array_keys($topic['first_post']['body']) : 'no body'
            ]);
        }
        
        // The osu! API returns topics with first_post structure
        // Let's handle multiple possible content sources
        
        // Method 1: Try raw content from first post
        if (isset($topic['first_post']['body']['raw']) && !empty(trim($topic['first_post']['body']['raw']))) {
            $content = trim($topic['first_post']['body']['raw']);
            $this->logDebug("Using raw content from first_post", [
                'topic_id' => $topic['id'],
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100)
            ]);
            return $content;
        }
        
        // Method 2: Try HTML content from first post and convert to text
        if (isset($topic['first_post']['body']['html']) && !empty(trim($topic['first_post']['body']['html']))) {
            $htmlContent = $topic['first_post']['body']['html'];
            
            // Convert HTML to more parseable format
            $htmlContent = str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlContent);
            $htmlContent = str_replace(['<p>', '</p>'], ["\n", "\n"], $htmlContent);
            $htmlContent = str_replace(['<div>', '</div>'], ["\n", "\n"], $htmlContent);
            
            // Convert common BBCode patterns that might be in HTML
            $htmlContent = preg_replace('/\[b\](.*?)\[\/b\]/i', '**$1**', $htmlContent);
            $htmlContent = preg_replace('/\[i\](.*?)\[\/i\]/i', '*$1*', $htmlContent);
            $htmlContent = preg_replace('/\[u\](.*?)\[\/u\]/i', '_$1_', $htmlContent);
            
            // Strip remaining HTML tags
            $content = strip_tags($htmlContent);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Clean up whitespace
            $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content); // Multiple newlines to double
            $content = trim($content);
            
            if (!empty($content)) {
                $this->logDebug("Using HTML content from first_post (converted)", [
                    'topic_id' => $topic['id'],
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 100)
                ]);
                return $content;
            }
        }
        
        // Method 3: Try direct body field if it exists
        if (isset($topic['body']) && !empty(trim($topic['body']))) {
            $content = trim($topic['body']);
            $this->logDebug("Using direct body field", [
                'topic_id' => $topic['id'],
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100)
            ]);
            return $content;
        }
        
        // Method 4: Check if there's a posts array with content
        if (isset($topic['posts']) && is_array($topic['posts']) && !empty($topic['posts'])) {
            $firstPost = $topic['posts'][0];
            if (isset($firstPost['body']['raw']) && !empty(trim($firstPost['body']['raw']))) {
                $content = trim($firstPost['body']['raw']);
                $this->logDebug("Using raw content from posts array", [
                    'topic_id' => $topic['id'],
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 100)
                ]);
                return $content;
            }
            if (isset($firstPost['body']['html']) && !empty(trim($firstPost['body']['html']))) {
                $content = strip_tags($firstPost['body']['html']);
                $this->logDebug("Using HTML content from posts array", [
                    'topic_id' => $topic['id'],
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 100)
                ]);
                return $content;
            }
        }
        
        // Method 5: Try fetching full content from individual topic endpoint
        if (isset($topic['id'])) {
            $fullContent = $this->fetchFullTopicContent($topic['id']);
            if ($fullContent !== null && !empty(trim($fullContent))) {
                $this->logDebug("Using content from individual topic API call", [
                    'topic_id' => $topic['id'],
                    'content_length' => strlen($fullContent),
                    'content_preview' => substr($fullContent, 0, 100)
                ]);
                return $fullContent;
            }
        }
        
        // Method 6: Fallback - use topic title as minimal content with warning
        if (isset($topic['title']) && !empty($topic['title'])) {
            $this->logWarning("No post content found, using title only", [
                'topic_id' => $topic['id'],
                'title' => $topic['title']
            ]);
            return "Tournament Title: " . $topic['title'] . "\n\n[No post content available - parsing may be limited]";
        }
        
        // Last resort
        $this->logError("No content extracted from topic", [
            'topic_id' => $topic['id'] ?? 'unknown',
            'available_keys' => array_keys($topic)
        ]);
        
        return "No content extracted - Topic ID: " . ($topic['id'] ?? 'unknown');
    }

    
    /**
     * Fetch full topic content from osu! API using individual topic endpoint
     * 
     * @param int $topicId Topic ID to fetch
     * @return string|null Full topic content or null if failed
     */
    private function fetchFullTopicContent(int $topicId): ?string
    {
        try {
            // Use the correct API endpoint: /forums/topics/{topic} to get posts with body
            $topicEndpoint = "https://osu.ppy.sh/api/v2/forums/topics/{$topicId}";
            
            // Get client credentials access token using the forum service method
            $tokenData = [
                'client_id' => $_ENV['OSU_CLIENT_ID'],
                'client_secret' => $_ENV['OSU_CLIENT_SECRET'],
                'grant_type' => 'client_credentials',
                'scope' => 'public'
            ];
            
            // Get access token
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://osu.ppy.sh/oauth/token',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($tokenData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'TourneyMethod/1.0'
            ]);
            
            $tokenResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$tokenResponse) {
                $this->logWarning("Failed to get access token for topic content fetch", [
                    'topic_id' => $topicId,
                    'http_code' => $httpCode
                ]);
                return null;
            }
            
            $tokenData = json_decode($tokenResponse, true);
            if (!isset($tokenData['access_token'])) {
                return null;
            }
            
            // Make request to get topic and posts
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$tokenData['access_token']}\r\n" .
                               "Accept: application/json\r\n" .
                               "User-Agent: TourneyMethod/1.0",
                    'timeout' => 30
                ]
            ]);
            
            $response = @file_get_contents($topicEndpoint, false, $context);
            
            if ($response === false) {
                $this->logWarning("Failed to fetch topic content from API", [
                    'topic_id' => $topicId,
                    'endpoint' => $topicEndpoint
                ]);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['posts']) || empty($data['posts'])) {
                $this->logWarning("Invalid or empty topic response", [
                    'topic_id' => $topicId,
                    'json_error' => json_last_error_msg(),
                    'has_posts' => isset($data['posts'])
                ]);
                return null;
            }
            
            // Get the first post content (the tournament announcement)
            $firstPost = $data['posts'][0] ?? null;
            if (!$firstPost || !isset($firstPost['body'])) {
                $this->logWarning("First post has no body content", [
                    'topic_id' => $topicId,
                    'post_structure' => array_keys($firstPost ?? [])
                ]);
                return null;
            }
            
            // The body should contain the raw BBCode content
            $content = '';
            if (isset($firstPost['body']['raw'])) {
                $content = trim($firstPost['body']['raw']);
            } elseif (isset($firstPost['body']['html'])) {
                $content = strip_tags(trim($firstPost['body']['html']));
            } elseif (is_string($firstPost['body'])) {
                $content = trim($firstPost['body']);
            }
            
            if (!empty($content)) {
                $this->logInfo("Successfully fetched topic content", [
                    'topic_id' => $topicId,
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 100)
                ]);
                return $content;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logError("Exception while fetching full topic content", [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        try {
            EnvLoader::load();
            
            // Database path - resolve relative paths from project root, not current working directory
            $envDbPath = $_ENV['DB_PATH'] ?? './data/tournament_method.db';
            
            if (str_starts_with($envDbPath, './') || str_starts_with($envDbPath, '../')) {
                // Relative path: resolve from project root (2 levels up from this script)
                $projectRoot = dirname(__DIR__, 2);
                $dbPath = $projectRoot . '/' . ltrim($envDbPath, './');
            } else {
                // Absolute path: use as-is
                $dbPath = $envDbPath;
            }
            
            // Create directory if it doesn't exist
            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->db = new PDO("sqlite:{$dbPath}");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Set SQLite optimizations
            $this->db->exec("PRAGMA foreign_keys = ON");
            $this->db->exec("PRAGMA journal_mode = WAL");
            $this->db->exec("PRAGMA synchronous = NORMAL");
            $this->db->exec("PRAGMA cache_size = 10000");
            
            $this->logDebug("Database connection established", ['db_path' => $dbPath]);
            
        } catch (Exception $e) {
            throw new Exception("Failed to initialize database: " . $e->getMessage());
        }
    }
    
    /**
     * Cleanup resources
     */
    private function cleanup(): void
    {
        try {
            // Close database connection
            if ($this->db) {
                $this->db = null;
            }
            
            $this->logDebug("Parser cleanup completed");
            
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Log info message
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    private function logError(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Log debug message
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Write log entry
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Use KST timezone for all timestamps
        $kstTime = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        $timestamp = $kstTime->format('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        // Console output
        echo "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        
        // Also log to system logs table if database is available
        if (isset($this->db)) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO system_logs (level, message, context, source, created_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $level,
                    $message,
                    empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                    'BasicTournamentParser',
                    $timestamp
                ]);
                
            } catch (Exception $e) {
                // Don't fail parser if logging fails
                error_log("Failed to log to database: " . $e->getMessage());
            }
        }
    }
}

// Run the parser if called directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    try {
        $parser = new BasicTournamentParser();
        $parser->run();
        exit(0);
        
    } catch (Exception $e) {
        error_log("Fatal parser error: " . $e->getMessage());
        exit(1);
    }
}