<?php
// Admin Tournament Edit Page

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Models\Tournament;

// Include database configuration
require_once __DIR__ . '/../../config/database.php';

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Validate CSRF token if provided
if (isset($_GET['csrf_token'])) {
    if (!SecurityHelper::validateCsrfToken($_GET['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

// Get tournament ID from URL parameters
$tournamentId = SecurityHelper::validateInteger($_GET['id'] ?? null);
if (!$tournamentId) {
    http_response_code(400);
    die('Invalid tournament ID');
}

// Get tournament data
$db = getDatabaseConnection();
$tournamentModel = new Tournament($db);
$tournament = $tournamentModel->findById($tournamentId);

if (!$tournament) {
    http_response_code(404);
    die('Tournament not found');
}

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '토너먼트 편집 - ' . SecurityHelper::escapeHtml($tournament['title']);
$contentTemplate = __DIR__ . '/../../src/templates/admin/edit-tournament.php';

// Make tournament data available to template
$GLOBALS['tournament'] = $tournament;

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>