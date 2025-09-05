<?php

/**
 * Health Check Endpoint for DigitalOcean App Platform
 * Returns JSON status of application components
 */

// Set timezone for development
date_default_timezone_set('Asia/Seoul');

header('Content-Type: application/json');

$status = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

try {
    // Database connectivity check
    $dbPath = __DIR__ . '/../data/database/tournaments.db';
    if (!file_exists($dbPath)) {
        throw new Exception('Database file not found');
    }
    
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test database query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $userCount = $stmt->fetch()['count'];
    
    $status['checks']['database'] = [
        'status' => 'ok',
        'user_count' => $userCount
    ];
    
} catch (Exception $e) {
    $status['status'] = 'unhealthy';
    $status['checks']['database'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(503);
}

// Check required directories (cross-platform)
$requiredDirs = [__DIR__ . '/../logs'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        $status['checks']['filesystem'][] = [
            'path' => $dir,
            'status' => 'missing'
        ];
        $status['status'] = 'unhealthy';
        http_response_code(503);
    }
}

// Check timezone
$status['checks']['timezone'] = [
    'configured' => date_default_timezone_get(),
    'current_time' => date('Y-m-d H:i:s T')
];

echo json_encode($status, JSON_PRETTY_PRINT);