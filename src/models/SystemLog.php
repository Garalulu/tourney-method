<?php

namespace TourneyMethod\Models;

use PDO;

/**
 * SystemLog model for managing system logs and error tracking
 * 
 * Handles log retrieval, filtering, and creation for admin monitoring
 * and parser error tracking.
 */
class SystemLog
{
    private PDO $db;
    
    // Log levels following PSR-3 standard
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_NOTICE = 'NOTICE';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';
    public const LEVEL_ALERT = 'ALERT';
    public const LEVEL_EMERGENCY = 'EMERGENCY';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find all system logs with optional filtering
     * 
     * @param array $filters Optional filters (level, source, limit, offset)
     * @return array Array of log entries
     */
    public function findWithFilters(array $filters = []): array
    {
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];
        
        // Filter by log level
        if (!empty($filters['level'])) {
            $sql .= " AND level = ?";
            $params[] = strtoupper($filters['level']);
        }
        
        // Filter by source component
        if (!empty($filters['source'])) {
            $sql .= " AND source LIKE ?";
            $params[] = '%' . $filters['source'] . '%';
        }
        
        // Order by most recent first
        $sql .= " ORDER BY created_at DESC";
        
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
     * Get log statistics for dashboard summary
     * 
     * @return array Log count by level
     */
    public function getLogStatistics(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                level,
                COUNT(*) as count,
                MAX(created_at) as latest
            FROM system_logs 
            WHERE created_at >= datetime('now', '-7 days')
            GROUP BY level
            ORDER BY count DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new system log entry
     * 
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, etc.)
     * @param string $message Log message
     * @param string|null $source Source component (parser, auth, admin, api)
     * @param array|null $context Additional context data as associative array
     * @param int|null $userId User ID if action is user-related
     * @return bool Success status
     */
    public function log(string $level, string $message, ?string $source = null, ?array $context = null, ?int $userId = null): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_logs (level, message, context, source, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now', '+9 hours'))
        ");
        
        return $stmt->execute([
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            $source,
            $userId
        ]);
    }
    
    /**
     * Get recent parser errors for quick overview
     * 
     * @param int $limit Number of recent errors to retrieve
     * @return array Recent parser error logs
     */
    public function getRecentParserErrors(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM system_logs 
            WHERE source = 'parser' 
            AND level IN ('ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY')
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean old logs older than specified days
     * 
     * @param int $days Number of days to keep logs
     * @return int Number of deleted log entries
     */
    public function cleanOldLogs(int $days = 90): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_logs 
            WHERE created_at < datetime('now', '-' || ? || ' days')
        ");
        
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Get all available log levels for filter dropdown
     * 
     * @return array Available log levels
     */
    public static function getAvailableLevels(): array
    {
        return [
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_NOTICE,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY
        ];
    }
}