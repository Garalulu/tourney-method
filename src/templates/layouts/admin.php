<?php
use TourneyMethod\Utils\SecurityHelper;

// Ensure this template is not accessed directly
if (!defined('ADMIN_TEMPLATE')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SecurityHelper::escapeHtml($pageTitle ?? 'Admin') ?> - Tourney Method Admin</title>
    
    <!-- Pico.css Framework for consistent styling -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1.5.7/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    
    <!-- Korean font optimization -->
    <style>
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', '맑은 고딕', sans-serif;
            word-break: keep-all;
        }
        
        .admin-header {
            background: var(--primary);
            color: var(--primary-inverse);
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-nav h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .login-container {
            max-width: 400px;
            margin: 4rem auto;
            padding: 2rem;
            background: var(--card-background-color);
            border-radius: var(--border-radius);
            box-shadow: var(--card-box-shadow);
        }
        
        .oauth-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #ff66aa;
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: background-color 0.2s;
        }
        
        .oauth-button:hover {
            background: #e055aa;
            color: white;
            text-decoration: none;
        }
        
        .oauth-button::before {
            content: "🎵";
            font-size: 1.2em;
        }
        
        .error-message {
            background: var(--del-color);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .success-message {
            background: var(--ins-color);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .footer {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid var(--muted-border-color);
            text-align: center;
            color: var(--muted-color);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php if (SecurityHelper::isCurrentUserAdmin()): ?>
        <!-- Admin Header with Navigation -->
        <header class="admin-header">
            <nav class="admin-nav">
                <h1>🏆 Tourney Method Admin</h1>
                <div class="admin-user-info">
                    <span>안녕하세요, <?= SecurityHelper::escapeHtml(SecurityHelper::getCurrentAdminUser()->getUsername()) ?>님</span>
                    <a href="/admin/logout.php" class="secondary" role="button">로그아웃</a>
                </div>
            </nav>
        </header>
        
        <!-- Admin Navigation Menu -->
        <nav class="container">
            <ul>
                <li><a href="/admin/">대시보드</a></li>
                <li><a href="/admin/tournaments.php">토너먼트 관리</a></li>
                <li><a href="/admin/parser.php">파서 관리</a></li>
                <li><a href="/admin/logs.php">로그</a></li>
            </ul>
        </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="container">
        <?php if (isset($errorMessage)): ?>
            <div class="error-message" role="alert">
                <?= SecurityHelper::escapeHtml($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($successMessage)): ?>
            <div class="success-message" role="alert">
                <?= SecurityHelper::escapeHtml($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Include the page content
        if (isset($contentTemplate) && file_exists($contentTemplate)) {
            include $contentTemplate;
        } else {
            echo '<p>콘텐츠를 찾을 수 없습니다.</p>';
        }
        ?>
    </main>
    
    <!-- Footer -->
    <footer class="footer container">
        <p>&copy; 2025 Tourney Method</p>
        <p><small>관리자 전용 인터페이스 | KST <?= date('Y-m-d H:i:s') ?></small></p>
    </footer>
    
    <!-- CSRF Token for AJAX requests -->
    <?php if (SecurityHelper::isCurrentUserAdmin()): ?>
        <script>
            window.csrfToken = '<?= SecurityHelper::escapeHtml(SecurityHelper::getCurrentAdminUser()->getCsrfToken()) ?>';
        </script>
    <?php endif; ?>
</body>
</html>