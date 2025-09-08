<?php

namespace TourneyMethod\Models;

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Services\ForumPostParserService;
use TourneyMethod\Utils\UrlExtractor;
use PDO;

/**
 * Tournament model for managing tournament data
 * 
 * Handles tournament CRUD operations with parser-specific methods
 * for forum post processing and duplicate checking.
 */
class Tournament
{
    private PDO $db;
    private ForumPostParserService $parserService;
    private UrlExtractor $urlExtractor;
    
    public const STATUS_PENDING = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->parserService = new ForumPostParserService();
        $this->urlExtractor = new UrlExtractor();
    }
    
    /**
     * Find tournament by osu! topic ID for duplicate checking
     * 
     * @param int $topicId osu! forum topic ID
     * @return array|null Tournament data or null if not found
     */
    public function findByTopicId(int $topicId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, osu_topic_id, title, status, parsed_at 
            FROM tournaments 
            WHERE osu_topic_id = ?
        ");
        
        $stmt->execute([$topicId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $tournament ?: null;
    }
    
    /**
     * Extract and save tournament data from raw forum post (Story 1.4)
     * 
     * Performs complete data extraction workflow using ForumPostParserService
     * and UrlExtractor, then saves structured data to database.
     * 
     * @param array $forumPostData Forum post data from osu! API
     * @return int Tournament ID
     * @throws \Exception On validation, parsing, or database errors
     */
    public function extractAndSaveFromRawData(array $forumPostData): int
    {
        // Validate forum post structure
        $this->validateForumPostData($forumPostData);
        
        $topicId = filter_var($forumPostData['id'], FILTER_VALIDATE_INT);
        $rawTitle = $this->sanitizeTitle($forumPostData['title']);
        $rawContent = $this->sanitizeRawContent($forumPostData['content'] ?? '');
        
        if (!$topicId || !$rawTitle) {
            throw new \InvalidArgumentException('Invalid forum post data: missing or invalid topic ID or title');
        }
        
        // Check for duplicate before processing
        if ($this->findByTopicId($topicId)) {
            throw new \Exception("Tournament with topic ID {$topicId} already exists");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Parse forum post content to extract structured data
            $parsedData = $this->parserService->parseForumPost($rawContent, $rawTitle);
            
            // Extract URL IDs from content
            $urlIds = $this->urlExtractor->extractAllUrlIds($rawContent);
            
            // Validate and prepare extracted data for database
            $tournamentData = $this->prepareExtractedData($parsedData, $urlIds, $topicId, $rawTitle, $rawContent);
            
            // Insert tournament with extracted data
            $tournamentId = $this->insertExtractedTournament($tournamentData);
            
            // Log extraction results with confidence scores
            $this->logTournamentExtraction($tournamentId, $topicId, $parsedData, $urlIds);
            
            $this->db->commit();
            
            return $tournamentId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception('Failed to extract and save tournament data: ' . $e->getMessage());
        }
    }
    
    /**
     * Create tournament from forum post data
     * 
     * @param array $forumPostData Forum post data from osu! API
     * @return int Tournament ID
     * @throws \Exception On validation or database errors
     */
    public function createFromForumPost(array $forumPostData): int
    {
        // Validate required forum post structure
        $this->validateForumPostData($forumPostData);
        
        $topicId = filter_var($forumPostData['id'], FILTER_VALIDATE_INT);
        $title = $this->sanitizeTitle($forumPostData['title']);
        $rawContent = $this->sanitizeRawContent($forumPostData['content'] ?? '');
        
        if (!$topicId || !$title) {
            throw new \InvalidArgumentException('Invalid forum post data: missing or invalid topic ID or title');
        }
        
        // Check for duplicate before creating
        if ($this->findByTopicId($topicId)) {
            throw new \Exception("Tournament with topic ID {$topicId} already exists");
        }
        
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO tournaments (
                    osu_topic_id, 
                    title, 
                    status, 
                    raw_post_content,
                    forum_url_slug,
                    parsed_at
                ) VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))
            ");
            
            $forumSlug = $this->generateForumSlug($topicId);
            
            $stmt->execute([
                $topicId,
                $title,
                self::STATUS_PENDING,
                $rawContent,
                $forumSlug
            ]);
            
            $tournamentId = $this->db->lastInsertId();
            
            // Log the creation
            $this->logTournamentCreation($tournamentId, $topicId, $title);
            
            $this->db->commit();
            
            return (int)$tournamentId;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception('Failed to create tournament: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all tournaments with pagination
     */
    public function getAllTournaments(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        $whereClause = '';
        $params = [];
        
        if ($status) {
            $whereClause = 'WHERE status = ?';
            $params[] = $status;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                id,
                osu_topic_id,
                title,
                status,
                rank_range_min,
                rank_range_max,
                tournament_start,
                registration_close,
                parsed_at
            FROM tournaments 
            {$whereClause}
            ORDER BY parsed_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update tournament status
     */
    public function updateStatus(int $tournamentId, string $status, ?int $approvedBy = null): bool
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_ARCHIVED])) {
            throw new \InvalidArgumentException('Invalid tournament status');
        }
        
        $sql = "UPDATE tournaments SET status = ?";
        $params = [$status];
        
        if ($status === self::STATUS_APPROVED && $approvedBy) {
            $sql .= ", approved_at = datetime('now', 'localtime'), approved_by = ?";
            $params[] = $approvedBy;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $tournamentId;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Validate forum post data structure
     */
    private function validateForumPostData(array $data): void
    {
        $requiredFields = ['id', 'title'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // Validate topic ID is numeric and positive
        if (!is_numeric($data['id']) || (int)$data['id'] <= 0) {
            throw new \InvalidArgumentException('Invalid topic ID: must be positive integer');
        }
        
        // Validate title is not empty after trimming
        if (trim($data['title']) === '') {
            throw new \InvalidArgumentException('Tournament title cannot be empty');
        }
    }
    
    /**
     * Sanitize tournament title for safe storage
     */
    private function sanitizeTitle(string $title): string
    {
        // Trim whitespace
        $title = trim($title);
        
        // Remove excessive whitespace
        $title = preg_replace('/\s+/', ' ', $title);
        
        // Limit length (database constraint is 500 chars)
        if (strlen($title) > 500) {
            $title = substr($title, 0, 497) . '...';
        }
        
        return $title;
    }
    
    /**
     * Sanitize raw forum content for storage
     */
    private function sanitizeRawContent(string $content): string
    {
        // Trim whitespace
        $content = trim($content);
        
        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // Remove excessive newlines (more than 3 consecutive)
        $content = preg_replace('/\n{4,}/', "\n\n\n", $content);
        
        // Limit content length to prevent database issues (reasonable limit)
        if (strlen($content) > 50000) {
            $content = substr($content, 0, 49997) . '...';
        }
        
        return $content;
    }
    
    /**
     * Generate forum URL slug from topic ID
     */
    private function generateForumSlug(int $topicId): string
    {
        return "forums/topics/{$topicId}";
    }
    
    /**
     * Log tournament creation to system logs
     */
    private function logTournamentCreation(int $tournamentId, int $topicId, string $title): void
    {
        $logStmt = $this->db->prepare("
            INSERT INTO system_logs (level, message, context, source) 
            VALUES (?, ?, ?, ?)
        ");
        
        $context = json_encode([
            'tournament_id' => $tournamentId,
            'osu_topic_id' => $topicId,
            'title_length' => strlen($title)
        ]);
        
        $logStmt->execute([
            'INFO',
            "New tournament created from forum post: {$title}",
            $context,
            'Tournament::createFromForumPost'
        ]);
    }
    
    /**
     * Prepare extracted data for database insertion
     * 
     * @param array $parsedData Parsed forum post data
     * @param array $urlIds Extracted URL IDs
     * @param int $topicId osu! topic ID
     * @param string $rawTitle Raw forum title
     * @param string $rawContent Raw forum content
     * @return array Prepared tournament data
     */
    private function prepareExtractedData(array $parsedData, array $urlIds, int $topicId, string $rawTitle, string $rawContent): array
    {
        return [
            'osu_topic_id' => $topicId,
            'title' => $parsedData['title'] ?? $rawTitle,
            'host_name' => $parsedData['host_name'],
            'rank_range' => $parsedData['rank_range'],
            'tournament_dates' => $parsedData['tournament_dates'],
            'has_badge' => $parsedData['has_badge'] ? 1 : 0,
            'banner_url' => $parsedData['banner_url'],
            'google_sheet_id' => $urlIds['google_sheets'] ?? null,
            'google_form_id' => $urlIds['google_forms'] ?? null,
            'challonge_slug' => $urlIds['challonge'] ?? null,
            'youtube_id' => $urlIds['youtube'] ?? null,
            'twitch_username' => $urlIds['twitch'] ?? null,
            'forum_url_slug' => $this->generateForumSlug($topicId),
            'raw_post_content' => $rawContent,
            'status' => self::STATUS_PENDING,
            'extraction_confidence' => json_encode($parsedData['extraction_confidence'] ?? [])
        ];
    }
    
    /**
     * Insert tournament with extracted data
     * 
     * @param array $tournamentData Prepared tournament data
     * @return int Tournament ID
     */
    private function insertExtractedTournament(array $tournamentData): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tournaments (
                osu_topic_id, 
                title, 
                host_name,
                rank_range,
                tournament_dates,
                has_badge,
                banner_url,
                google_sheet_id,
                google_form_id,
                challonge_slug,
                youtube_id,
                twitch_username,
                forum_url_slug,
                raw_post_content,
                status,
                extraction_confidence,
                parsed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))
        ");
        
        $stmt->execute([
            $tournamentData['osu_topic_id'],
            $tournamentData['title'],
            $tournamentData['host_name'],
            $tournamentData['rank_range'],
            $tournamentData['tournament_dates'],
            $tournamentData['has_badge'],
            $tournamentData['banner_url'],
            $tournamentData['google_sheet_id'],
            $tournamentData['google_form_id'],
            $tournamentData['challonge_slug'],
            $tournamentData['youtube_id'],
            $tournamentData['twitch_username'],
            $tournamentData['forum_url_slug'],
            $tournamentData['raw_post_content'],
            $tournamentData['status'],
            $tournamentData['extraction_confidence']
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Log tournament extraction results
     * 
     * @param int $tournamentId Tournament ID
     * @param int $topicId osu! topic ID
     * @param array $parsedData Parsed data with confidence scores
     * @param array $urlIds Extracted URL IDs
     */
    private function logTournamentExtraction(int $tournamentId, int $topicId, array $parsedData, array $urlIds): void
    {
        $logStmt = $this->db->prepare("
            INSERT INTO system_logs (level, message, context, source) 
            VALUES (?, ?, ?, ?)
        ");
        
        // Count successful extractions
        $extractedFields = array_filter($parsedData, fn($value) => $value !== null && $value !== false);
        $extractedUrls = count($urlIds);
        
        $context = json_encode([
            'tournament_id' => $tournamentId,
            'osu_topic_id' => $topicId,
            'fields_extracted' => count($extractedFields),
            'urls_extracted' => $extractedUrls,
            'extraction_confidence' => $parsedData['extraction_confidence'] ?? [],
            'url_types_found' => array_keys($urlIds)
        ]);
        
        $logStmt->execute([
            'INFO',
            "Tournament data extracted and saved with " . count($extractedFields) . " fields, {$extractedUrls} URLs",
            $context,
            'Tournament::extractAndSaveFromRawData'
        ]);
    }
    
    /**
     * Get tournaments count by status
     */
    public function getCountByStatus(?string $status = null): int
    {
        if ($status) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM tournaments WHERE status = ?");
            $stmt->execute([$status]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM tournaments");
            $stmt->execute();
        }
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get tournament by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                osu_topic_id,
                title,
                status,
                rank_range_min,
                rank_range_max,
                team_size,
                max_teams,
                registration_open,
                registration_close,
                tournament_start,
                google_sheet_id,
                forum_url_slug,
                google_form_id,
                challonge_slug,
                youtube_id,
                twitch_username,
                raw_post_content,
                parsed_at,
                approved_at,
                approved_by
            FROM tournaments 
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $tournament ?: null;
    }

    /**
     * Get all tournaments with pending review status
     * Returns tournaments sorted by parsed_at DESC (newest first)
     */
    public function findPendingReview(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                title,
                parsed_at
            FROM tournaments 
            WHERE status = 'pending_review'
            ORDER BY parsed_at DESC
        ");
        
        $stmt->execute();
        $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $tournaments;
    }
}