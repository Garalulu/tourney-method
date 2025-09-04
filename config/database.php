<?php
/**
 * Database Configuration
 * Secure SQLite database connection outside web root
 */

// Database file path - OUTSIDE web root for security
define('DB_PATH', __DIR__ . '/../data/tournament_method.db');

// Ensure data directory exists
$dataDir = dirname(DB_PATH);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

/**
 * Get secure database connection with proper configuration
 */
function getDatabaseConnection(): PDO {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        
        // Enable foreign key constraints
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        // Enable WAL mode for better concurrency
        $pdo->exec('PRAGMA journal_mode = WAL');
        
        // Set secure timeout
        $pdo->exec('PRAGMA busy_timeout = 30000');
        
        // Error mode for debugging
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Fetch mode
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}

/**
 * Initialize database with schema if not exists
 */
function initializeDatabase(): void {
    $pdo = getDatabaseConnection();
    
    // Check if tables exist
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    
    if ($result->fetchColumn() === false) {
        // Database is empty, run schema
        $schema = file_get_contents(__DIR__ . '/../data/schema.sql');
        if ($schema === false) {
            throw new Exception("Could not read schema file");
        }
        
        $pdo->exec($schema);
        error_log("Database schema initialized successfully");
    }
}

/**
 * Set secure file permissions on database file
 */
function setDatabasePermissions(): void {
    if (file_exists(DB_PATH)) {
        // Set restrictive permissions (owner read/write only)
        chmod(DB_PATH, 0600);
    }
    
    // Ensure data directory is not web accessible
    $htaccess = dirname(DB_PATH) . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}