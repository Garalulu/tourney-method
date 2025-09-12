<?php

namespace TourneyMethod\Utils;

use PDO;
use PDOException;
use Exception;

class DatabaseHelper
{
    private static ?self $instance = null;
    private PDO $connection;
    
    /** Database connection timeout in seconds */
    private const CONNECTION_TIMEOUT = 30;
    
    private function __construct()
    {
        $this->connect();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function connect(): void
    {
        $dbPath = $_ENV['DATABASE_PATH'] ?? __DIR__ . '/../../data/tournament_method.db';
        
        // Ensure the directory exists
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        try {
            $this->connection = new PDO("sqlite:$dbPath", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => self::CONNECTION_TIMEOUT,
                PDO::ATTR_PERSISTENT => false,
            ]);
            
            // Configure SQLite for security and performance
            $this->configureSQLiteSecurity($this->connection);
            
            // Initialize database schema if it doesn't exist
            $this->initializeSchema();
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    private function initializeSchema(): void
    {
        $schemaPath = __DIR__ . '/../../data/database/schema.sql';
        
        // Check if tables already exist
        $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tournaments'");
        if ($stmt->fetch()) {
            return; // Tables already exist
        }
        
        if (file_exists($schemaPath)) {
            $schema = file_get_contents($schemaPath);
            if ($schema !== false) {
                $this->connection->exec($schema);
            }
        }
    }
    
    /**
     * Configure SQLite-specific security and performance settings
     */
    private function configureSQLiteSecurity(PDO $pdo): void
    {
        try {
            // Enable foreign key constraints for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            // Set UTF-8 encoding for Korean support
            $pdo->exec("PRAGMA encoding = 'UTF-8'");
            
            // Performance and security optimizations
            $pdo->exec('PRAGMA journal_mode = WAL'); // Better concurrency
            $pdo->exec('PRAGMA synchronous = NORMAL'); // Balance safety and speed
            $pdo->exec('PRAGMA temp_store = MEMORY'); // Use memory for temp data
            $pdo->exec('PRAGMA mmap_size = 268435456'); // 256MB memory map
            
            // Security: Disable potentially dangerous features
            $pdo->exec('PRAGMA trusted_schema = OFF');
            
        } catch (PDOException $e) {
            error_log("SQLite security configuration failed: " . $e->getMessage());
            // Don't throw - these are optimizations, not critical
        }
    }
    
    public function getConnection(): PDO
    {
        return $this->connection;
    }
    
    public function prepare(string $sql): \PDOStatement
    {
        return $this->connection->prepare($sql);
    }
    
    public function query(string $sql): \PDOStatement
    {
        return $this->connection->query($sql);
    }
    
    public function exec(string $sql): int|false
    {
        return $this->connection->exec($sql);
    }
    
    public function lastInsertId(): string|false
    {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->connection->commit();
    }
    
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }
    
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }
    
    /**
     * Static method to get secure connection for backward compatibility
     * Used by improved public PHP files
     */
    public static function getSecureConnection(): PDO
    {
        return self::getInstance()->getConnection();
    }
    
    /**
     * Static method to initialize secure database
     * Used by improved public PHP files
     */
    public static function initializeSecureDatabase(): void
    {
        // Initialize database structure if needed
        require_once __DIR__ . '/../../config/database.php';
        initializeDatabase();
        setDatabasePermissions();
        
        // Ensure singleton instance is created
        self::getInstance();
    }
    
    /**
     * Get database statistics for health checks
     */
    public static function getDatabaseStats(): array
    {
        $dbPath = $_ENV['DATABASE_PATH'] ?? __DIR__ . '/../../data/tournament_method.db';
        
        $stats = [
            'file_exists' => file_exists($dbPath),
            'file_size' => file_exists($dbPath) ? filesize($dbPath) : 0,
            'readable' => is_readable($dbPath),
            'writable' => is_writable($dbPath),
            'modified' => file_exists($dbPath) ? filemtime($dbPath) : 0
        ];
        
        try {
            $pdo = self::getSecureConnection();
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM sqlite_master WHERE type='table'");
            $stats['table_count'] = (int)$stmt->fetch()['total'];
        } catch (Exception $e) {
            $stats['table_count'] = 0;
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    // Prevent cloning and unserialization for singleton
    private function __clone() {}
    public function __wakeup() {}
}