<?php

/**
 * PHPUnit Bootstrap File
 * Sets up testing environment
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set test timezone
date_default_timezone_set('Asia/Seoul');

// Create test database directory
$testDbDir = __DIR__ . '/fixtures';
if (!is_dir($testDbDir)) {
    mkdir($testDbDir, 0755, true);
}

// Define test constants
define('TEST_DB_PATH', $testDbDir . '/test.db');
define('SCHEMA_PATH', __DIR__ . '/../data/database/schema.sql');
define('PHPUNIT_RUNNING', true);

// Configure session handling for tests
ini_set('session.use_cookies', 0);
ini_set('session.cache_limiter', '');
ini_set('session.use_trans_sid', 0);
ini_set('session.save_handler', 'files');
session_save_path($testDbDir . '/sessions');

// Create session directory
$sessionDir = $testDbDir . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0755, true);
}