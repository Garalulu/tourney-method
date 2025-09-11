<?php
// Admin Parser Management Interface - Monitor and control parser activity

require_once __DIR__ . '/../../vendor/autoload.php';

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DatabaseHelper;
use TourneyMethod\Models\ParserStatus;
use TourneyMethod\Models\SystemLog;
use TourneyMethod\Utils\DateHelper;

// Initialize security and require admin authentication
SecurityHelper::configureSecureSession();
SecurityHelper::requireAdminAuth();

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Initialize database connection
$db = DatabaseHelper::getInstance();

// Create models
$parserStatus = new ParserStatus($db->getConnection());
$systemLog = new SystemLog($db->getConnection());

// Handle control actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    SecurityHelper::validateCsrfToken($_POST['csrf_token'] ?? '', $_SESSION['csrf_token'] ?? '');
    
    try {
        switch ($_POST['action']) {
            case 'toggle_parser':
                $enabled = isset($_POST['enable']) && $_POST['enable'] === '1';
                $success = $parserStatus->setEnabled($enabled);
                
                if ($success) {
                    $statusText = $enabled ? 'activated' : 'paused';
                    $systemLog->log('INFO', "Parser {$statusText} by admin", 'admin', 
                                  ['enabled' => $enabled], $currentUser->getUserId());
                    $successMessage = $enabled ? "파서가 활성화되었습니다." : "파서가 일시 정지되었습니다.";
                } else {
                    $errorMessage = "파서 상태 변경에 실패했습니다.";
                }
                break;
                
            case 'update_schedule':
                $schedule = $_POST['schedule'] ?? '0 2 * * *';
                $success = $parserStatus->updateSchedule($schedule);
                
                if ($success) {
                    $systemLog->log('INFO', "Parser schedule updated to: {$schedule}", 'admin',
                                  ['old_schedule' => $_POST['old_schedule'] ?? '', 'new_schedule' => $schedule], 
                                  $currentUser->getUserId());
                    $successMessage = "파서 스케줄이 업데이트되었습니다.";
                } else {
                    $errorMessage = "스케줄 업데이트에 실패했습니다.";
                }
                break;
                
            case 'run_parser':
                // Record parser run start
                $parserStatus->recordRunStart();
                
                // Execute parser script in background
                $projectRoot = __DIR__ . '/../../';
                $parserScript = $projectRoot . 'scripts/parser/basic_parser.php';
                $logFile = $projectRoot . 'logs/parser_manual.log';
                
                // Change to project root directory and run parser
                if (PHP_OS_FAMILY === 'Windows') {
                    $command = "start /B cmd /C \"cd /D \"{$projectRoot}\" && php \"{$parserScript}\" > \"{$logFile}\" 2>&1\"";
                } else {
                    $command = "cd '{$projectRoot}' && php '{$parserScript}' > '{$logFile}' 2>&1 &";
                }
                
                exec($command);
                
                $userId = $currentUser->getUserId() ?: null;
                $systemLog->log('INFO', "Manual parser run triggered by admin", 'admin',
                              ['command' => $command], $userId);
                $successMessage = "파서가 수동으로 실행되었습니다. 결과는 잠시 후 로그에서 확인할 수 있습니다.";
                break;
                
            default:
                $errorMessage = "알 수 없는 액션입니다.";
        }
    } catch (Exception $e) {
        $userId = $currentUser->getUserId() ?: null;
        $systemLog->log('ERROR', "Parser management action failed: " . $e->getMessage(), 'admin',
                      ['action' => $_POST['action'], 'error' => $e->getMessage()], $userId);
        $errorMessage = "작업 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// Get parser status and statistics
$currentStatus = $parserStatus->getCurrentStatus();
$statistics = $parserStatus->getStatistics();
$recentRuns = $parserStatus->getRecentRuns(15);

// Generate CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// Page configuration for template
define('ADMIN_TEMPLATE', true);
$pageTitle = '파서 관리';
$contentTemplate = __DIR__ . '/../../src/templates/admin/parser-management.php';

// Include admin layout template
include __DIR__ . '/../../src/templates/layouts/admin.php';
?>