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