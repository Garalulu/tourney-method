<?php
// Admin Login Page - osu! OAuth 2.0 Authentication

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables for development
TourneyMethod\Utils\EnvLoader::load();

use TourneyMethod\Services\AuthService;
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Models\AdminUser;

// Initialize security configuration
SecurityHelper::configureSecureSession();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already authenticated
if (AdminUser::isAuthenticated()) {
    header('Location: /admin/');
    exit;
}

// Handle OAuth flow initiation, error messages, and success messages
$errorMessage = null;
$successMessage = null;
$authService = new AuthService();

// Handle success messages
if (isset($_GET['message'])) {
    $messageType = SecurityHelper::validateString($_GET['message'], 50);
    switch ($messageType) {
        case 'logged_out':
            $successMessage = '성공적으로 로그아웃되었습니다.';
            break;
    }
}

// Handle error messages from OAuth callback
if (isset($_GET['error'])) {
    $errorType = SecurityHelper::validateString($_GET['error'], 50);
    switch ($errorType) {
        case 'auth_failed':
            $errorMessage = '인증에 실패했습니다. 다시 시도해주세요.';
            break;
        case 'not_authorized':
            $errorMessage = '관리자 권한이 없습니다. 승인된 관리자만 접근할 수 있습니다.';
            break;
        case 'csrf_error':
            $errorMessage = '보안 오류가 발생했습니다. 페이지를 새로고침하고 다시 시도해주세요.';
            break;
        case 'oauth_error':
            $errorMessage = 'osu! 인증 서비스에 문제가 발생했습니다. 잠시 후 다시 시도해주세요.';
            break;
        case 'invalid_request':
            $errorMessage = '잘못된 요청입니다. 다시 시도해주세요.';
            break;
        case 'server_error':
            $errorMessage = '서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
            break;
        default:
            $errorMessage = '알 수 없는 오류가 발생했습니다.';
    }
}

if (isset($_GET['login']) && $_GET['login'] === 'osu') {
    try {
        $authUrl = $authService->generateAuthorizationUrl();
        header('Location: ' . $authUrl);
        exit;
    } catch (\Exception $e) {
        error_log('OAuth authorization URL generation failed: ' . $e->getMessage());
        $errorMessage = '로그인 요청을 처리하는 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
    }
}

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '관리자 로그인';
$contentTemplate = __DIR__ . '/../../src/templates/admin/login-form.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>