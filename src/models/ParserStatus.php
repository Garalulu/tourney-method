<?php

namespace TourneyMethod\Models;

use PDO;
use Exception;

/**
 * ParserStatus model for managing parser scheduling and activity
 * 
 * Handles parser status tracking, configuration, and execution history
 * for admin monitoring and control.
 */
class ParserStatus
{
    private PDO $db;
    
    // Parser status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_RUNNING = 'running';
    public const STATUS_ERROR = 'error';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializeParserTable();
    }
    
    /**
     * Initialize parser status table if it doesn't exist
     */
    private function initializeParserTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS parser_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL DEFAULT 'active',
            last_run DATETIME,
            next_run DATETIME,
            last_success DATETIME,
            last_error DATETIME,
            total_runs INTEGER DEFAULT 0,
            successful_runs INTEGER DEFAULT 0,
            failed_runs INTEGER DEFAULT 0,
            current_run_pid INTEGER,
            schedule_interval TEXT DEFAULT '0 2 * * *',
            is_enabled BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            CHECK (status IN ('active', 'paused', 'running', 'error'))
        )";
        
        $this->db->exec($sql);
        
        // Insert default record if table is empty
        $count = $this->db->query("SELECT COUNT(*) FROM parser_status")->fetchColumn();
        if ($count == 0) {
            $this->db->exec("INSERT INTO parser_status (status, schedule_interval) VALUES ('active', '0 2 * * *')");
        }
    }
    
    /**
     * Get current parser status and configuration
     * 
     * @return array|null Parser status data
     */
    public function getCurrentStatus(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM parser_status 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute();
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($status) {
            // Calculate next run time based on schedule
            $status['next_run_calculated'] = $this->calculateNextRun($status['schedule_interval'], $status['last_run']);
        }
        
        return $status;
    }
    
    /**
     * Update parser status
     * 
     * @param string $status New status
     * @param array $data Additional data to update
     * @return bool Success status
     */
    public function updateStatus(string $status, array $data = []): bool
    {
        $validStatuses = [self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_RUNNING, self::STATUS_ERROR];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid parser status: ' . $status);
        }
        
        $updateFields = ['status = ?', 'updated_at = datetime(\'now\', \'+9 hours\')'];
        $params = [$status];
        
        // Add optional fields
        if (isset($data['last_run'])) {
            $updateFields[] = 'last_run = ?';
            $params[] = $data['last_run'];
        }
        
        if (isset($data['last_success'])) {
            $updateFields[] = 'last_success = ?';
            $params[] = $data['last_success'];
        }
        
        if (isset($data['last_error'])) {
            $updateFields[] = 'last_error = ?';
            $params[] = $data['last_error'];
        }
        
        if (isset($data['total_runs'])) {
            // Check if it's a SQL expression or a literal value
            if (is_string($data['total_runs']) && strpos($data['total_runs'], 'COALESCE') !== false) {
                $updateFields[] = 'total_runs = ' . $data['total_runs'];
            } else {
                $updateFields[] = 'total_runs = ?';
                $params[] = $data['total_runs'];
            }
        }
        
        if (isset($data['successful_runs'])) {
            // Check if it's a SQL expression or a literal value
            if (is_string($data['successful_runs']) && strpos($data['successful_runs'], 'COALESCE') !== false) {
                $updateFields[] = 'successful_runs = ' . $data['successful_runs'];
            } else {
                $updateFields[] = 'successful_runs = ?';
                $params[] = $data['successful_runs'];
            }
        }
        
        if (isset($data['failed_runs'])) {
            // Check if it's a SQL expression or a literal value
            if (is_string($data['failed_runs']) && strpos($data['failed_runs'], 'COALESCE') !== false) {
                $updateFields[] = 'failed_runs = ' . $data['failed_runs'];
            } else {
                $updateFields[] = 'failed_runs = ?';
                $params[] = $data['failed_runs'];
            }
        }
        
        $sql = "UPDATE parser_status SET " . implode(', ', $updateFields) . " WHERE id = (SELECT id FROM parser_status ORDER BY updated_at DESC LIMIT 1)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Record parser run start
     * 
     * @param int|null $pid Process ID
     * @return bool Success status
     */
    public function recordRunStart(?int $pid = null): bool
    {
        $kstTime = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        
        return $this->updateStatus(self::STATUS_RUNNING, [
            'last_run' => $kstTime->format('Y-m-d H:i:s'),
            'total_runs' => $this->incrementCounter('total_runs')
        ]);
    }
    
    /**
     * Record successful parser run completion
     * 
     * @param array $stats Run statistics
     * @return bool Success status
     */
    public function recordRunSuccess(array $stats = []): bool
    {
        $kstTime = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        
        return $this->updateStatus(self::STATUS_ACTIVE, [
            'last_success' => $kstTime->format('Y-m-d H:i:s'),
            'successful_runs' => $this->incrementCounter('successful_runs')
        ]);
    }
    
    /**
     * Record failed parser run
     * 
     * @param string $error Error message
     * @return bool Success status
     */
    public function recordRunFailure(string $error): bool
    {
        $kstTime = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        
        return $this->updateStatus(self::STATUS_ERROR, [
            'last_error' => $kstTime->format('Y-m-d H:i:s'),
            'failed_runs' => $this->incrementCounter('failed_runs')
        ]);
    }
    
    /**
     * Toggle parser enabled/disabled state
     * 
     * @param bool $enabled New enabled state
     * @return bool Success status
     */
    public function setEnabled(bool $enabled): bool
    {
        $status = $enabled ? self::STATUS_ACTIVE : self::STATUS_PAUSED;
        
        $stmt = $this->db->prepare("
            UPDATE parser_status 
            SET is_enabled = ?, status = ?, updated_at = datetime('now', '+9 hours')
            WHERE id = (SELECT id FROM parser_status ORDER BY updated_at DESC LIMIT 1)
        ");
        
        return $stmt->execute([$enabled ? 1 : 0, $status]);
    }
    
    /**
     * Update parser schedule interval
     * 
     * @param string $interval Cron expression (e.g., "0 2 * * *")
     * @return bool Success status
     */
    public function updateSchedule(string $interval): bool
    {
        // Basic cron validation
        if (!$this->isValidCronExpression($interval)) {
            throw new \InvalidArgumentException('Invalid cron expression: ' . $interval);
        }
        
        $stmt = $this->db->prepare("
            UPDATE parser_status 
            SET schedule_interval = ?, updated_at = datetime('now', '+9 hours')
            WHERE id = (SELECT id FROM parser_status ORDER BY updated_at DESC LIMIT 1)
        ");
        
        return $stmt->execute([$interval]);
    }
    
    /**
     * Get recent parser run history
     * 
     * @param int $limit Number of runs to retrieve
     * @return array Recent run logs from system_logs
     */
    public function getRecentRuns(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM system_logs 
            WHERE source = 'BasicTournamentParser' OR source = 'parser' 
               OR message LIKE '%parser%' 
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get parser statistics summary
     * 
     * @return array Parser statistics
     */
    public function getStatistics(): array
    {
        $current = $this->getCurrentStatus();
        
        if (!$current) {
            return [];
        }
        
        // Calculate success rate
        $totalRuns = (int)$current['total_runs'];
        $successfulRuns = (int)$current['successful_runs'];
        $successRate = $totalRuns > 0 
            ? round(($successfulRuns / $totalRuns) * 100, 2)
            : 0;
        
        // Calculate average runs per day (last 30 days)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM system_logs 
            WHERE source = 'parser' 
            AND created_at >= datetime('now', '-30 days')
        ");
        $stmt->execute();
        $recentLogs = $stmt->fetchColumn();
        $avgRunsPerDay = round($recentLogs / 30, 2);
        
        return [
            'total_runs' => $current['total_runs'],
            'successful_runs' => $current['successful_runs'],
            'failed_runs' => $current['failed_runs'],
            'success_rate' => $successRate,
            'avg_runs_per_day' => $avgRunsPerDay,
            'last_run' => $current['last_run'],
            'last_success' => $current['last_success'],
            'last_error' => $current['last_error'],
            'is_enabled' => (bool)$current['is_enabled'],
            'current_status' => $current['status']
        ];
    }
    
    /**
     * Calculate next run time based on cron schedule
     * 
     * @param string $cronExpression Cron expression
     * @param string|null $lastRun Last run time
     * @return string|null Next run time
     */
    private function calculateNextRun(string $cronExpression, ?string $lastRun): ?string
    {
        // For now, return a simple calculation for daily 2 AM runs
        // In a real implementation, you'd use a cron parser library
        if ($cronExpression === '0 2 * * *') {
            $next = new \DateTime('tomorrow 02:00:00', new \DateTimeZone('Asia/Seoul'));
            return $next->format('Y-m-d H:i:s');
        }
        
        // Default to next day at 2 AM for other expressions
        $next = new \DateTime('+1 day 02:00:00', new \DateTimeZone('Asia/Seoul'));
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Increment a counter field atomically
     * 
     * @param string $field Field name to increment
     * @return string SQL expression for increment
     */
    private function incrementCounter(string $field): string
    {
        return "COALESCE({$field}, 0) + 1";
    }
    
    /**
     * Basic cron expression validation
     * 
     * @param string $expression Cron expression
     * @return bool Valid or not
     */
    private function isValidCronExpression(string $expression): bool
    {
        $parts = explode(' ', trim($expression));
        
        // Basic check: should have 5 parts (minute, hour, day, month, weekday)
        return count($parts) === 5;
    }
}