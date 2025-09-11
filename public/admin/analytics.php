<?php
// Admin Analytics Dashboard - Tournament Statistics and Data Visualization

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;
use TourneyMethod\Models\Tournament;

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

// Get tournament statistics for charts
$statistics = $tournament->getTournamentStatistics();

// Mock data for demonstration (replace with real data later)
$mockStats = [
    'monthly_tournaments' => [
        'January' => 5, 'February' => 8, 'March' => 12, 'April' => 15,
        'May' => 18, 'June' => 22, 'July' => 20, 'August' => 25,
        'September' => 30, 'October' => 28, 'November' => 24, 'December' => 26
    ],
    'sr_distribution' => [
        '1-2★' => 15, '2-3★' => 25, '3-4★' => 35, '4-5★' => 45,
        '5-6★' => 55, '6-7★' => 35, '7-8★' => 20, '8-9★' => 8, '9-10★' => 2
    ],
    'tournament_status' => [
        'completed' => 156, 'ongoing' => 24, 'pending' => 12, 'cancelled' => 8
    ],
    'peak_hours' => [
        '00' => 2, '06' => 5, '12' => 15, '18' => 35, '20' => 42, '22' => 28
    ]
];

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '통계 분석';
$contentTemplate = __DIR__ . '/../../src/templates/admin/analytics.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>