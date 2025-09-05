<?php
// Admin Logout Handler - Secure Session Cleanup

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Models\AdminUser;
use TourneyMethod\Utils\SecurityHelper;

// Initialize security configuration
SecurityHelper::configureSecureSession();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout attempt (if user was authenticated)
if (AdminUser::isAuthenticated()) {
    $currentUser = AdminUser::getCurrentUser();
    error_log(sprintf(
        'Admin logout: user_id=%d, osu_id=%d, username=%s',
        $currentUser->getUserId(),
        $currentUser->getOsuId(),
        $currentUser->getUsername()
    ));
}

// Destroy session and cleanup
AdminUser::destroySession();

// Redirect to login page with success message
header('Location: /admin/login.php?message=logged_out');
exit;
?>