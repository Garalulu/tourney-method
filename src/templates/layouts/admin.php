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
    <link rel="stylesheet" href="/assets/css/admin-theme.css">
    
    <!-- Korean font optimization -->
    <style>
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', '맑은 고딕', sans-serif;
            word-break: keep-all;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0066cc 100%);
            color: var(--primary-inverse);
            padding: 1rem 0;
            margin-bottom: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 2rem;
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
        <!-- Mobile Header -->
        <div class="mobile-header">
            <h2>🏆 Tourney Method</h2>
            <button class="mobile-toggle" onclick="$('.admin-sidebar').removeClass('collapsed'); $('.mobile-overlay').addClass('active'); $('body').addClass('sidebar-open');">
                <span class="hamburger"></span>
            </button>
        </div>
        
        <!-- Gaming Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h1 class="neon-text">🏆 Tourney Method</h1>
                <div class="admin-user-info">
                    <img class="user-avatar" 
                         src="https://a.ppy.sh/<?= SecurityHelper::getCurrentAdminUser()->getOsuId() ?>" 
                         alt="<?= SecurityHelper::escapeHtml(SecurityHelper::getCurrentAdminUser()->getUsername()) ?>" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="user-avatar" style="display: none;">👤</div>
                    <div class="user-actions">
                        <!-- Theme toggle will be inserted here by JavaScript -->
                        <a href="/admin/logout.php" class="btn-gaming" title="로그아웃">🚪</a>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="/admin/" class="<?= ($_SERVER['REQUEST_URI'] === '/admin/' || $_SERVER['REQUEST_URI'] === '/admin/index.php') ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">대시보드</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/tournaments.php" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/tournaments.php') !== false) ? 'active' : '' ?>">
                            <span class="nav-icon">🎮</span>
                            <span class="nav-text">토너먼트 관리</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/parser.php" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/parser.php') !== false) ? 'active' : '' ?>">
                            <span class="nav-icon">⚙️</span>
                            <span class="nav-text">파서 관리</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/logs.php" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/logs.php') !== false) ? 'active' : '' ?>">
                            <span class="nav-icon">📝</span>
                            <span class="nav-text">로그</span>
                        </a>
                    </li>
                    <li class="nav-section">통계</li>
                    <li>
                        <a href="/admin/analytics.php" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/analytics.php') !== false) ? 'active' : '' ?>">
                            <span class="nav-icon">📈</span>
                            <span class="nav-text">분석</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <button class="sidebar-toggle" title="사이드바 토글">
                    <span class="hamburger"></span>
                </button>
            </div>
        </aside>
        
        <!-- Mobile Menu Overlay -->
        <div class="mobile-overlay" onclick="$('body').removeClass('sidebar-open'); $(this).removeClass('active');"></div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content">
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
            echo '<div class="admin-card"><p>콘텐츠를 찾을 수 없습니다.</p></div>';
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
    
    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Admin Theme & Interactions -->
    <script src="/assets/js/admin-theme.js"></script>
</body>
</html>