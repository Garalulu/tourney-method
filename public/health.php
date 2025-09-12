<?php

/**
 * Health Check Endpoint for DigitalOcean App Platform
 * Returns JSON status of application components
 */

// Set timezone for development
date_default_timezone_set('Asia/Seoul');

// Security headers for health endpoint
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$status = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production',
    'checks' => []
];

try {
    // Database connectivity and structure check
    $dbPath = __DIR__ . '/../data/tournament_method.db';
    if (!file_exists($dbPath)) {
        throw new Exception('Database file not found');
    }
    
    if (!is_readable($dbPath)) {
        throw new Exception('Database file not readable');
    }
    
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test critical table existence and basic query
    $requiredTables = ['users', 'tournaments', 'admin_users'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            throw new Exception("Required table '$table' not found");
        }
    }
    
    // Test basic queries
    $userStmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $tournamentStmt = $pdo->query('SELECT COUNT(*) as count FROM tournaments');
    
    $status['checks']['database'] = [
        'status' => 'ok',
        'user_count' => (int)$userStmt->fetch()['count'],
        'tournament_count' => (int)$tournamentStmt->fetch()['count'],
        'tables_verified' => count($requiredTables)
    ];
    
} catch (Exception $e) {
    $status['status'] = 'unhealthy';
    $status['checks']['database'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(503);
}

// Check required directories and file permissions
$requiredDirs = [
    'logs' => __DIR__ . '/../logs',
    'data' => __DIR__ . '/../data',
    'vendor' => __DIR__ . '/../vendor'
];

$status['checks']['filesystem'] = [];
foreach ($requiredDirs as $name => $dir) {
    $check = [
        'name' => $name,
        'path' => $dir,
        'exists' => is_dir($dir),
        'readable' => is_readable($dir),
        'writable' => is_writable($dir)
    ];
    
    if (!$check['exists']) {
        $check['status'] = 'missing';
        $status['status'] = 'unhealthy';
        http_response_code(503);
    } elseif (!$check['readable']) {
        $check['status'] = 'unreadable';
        $status['status'] = 'unhealthy';
        http_response_code(503);
    } else {
        $check['status'] = 'ok';
    }
    
    $status['checks']['filesystem'][$name] = $check;
}

// Check timezone
$status['checks']['timezone'] = [
    'configured' => date_default_timezone_get(),
    'current_time' => date('Y-m-d H:i:s T')
];

// Add performance metrics
$status['performance'] = [
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
];

// Add system info (useful for debugging)
$status['system'] = [
    'php_version' => PHP_VERSION,
    'os' => PHP_OS,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);