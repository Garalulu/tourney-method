<?php

namespace TourneyMethod\Utils;

use PDO;
use PDOException;

class DatabaseHelper
{
    private static ?self $instance = null;
    private PDO $connection;
    
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
            ]);
            
            // Enable foreign key constraints for SQLite
            $this->connection->exec('PRAGMA foreign_keys = ON');
            
            // Set UTF-8 encoding for Korean support
            $this->connection->exec("PRAGMA encoding = 'UTF-8'");
            
            // Initialize database schema if it doesn't exist
            $this->initializeSchema();
            
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
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
    
    // Prevent cloning and unserialization for singleton
    private function __clone() {}
    public function __wakeup() {}
}