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
    public const STATUS_CANCELLED = 'cancelled';
    
    /** Maximum title length for database storage */
    private const MAX_TITLE_LENGTH = 500;
    
    /** Maximum content length for database storage */
    private const MAX_CONTENT_LENGTH = 50000;
    
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

    
    public function extractAndSaveFromRawData(array $forumPostData): int
    {
        // Validate forum post structure
        $this->validateForumPostData($forumPostData);
        
        $topicId = filter_var($forumPostData['id'], FILTER_VALIDATE_INT);
        $rawTitle = $this->sanitizeTitle($forumPostData['title']);
        $rawContent = $this->sanitizeRawContent($forumPostData['content'] ?? '');
        $topicUserId = isset($forumPostData['user_id']) ? filter_var($forumPostData['user_id'], FILTER_VALIDATE_INT) : null;
        
        if (!$topicId || !$rawTitle) {
            throw new \InvalidArgumentException('Invalid forum post data: missing or invalid topic ID or title');
        }
        
        // Check for duplicate before processing
        if ($this->findByTopicId($topicId)) {
            throw new \Exception("Tournament with topic ID {$topicId} already exists");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Parse forum post content to extract structured data (pass user ID for host name fallback)
            $parsedData = $this->parserService->parseForumPost($rawContent, $rawTitle, $topicUserId);
            
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

    
    public function updateFromRawData(int $tournamentId, array $forumPostData): bool
    {
        // Validate forum post structure
        $this->validateForumPostData($forumPostData);
        
        $topicId = filter_var($forumPostData['id'], FILTER_VALIDATE_INT);
        $rawTitle = $this->sanitizeTitle($forumPostData['title']);
        $rawContent = $this->sanitizeRawContent($forumPostData['content'] ?? '');
        $topicUserId = isset($forumPostData['user_id']) ? filter_var($forumPostData['user_id'], FILTER_VALIDATE_INT) : null;
        
        if (!$topicId || !$rawTitle) {
            throw new \InvalidArgumentException('Invalid forum post data: missing or invalid topic ID or title');
        }
        
        // Verify tournament exists
        $existingTournament = $this->findById($tournamentId);
        if (!$existingTournament) {
            throw new \Exception("Tournament with ID {$tournamentId} not found");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Parse forum post content to extract structured data (pass user ID for host name fallback)
            $parsedData = $this->parserService->parseForumPost($rawContent, $rawTitle, $topicUserId);
            
            // Extract URL IDs from content
            $urlIds = $this->urlExtractor->extractAllUrlIds($rawContent);
            
            // Validate and prepare extracted data for database
            $tournamentData = $this->prepareExtractedData($parsedData, $urlIds, $topicId, $rawTitle, $rawContent);
            
            // Update tournament with extracted data
            $this->updateExtractedTournament($tournamentId, $tournamentData);
            
            // Log extraction results with confidence scores
            $this->logTournamentExtraction($tournamentId, $topicId, $parsedData, $urlIds, 'update');
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new \Exception('Failed to update tournament with new data: ' . $e->getMessage());
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
        
        // Limit length (database constraint)
        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH - 3) . '...';
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
        
        // Limit content length to prevent database issues
        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            $content = substr($content, 0, self::MAX_CONTENT_LENGTH - 3) . '...';
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
            // New metadata fields from title parsing
            'team_vs' => $parsedData['team_vs'],
            'team_size' => $parsedData['team_size'],
            'rank_range_min' => $parsedData['rank_range_min'],
            'rank_range_max' => $parsedData['rank_range_max'],
            'is_bws' => $parsedData['is_bws'] ? 1 : 0,
            'game_mode' => $parsedData['game_mode'],
            // New metadata fields from post content parsing
            'discord_link' => $parsedData['discord_link'],
            'star_rating_min' => $parsedData['star_rating_min'],
            'star_rating_max' => $parsedData['star_rating_max'],
            'star_rating_qualifier' => $parsedData['star_rating_qualifier'],
            'registration_open_date' => $parsedData['registration_open_date'],
            'registration_close_date' => $parsedData['registration_close_date'],
            'end_date' => $parsedData['end_date'],
            // URL fields
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
                team_vs,
                team_size,
                rank_range_min,
                rank_range_max,
                is_bws,
                game_mode,
                discord_link,
                star_rating_min,
                star_rating_max,
                star_rating_qualifier,
                end_date,
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))
        ");
        
        $stmt->execute([
            $tournamentData['osu_topic_id'],
            $tournamentData['title'],
            $tournamentData['host_name'],
            $tournamentData['rank_range'],
            $tournamentData['tournament_dates'],
            $tournamentData['has_badge'],
            $tournamentData['banner_url'],
            $tournamentData['team_vs'],
            $tournamentData['team_size'],
            $tournamentData['rank_range_min'],
            $tournamentData['rank_range_max'],
            $tournamentData['is_bws'],
            $tournamentData['game_mode'],
            $tournamentData['discord_link'],
            $tournamentData['star_rating_min'],
            $tournamentData['star_rating_max'],
            $tournamentData['star_rating_qualifier'],
            $tournamentData['end_date'],
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

    
    private function updateExtractedTournament(int $tournamentId, array $tournamentData): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tournaments SET
                title = ?,
                host_name = ?,
                rank_range = ?,
                tournament_dates = ?,
                has_badge = ?,
                banner_url = ?,
                team_vs = ?,
                team_size = ?,
                rank_range_min = ?,
                rank_range_max = ?,
                is_bws = ?,
                game_mode = ?,
                discord_link = ?,
                star_rating_min = ?,
                star_rating_max = ?,
                star_rating_qualifier = ?,
                end_date = ?,
                google_sheet_id = ?,
                google_form_id = ?,
                challonge_slug = ?,
                youtube_id = ?,
                twitch_username = ?,
                forum_url_slug = ?,
                raw_post_content = ?,
                extraction_confidence = ?,
                parsed_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $tournamentData['title'],
            $tournamentData['host_name'],
            $tournamentData['rank_range'],
            $tournamentData['tournament_dates'],
            $tournamentData['has_badge'],
            $tournamentData['banner_url'],
            $tournamentData['team_vs'],
            $tournamentData['team_size'],
            $tournamentData['rank_range_min'],
            $tournamentData['rank_range_max'],
            $tournamentData['is_bws'],
            $tournamentData['game_mode'],
            $tournamentData['discord_link'],
            $tournamentData['star_rating_min'],
            $tournamentData['star_rating_max'],
            $tournamentData['star_rating_qualifier'],
            $tournamentData['end_date'],
            $tournamentData['google_sheet_id'],
            $tournamentData['google_form_id'],
            $tournamentData['challonge_slug'],
            $tournamentData['youtube_id'],
            $tournamentData['twitch_username'],
            $tournamentData['forum_url_slug'],
            $tournamentData['raw_post_content'],
            $tournamentData['extraction_confidence'],
            $tournamentId
        ]);
    }
    
    /**
     * Log tournament extraction results
     * 
     * @param int $tournamentId Tournament ID
     * @param int $topicId osu! topic ID
     * @param array $parsedData Parsed data with confidence scores
     * @param array $urlIds Extracted URL IDs
     * @param string $operation Operation type ('insert' or 'update')
     */
    private function logTournamentExtraction(int $tournamentId, int $topicId, array $parsedData, array $urlIds, string $operation = 'insert'): void
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
            'operation' => $operation,
            'fields_extracted' => count($extractedFields),
            'urls_extracted' => $extractedUrls,
            'extraction_confidence' => $parsedData['extraction_confidence'] ?? [],
            'url_types_found' => array_keys($urlIds)
        ]);
        
        $action = $operation === 'update' ? 'updated' : 'extracted and saved';
        $source = $operation === 'update' ? 'Tournament::updateFromRawData' : 'Tournament::extractAndSaveFromRawData';
        
        $logStmt->execute([
            'INFO',
            "Tournament data {$action} with " . count($extractedFields) . " fields, {$extractedUrls} URLs",
            $context,
            $source
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

    /**
     * Update tournament data
     * @param int $id Tournament ID
     * @param array $data Tournament data to update
     * @return bool Success status
     */
    public function update(int $id, array $data): bool {
        try {
            // Build dynamic SQL based on provided data
            $setParts = [];
            $params = ['id' => $id];
            
            $allowedFields = [
                'title', 'rank_range_min', 'rank_range_max', 'team_size', 'max_teams',
                'registration_open', 'registration_close', 'tournament_start',
                'google_sheet_id', 'google_form_id', 'challonge_slug', 
                'youtube_id', 'twitch_username'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $setParts[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            // Handle special cases for form field mapping
            if (isset($data['start_date'])) {
                $setParts[] = "tournament_start = :tournament_start";
                $params['tournament_start'] = $data['start_date'];
            }
            
            if (isset($data['rank_range'])) {
                // Parse rank range like "#1,000 - #10,000"
                if (preg_match('/#?([\d,]+)\s*-\s*#?([\d,]+)/', $data['rank_range'], $matches)) {
                    $setParts[] = "rank_range_min = :rank_range_min";
                    $setParts[] = "rank_range_max = :rank_range_max";
                    $params['rank_range_min'] = (int)str_replace(',', '', $matches[1]);
                    $params['rank_range_max'] = (int)str_replace(',', '', $matches[2]);
                }
            }
            
            if (empty($setParts)) {
                return true; // Nothing to update
            }
            
            $sql = "UPDATE tournaments SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Tournament update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Approve tournament by changing status to approved
     * @param int $id Tournament ID
     * @param int|null $approvedBy Admin user ID who approved
     * @return bool Success status
     */
    public function approve(int $id, ?int $approvedBy = null): bool {
        try {
            $sql = "UPDATE tournaments SET 
                    status = 'approved', 
                    approved_at = datetime('now', '+9 hours'),
                    approved_by = :approved_by
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'id' => $id,
                'approved_by' => $approvedBy
            ]);
        } catch (Exception $e) {
            error_log("Tournament approval failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced tournament search with multiple filters
     * 
     * @param array $filters Filters: status, search, limit, offset, mode
     * @return array Array of tournaments
     */
    public function findWithFilters(array $filters = []): array
    {
        $sql = "SELECT 
                    id,
                    osu_topic_id,
                    title,
                    status,
                    rank_range_min,
                    rank_range_max,
                    team_size,
                    tournament_start,
                    registration_close,
                    parsed_at,
                    approved_at,
                    approved_by
                FROM tournaments WHERE 1=1";
        
        $params = [];
        
        // Filter by status
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Search by title or host
        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE ? OR raw_post_content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Order by parsed date (newest first)
        $sql .= " ORDER BY parsed_at DESC";
        
        // Pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update tournament status with enhanced status options
     * 
     * @param int $tournamentId Tournament ID
     * @param string $status New status
     * @param int|null $adminUserId Admin user making the change
     * @return bool Success status
     */
    public function updateTournamentStatus(int $tournamentId, string $status, ?int $adminUserId = null): bool
    {
        // Valid statuses for tournament management
        $validStatuses = [
            self::STATUS_PENDING,
            self::STATUS_APPROVED, 
            self::STATUS_REJECTED,
            self::STATUS_ARCHIVED,
            self::STATUS_CANCELLED
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid tournament status: ' . $status);
        }
        
        $sql = "UPDATE tournaments SET status = ?";
        $params = [$status];
        
        if ($status === self::STATUS_APPROVED && $adminUserId) {
            $sql .= ", approved_at = datetime('now', '+9 hours'), approved_by = ?";
            $params[] = $adminUserId;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $tournamentId;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get tournament statistics for admin dashboard
     * 
     * @return array Status counts
     */
    public function getTournamentStatistics(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                MAX(parsed_at) as latest_parsed
            FROM tournaments 
            GROUP BY status
            ORDER BY count DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    /**
     * Get approved tournaments for public display
     * Only returns tournaments with status 'approved'
     * 
     * @param int $limit Maximum number of tournaments to return
     * @param int $offset Offset for pagination
     * @param array $filters Additional filters (search, game_mode, etc.)
     * @return array Array of approved tournaments with public-safe data
     */
    public function getApprovedTournaments(int $limit = 20, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT 
                    id,
                    osu_topic_id,
                    title,
                    status,
                    rank_range_min,
                    rank_range_max,
                    rank_range,
                    team_size,
                    team_vs,
                    max_teams,
                    registration_open,
                    registration_close,
                    tournament_start,
                    end_date,
                    game_mode,
                    is_bws,
                    has_badge,
                    host_name,
                    star_rating_min,
                    star_rating_max,
                    google_form_id,
                    forum_url_slug,
                    discord_link,
                    approved_at,
                    parsed_at
                FROM tournaments 
                WHERE status = 'approved'";
        
        $params = [];
        
        // Filter by game mode if specified
        if (!empty($filters['game_mode'])) {
            $sql .= " AND game_mode = ?";
            $params[] = $filters['game_mode'];
        }
        
        // Search in title and host name
        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE ? OR host_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by registration status
        if (!empty($filters['registration_status'])) {
            $currentDate = date('Y-m-d H:i:s');
            switch ($filters['registration_status']) {
                case 'open':
                    $sql .= " AND (registration_close IS NULL OR registration_close > ?)";
                    $params[] = $currentDate;
                    break;
                case 'closed':
                    $sql .= " AND registration_close IS NOT NULL AND registration_close <= ?";
                    $params[] = $currentDate;
                    break;
                case 'upcoming':
                    $sql .= " AND tournament_start IS NOT NULL AND tournament_start > ?";
                    $params[] = $currentDate;
                    break;
                case 'ongoing':
                    $sql .= " AND tournament_start IS NOT NULL AND tournament_start <= ? AND (end_date IS NULL OR end_date > ?)";
                    $params[] = $currentDate;
                    $params[] = $currentDate;
                    break;
                case 'completed':
                    $sql .= " AND end_date IS NOT NULL AND end_date <= ?";
                    $params[] = $currentDate;
                    break;
            }
        }
        
        // Order by tournament start date (upcoming first, then by parsed date)
        $sql .= " ORDER BY 
                    CASE 
                        WHEN tournament_start IS NOT NULL AND tournament_start > datetime('now') THEN tournament_start
                        ELSE parsed_at 
                    END DESC";
        
        // Add pagination
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tournament statistics for public display
     * 
     * @return array Statistics array with counts
     */
    public function getPublicStatistics(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'approved' AND (registration_close IS NULL OR registration_close > datetime('now')) THEN 1 END) as active_registrations,
                COUNT(CASE WHEN status = 'approved' AND end_date IS NOT NULL AND end_date <= datetime('now') THEN 1 END) as completed_tournaments,
                COUNT(CASE WHEN status = 'approved' AND tournament_start IS NOT NULL AND tournament_start > datetime('now') THEN 1 END) as upcoming_tournaments,
                COUNT(*) as total_tournaments
            FROM tournaments
        ");
        
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Estimate participant count based on approved tournaments
        // This is a placeholder - you might want to add a participants table later
        $stats['estimated_participants'] = $stats['approved_count'] * 42; // Average estimate
        
        return $stats;
    }
    
    /**
     * Format tournament status for display
     * 
     * @param array $tournament Tournament data
     * @return array Tournament status info
     */
    public function getTournamentDisplayStatus(array $tournament): array
    {
        $currentDate = date('Y-m-d H:i:s');
        $status = 'unknown';
        $statusClass = 'inactive';
        $statusText = '정보 없음';
        
        // Check registration status
        if (!empty($tournament['registration_close']) && $tournament['registration_close'] > $currentDate) {
            $status = 'open';
            $statusClass = 'open';
            $statusText = '참가 모집 중';
        } elseif (!empty($tournament['registration_close']) && $tournament['registration_close'] <= $currentDate) {
            // Registration closed, check tournament status
            if (!empty($tournament['tournament_start'])) {
                if ($tournament['tournament_start'] > $currentDate) {
                    $status = 'upcoming';
                    $statusClass = 'upcoming';
                    $statusText = '개최 예정';
                } elseif (!empty($tournament['end_date']) && $tournament['end_date'] <= $currentDate) {
                    $status = 'completed';
                    $statusClass = 'closed';
                    $statusText = '완료됨';
                } else {
                    $status = 'ongoing';
                    $statusClass = 'closed';
                    $statusText = '진행 중';
                }
            } else {
                $status = 'registration_closed';
                $statusClass = 'upcoming';
                $statusText = '등록 마감';
            }
        } elseif (empty($tournament['registration_close'])) {
            // No registration close date, assume open
            $status = 'open';
            $statusClass = 'open';
            $statusText = '참가 모집 중';
        }
        
        return [
            'status' => $status,
            'class' => $statusClass,
            'text' => $statusText
        ];
    }
    
    /**
     * Format game mode for display
     * 
     * @param string|null $gameMode Game mode code from database
     * @return string Formatted game mode name
     */
    public function formatGameMode(?string $gameMode): string
    {
        $modes = [
            'STD' => 'osu! Standard',
            'TAIKO' => 'osu! Taiko', 
            'CATCH' => 'osu! Catch',
            'MANIA4' => 'osu! Mania 4K',
            'MANIA7' => 'osu! Mania 7K',
            'MANIA0' => 'osu! Mania',
            'ETC' => 'Mixed Mode'
        ];
        
        return $modes[$gameMode] ?? 'osu! Standard';
    }
    
    /**
     * Format rank range for display
     * 
     * @param array $tournament Tournament data
     * @return string Formatted rank range
     */
    public function formatRankRange(array $tournament): string
    {
        if (!empty($tournament['rank_range'])) {
            return $tournament['rank_range'];
        }
        
        if (!empty($tournament['rank_range_min']) && !empty($tournament['rank_range_max'])) {
            return '#' . number_format($tournament['rank_range_min']) . ' - #' . number_format($tournament['rank_range_max']);
        }
        
        if (!empty($tournament['rank_range_min'])) {
            return '#' . number_format($tournament['rank_range_min']) . '+';
        }
        
        return 'Open Rank';
    }
    
    /**
     * Format team information for display
     * 
     * @param array $tournament Tournament data
     * @return string Formatted team info
     */
    public function formatTeamInfo(array $tournament): string
    {
        $teamSize = $tournament['team_size'] ?? null;
        $teamVs = $tournament['team_vs'] ?? null;
        
        if ($teamVs && $teamVs > 0) {
            return $teamVs . 'v' . $teamVs;
        }
        
        if ($teamSize && $teamSize > 1) {
            return $teamSize . '인 팀';
        }
        
        return '1v1';
    }
}