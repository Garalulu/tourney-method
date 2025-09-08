<?php
// Admin Tournament Edit Page

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Models\Tournament;
use TourneyMethod\Services\ValidationService;

// Include database configuration
require_once __DIR__ . '/../../config/database.php';

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Get tournament ID from URL parameters
$tournamentId = SecurityHelper::validateInteger($_GET['id'] ?? null);
if (!$tournamentId) {
    http_response_code(400);
    die('Invalid tournament ID');
}

// Get database connection and tournament model
$db = getDatabaseConnection();
$tournamentModel = new Tournament($db);
$tournament = $tournamentModel->findById($tournamentId);

if (!$tournament) {
    http_response_code(404);
    die('Tournament not found');
}

// Handle POST requests for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!SecurityHelper::validateCsrfToken($_POST['csrf_token'] ?? '', $_SESSION['csrf_token'] ?? '')) {
            throw new \Exception('Invalid CSRF token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        $adminUserId = $_SESSION['admin_user_id'] ?? null;

        if ($action === 'update') {
            // Validate and process tournament update
            $formData = [
                'title' => trim($_POST['title'] ?? ''),
                'rank_range' => trim($_POST['rank_range'] ?? ''),
                'team_size' => trim($_POST['team_size'] ?? ''),
                'max_teams' => trim($_POST['max_teams'] ?? ''),
                'start_date' => trim($_POST['start_date'] ?? ''),
                'registration_close' => trim($_POST['registration_close'] ?? ''),
                'google_sheet_id' => trim($_POST['google_sheet_id'] ?? ''),
                'sheet_link' => trim($_POST['sheet_link'] ?? '')
            ];

            // Basic validation
            if (empty($formData['title'])) {
                throw new \Exception('토너먼트 제목은 필수입니다.');
            }

            // Update tournament data
            if ($tournamentModel->update($tournamentId, $formData)) {
                $_SESSION['success_message'] = '토너먼트 정보가 성공적으로 업데이트되었습니다.';
            } else {
                throw new \Exception('토너먼트 업데이트 중 오류가 발생했습니다.');
            }

        } elseif ($action === 'approve') {
            // Validate and process tournament approval
            $formData = [
                'title' => trim($_POST['title'] ?? ''),
                'rank_range' => trim($_POST['rank_range'] ?? ''),
                'team_size' => trim($_POST['team_size'] ?? ''),
                'max_teams' => trim($_POST['max_teams'] ?? ''),
                'start_date' => trim($_POST['start_date'] ?? ''),
                'registration_close' => trim($_POST['registration_close'] ?? ''),
                'google_sheet_id' => trim($_POST['google_sheet_id'] ?? ''),
                'sheet_link' => trim($_POST['sheet_link'] ?? '')
            ];

            // Basic validation for approval
            if (empty($formData['title'])) {
                throw new \Exception('토너먼트 제목은 승인 전에 필수입니다.');
            }

            $db->beginTransaction();
            
            try {
                // Update tournament data first
                if (!$tournamentModel->update($tournamentId, $formData)) {
                    throw new \Exception('토너먼트 데이터 업데이트에 실패했습니다.');
                }

                // Approve tournament
                if (!$tournamentModel->approve($tournamentId, $adminUserId)) {
                    throw new \Exception('토너먼트 승인에 실패했습니다.');
                }

                $db->commit();
                $_SESSION['success_message'] = '토너먼트가 성공적으로 승인되고 공개되었습니다.';
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }

        } else {
            throw new \Exception('Invalid action specified.');
        }

        // Regenerate CSRF token after successful form submission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Redirect back to edit page with success message
        header("Location: /admin/edit.php?id={$tournamentId}");
        exit;

    } catch (\Exception $e) {
        // Store error message and continue to display form
        $_SESSION['error_message'] = $e->getMessage();
        
        // Regenerate CSRF token after error
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Validate CSRF token if provided in GET request
if (isset($_GET['csrf_token'])) {
    if (!SecurityHelper::validateCsrfToken($_GET['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

// Refresh tournament data in case it was updated
$tournament = $tournamentModel->findById($tournamentId);

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '토너먼트 편집 - ' . SecurityHelper::escapeHtml($tournament['title']);
$contentTemplate = __DIR__ . '/../../src/templates/admin/edit-tournament.php';

// Make tournament data available to template
$GLOBALS['tournament'] = $tournament;

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>