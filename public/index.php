<?php
/**
 * Tournament Method - Main Entry Point
 * This file should be the only PHP file in the web root
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Include configuration (outside web root)
require_once __DIR__ . '/../config/database.php';

try {
    // Initialize database with secure setup
    initializeDatabase();
    setDatabasePermissions();
    
    echo "<h1>Tournament Method</h1>";
    echo "<p>System initialized successfully!</p>";
    echo "<p>Database is secure outside web root.</p>";
    
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>System Error</h1>";
    echo "<p>Please check system logs.</p>";
}
?>