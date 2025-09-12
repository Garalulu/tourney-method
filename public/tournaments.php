<?php
/**
 * Tourney Method - Tournament Discovery Page
 * Public tournament listing with filtering and modal detail views
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Include autoloader and configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use TourneyMethod\Models\Tournament;
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;

try {
    // Initialize database with secure setup
    $pdo = DatabaseHelper::getSecureConnection();
    $tournamentModel = new Tournament($pdo);
    
    // Get initial tournaments for page load (first 10)
    $initialTournaments = $tournamentModel->getApprovedTournaments(10, 0);
    
    // Get tournament statistics for display
    $stats = $tournamentModel->getPublicStatistics();
    
    // Page configuration for main template
    define('MAIN_TEMPLATE', true);
    $pageTitle = '토너먼트 발견 - 모든 토너먼트';
    $showHero = false;
    $contentTemplate = __DIR__ . '/../src/templates/pages/tournaments.php';
    
    // Pass data to template
    $tournaments = $initialTournaments;
    $tournamentStats = $stats;
    $containerClass = 'tournaments-container';
    
    // Include main layout template
    include __DIR__ . '/../src/templates/layouts/main.php';
    
} catch (Exception $e) {
    // Log the specific error for debugging
    error_log("Tournament listing error [" . date('Y-m-d H:i:s') . "]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    
    // Show error page with proper styling
    define('MAIN_TEMPLATE', true);
    $pageTitle = 'System Error';
    $showHero = false;
    $errorMessage = '토너먼트 목록을 불러오는 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
    $contentTemplate = __DIR__ . '/../src/templates/pages/error.php';
    
    include __DIR__ . '/../src/templates/layouts/main.php';
}
?>