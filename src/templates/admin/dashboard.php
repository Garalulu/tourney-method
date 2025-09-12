<?php
/**
 * Admin Dashboard Template - Refactored
 * Modular structure with component-based architecture
 * WCAG 2.1 AA compliant, mobile-first responsive design
 */

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;
use TourneyMethod\Models\Tournament;

// Include database configuration
require_once __DIR__ . '/../../../config/database.php';

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Get pending review tournaments
$db = getDatabaseConnection();
$tournamentModel = new Tournament($db);
$pendingTournaments = $tournamentModel->findPendingReview();

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityHelper::generateCsrfToken();
}

// Calculate statistics
$totalTournaments = count($pendingTournaments) + 15; // Mock data for existing tournaments
$pendingCount = count($pendingTournaments);
$monthlyApproved = 8; // Mock data
$avgStarRating = 5.2; // Mock data
?>

<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tourney Method 관리자 대시보드 - 토너먼트 관리 및 검토">
    <title>관리자 대시보드 - Tourney Method</title>
    
    <!-- Pico.css for performance and accessibility -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Admin theme and dashboard styles -->
    <link rel="stylesheet" href="/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/components/dashboard-header.php'; ?>
        <?php include __DIR__ . '/components/dashboard-stats.php'; ?>
        <?php include __DIR__ . '/components/dashboard-actions.php'; ?>
        <?php include __DIR__ . '/components/dashboard-review.php'; ?>
        <?php include __DIR__ . '/components/dashboard-status.php'; ?>
        <?php include __DIR__ . '/components/dashboard-dev-info.php'; ?>
    </div>
    
    <!-- Dashboard JavaScript -->
    <script src="/assets/js/admin-dashboard.js"></script>
</body>
</html>