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
use TourneyMethod\Config\OsuApi;
use PDO;

/**
 * Basic Tournament Parser
 */
class BasicTournamentParser
{
    private PDO $db;
    private OsuForumService $forumService;
    private Tournament $tournamentModel;
    private int $processedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    
    public function __construct()
    {
        $this->initializeDatabase();
        $this->forumService = new OsuForumService($this->db);
        $this->tournamentModel = new Tournament($this->db);
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
                return;
            }
            
            $this->logInfo("Fetched " . count($topicsData['topics']) . " topics from forum API");
            
            // Process each topic
            foreach ($topicsData['topics'] as $topic) {
                $this->processTopic($topic);
            }
            
            $this->logInfo("Parser completed. Processed: {$this->processedCount}, Skipped: {$this->skippedCount}, Errors: {$this->errorCount}");
            
        } catch (Exception $e) {
            $this->logError("Parser failed: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            exit(1);
        } finally {
            $this->cleanup();
        }
    }
    
    /**
     * Process individual forum topic
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
            
            // Check for duplicates
            $existingTournament = $this->tournamentModel->findByTopicId($topicId);
            
            if ($existingTournament) {
                $this->logDebug("Topic already exists, skipping", [
                    'topic_id' => $topicId,
                    'existing_id' => $existingTournament['id'],
                    'title' => $title
                ]);
                $this->skippedCount++;
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
        $content = [];
        
        // Prioritize raw content for better parsing
        if (isset($topic['first_post']['body']['raw']) && !empty(trim($topic['first_post']['body']['raw']))) {
            // Use full raw content for data extraction
            return trim($topic['first_post']['body']['raw']);
        }
        
        // Fallback to HTML content with tag stripping
        if (isset($topic['first_post']['body']['html']) && !empty(trim($topic['first_post']['body']['html']))) {
            $htmlContent = $topic['first_post']['body']['html'];
            // Convert HTML to more parseable format
            $htmlContent = str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlContent);
            $htmlContent = str_replace(['<p>', '</p>'], ["\n", "\n"], $htmlContent);
            $htmlContent = strip_tags($htmlContent);
            return trim($htmlContent);
        }
        
        // Final fallback - use topic title as minimal content
        if (isset($topic['title'])) {
            return "Tournament Title: " . $topic['title'] . "\n\n[No additional content available]";
        }
        
        return "No content extracted - Topic ID: " . ($topic['id'] ?? 'unknown');
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        try {
            EnvLoader::load();
            
            // Database path - use persistent storage path for App Platform compatibility
            $dbPath = $_ENV['DB_PATH'] ?? __DIR__ . '/../../data/tournament_method.db';
            
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
            $this->db = null;
            
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
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        // Console output
        echo "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        
        // Also log to system logs table if database is available
        if (isset($this->db)) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO system_logs (level, message, context, source) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $level,
                    $message,
                    empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                    'BasicTournamentParser'
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