<?php
// Admin System Logs Viewer - Monitor system logs and parser errors

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;
use TourneyMethod\Models\SystemLog;
use TourneyMethod\Utils\DateHelper;

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Initialize database connection
$db = DatabaseHelper::getInstance();

// Create SystemLog model
$systemLog = new SystemLog($db->getConnection());

// Handle filtering parameters
$filters = [];
if (!empty($_GET['level'])) {
    $filters['level'] = $_GET['level'];
}
if (!empty($_GET['source'])) {
    $filters['source'] = $_GET['source'];
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 50; // Show 50 logs per page
$offset = ($page - 1) * $limit;

$filters['limit'] = $limit;
$filters['offset'] = $offset;

// Get logs and statistics
$logs = $systemLog->findWithFilters($filters);
$statistics = $systemLog->getLogStatistics();
$recentErrors = $systemLog->getRecentParserErrors(5);

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '시스템 로그';
$contentTemplate = __DIR__ . '/../../src/templates/admin/logs-viewer.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>