<?php
// OAuth 2.0 Callback Handler for osu! Authentication

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables for development
TourneyMethod\Utils\EnvLoader::load();

use TourneyMethod\Services\AuthService;
use TourneyMethod\Models\AdminUser;
use TourneyMethod\Utils\SecurityHelper;

// Initialize security configuration
SecurityHelper::configureSecureSession();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for production
error_reporting(E_ERROR | E_WARNING | E_PARSE);

/**
 * Handle OAuth callback with comprehensive error handling and logging
 */
function handleOAuthCallback(): void
{
    $authService = new AuthService();
    
    try {
        // Validate required OAuth callback parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            throw new \RuntimeException('Missing required OAuth parameters (code or state)');
        }
        
        // Validate and sanitize input parameters
        $authorizationCode = SecurityHelper::validateString($_GET['code'], 2000);
        $state = SecurityHelper::validateString($_GET['state'], 64);
        
        // Check for OAuth error response
        if (isset($_GET['error'])) {
            $error = SecurityHelper::validateString($_GET['error'], 100);
            throw new \RuntimeException('OAuth authorization failed: ' . $error);
        }
        
        // Complete OAuth authentication flow
        $userInfo = $authService->authenticateUser($authorizationCode, $state);
        
        // Create and validate admin user
        $adminUser = AdminUser::authorizeAdmin($userInfo);
        
        // Create secure session
        $sessionId = $adminUser->createSession();
        
        // Log successful authentication (without sensitive data)
        error_log(sprintf(
            'Admin login successful: user_id=%d, osu_id=%d, session_id=%s',
            $adminUser->getUserId(),
            $adminUser->getOsuId(),
            substr($sessionId, 0, 8) . '...'
        ));
        
        // Redirect to admin dashboard
        header('Location: /admin/');
        exit;
        
    } catch (\RuntimeException $e) {
        // Log authentication error (without exposing internal details)
        error_log('Admin authentication failed: ' . $e->getMessage());
        
        // Destroy any partial session data
        if (isset($_SESSION)) {
            session_destroy();
        }
        
        // Redirect to login with error message
        $errorType = 'auth_failed';
        if (strpos($e->getMessage(), 'not authorized') !== false) {
            $errorType = 'not_authorized';
        } elseif (strpos($e->getMessage(), 'CSRF') !== false) {
            $errorType = 'csrf_error';
        } elseif (strpos($e->getMessage(), 'OAuth') !== false) {
            $errorType = 'oauth_error';
        }
        
        header('Location: /admin/login.php?error=' . urlencode($errorType));
        exit;
        
    } catch (\InvalidArgumentException $e) {
        // Log validation error
        error_log('OAuth callback validation failed: ' . $e->getMessage());
        
        // Clean up session
        if (isset($_SESSION)) {
            session_destroy();
        }
        
        header('Location: /admin/login.php?error=invalid_request');
        exit;
        
    } catch (\Exception $e) {
        // Log unexpected error
        error_log('OAuth callback unexpected error: ' . $e->getMessage());
        
        // Clean up session
        if (isset($_SESSION)) {
            session_destroy();
        }
        
        header('Location: /admin/login.php?error=server_error');
        exit;
    }
}

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleOAuthCallback();
        break;
        
    case 'POST':
        // POST method not supported for OAuth callback
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'Method not allowed']);
        exit;
        
    default:
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'Method not allowed']);
        exit;
}
?>