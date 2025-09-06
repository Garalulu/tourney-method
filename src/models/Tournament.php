<?php

namespace TourneyMethod\Models;

use TourneyMethod\Utils\SecurityHelper;
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
    
    public const STATUS_PENDING = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
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
}