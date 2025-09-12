<?php
/**
 * Tournament Method - Main Entry Point
 * Korean osu! tournament discovery platform
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Include autoloader and configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;

try {
    // Initialize database with secure setup
    DatabaseHelper::initializeSecureDatabase();
    
    // Page configuration for main template
    define('MAIN_TEMPLATE', true);
    $pageTitle = '한국 osu! 토너먼트 발견';
    $showHero = true;
    $contentTemplate = __DIR__ . '/../src/templates/pages/homepage.php';
    
    // Include main layout template
    include __DIR__ . '/../src/templates/layouts/main.php';
    
} catch (Exception $e) {
    // Enhanced error logging with context
    error_log("Application error [" . date('Y-m-d H:i:s') . "]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    
    // Show error page with proper styling
    define('MAIN_TEMPLATE', true);
    $pageTitle = 'System Error';
    $showHero = false;
    $errorMessage = '시스템 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
    $contentTemplate = __DIR__ . '/../src/templates/pages/error.php';
    
    include __DIR__ . '/../src/templates/layouts/main.php';
}
?>