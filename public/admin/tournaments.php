<?php
// Admin Tournament Management Dashboard - View, edit, and manage all tournaments

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;
use TourneyMethod\Models\Tournament;
use TourneyMethod\Models\SystemLog;
use TourneyMethod\Utils\DateHelper;

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Initialize database connection
$dbHelper = DatabaseHelper::getInstance();
$db = $dbHelper->getConnection();

// Create Tournament model
$tournament = new Tournament($db);
$systemLog = new SystemLog($db);

// Handle status change requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    SecurityHelper::validateCsrfToken($_POST['csrf_token'] ?? '', $_SESSION['csrf_token'] ?? '');
    
    if ($_POST['action'] === 'change_status' && isset($_POST['tournament_id'], $_POST['new_status'])) {
        $tournamentId = (int)$_POST['tournament_id'];
        $newStatus = $_POST['new_status'];
        $reason = $_POST['reason'] ?? '';
        
        try {
            $success = $tournament->updateTournamentStatus($tournamentId, $newStatus, $currentUser->getUserId());
            
            if ($success) {
                // Log the status change
                $systemLog->log(
                    'INFO',
                    "Tournament {$tournamentId} status changed to {$newStatus} by admin",
                    'admin',
                    ['tournament_id' => $tournamentId, 'old_status' => $_POST['old_status'] ?? '', 'new_status' => $newStatus, 'reason' => $reason],
                    $currentUser->getUserId()
                );
                
                $successMessage = "토너먼트 상태가 성공적으로 변경되었습니다.";
            } else {
                $errorMessage = "토너먼트 상태 변경에 실패했습니다.";
            }
        } catch (Exception $e) {
            $systemLog->log('ERROR', "Tournament status change failed: " . $e->getMessage(), 'admin', 
                          ['tournament_id' => $tournamentId, 'error' => $e->getMessage()], $currentUser->getUserId());
            $errorMessage = "상태 변경 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

// Handle filtering parameters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 25; // Show 25 tournaments per page
$offset = ($page - 1) * $limit;

$filters['limit'] = $limit;
$filters['offset'] = $offset;

// Get tournaments and statistics
$tournaments = $tournament->findWithFilters($filters);
$statistics = $tournament->getTournamentStatistics();

// Generate CSRF token for forms
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '토너먼트 관리';
$contentTemplate = __DIR__ . '/../../src/templates/admin/tournament-management.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>