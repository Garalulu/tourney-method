<?php
// Admin Dashboard - Main Admin Interface

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Models\AdminUser;

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '관리자 대시보드';
$contentTemplate = __DIR__ . '/../../src/templates/admin/dashboard.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>