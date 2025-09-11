<?php
use TourneyMethod\Utils\SecurityHelper;

// Ensure this template is not accessed directly
if (!defined('MAIN_TEMPLATE')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SecurityHelper::escapeHtml($pageTitle ?? 'Tournament Discovery') ?> - Tourney Method</title>
    <meta name="description" content="Korean osu! tournament discovery platform - find and join tournaments easily">
    
    <!-- Pico.css Framework for consistent styling -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1.5.7/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    
    <!-- Preconnect to external domains for performance -->
    <link rel="preconnect" href="https://osu.ppy.sh">
    <link rel="dns-prefetch" href="https://osu.ppy.sh">
</head>
<body>
    <!-- Skip link for accessibility -->
    <a class="skip-link" href="#main-content">메인 콘텐츠로 건너뛰기</a>
    
    <!-- Main Navigation -->
    <nav class="main-nav">
        <div class="nav-container container">
            <a href="/" class="nav-brand">
                🏆 Tourney Method
            </a>
            <div class="nav-links">
                <a href="/">홈</a>
                <a href="/tournaments">토너먼트</a>
                <a href="/about">소개</a>
                <?php if (SecurityHelper::isCurrentUserAdmin()): ?>
                    <a href="/admin/">관리자</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section (if on homepage) -->
    <?php if (isset($showHero) && $showHero): ?>
        <section class="hero">
            <div class="hero-content container">
                <h1 class="korean-text">한국 osu! 토너먼트를 쉽게 찾아보세요</h1>
                <p class="korean-text">osu! 포럼의 모든 토너먼트 정보를 한 곳에서 확인하고 참가하세요</p>
                <div style="margin-top: 2rem;">
                    <a href="#tournaments" class="oauth-button" style="background: var(--tournament-gold); color: #000;">
                        토너먼트 보기
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="<?= isset($containerClass) ? $containerClass : 'container' ?>" style="margin-top: 2rem;">
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
            echo '<div class="empty-state">';
            echo '<h3>콘텐츠를 찾을 수 없습니다</h3>';
            echo '<p>요청하신 페이지의 내용을 불러올 수 없습니다.</p>';
            echo '</div>';
        }
        ?>
    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-content container">
            <div>
                <h4>🏆 Tourney Method</h4>
                <p class="korean-text">한국 osu! 커뮤니티를 위한<br>토너먼트 발견 플랫폼</p>
            </div>
            <div>
                <h5>링크</h5>
                <ul style="list-style: none; padding: 0;">
                    <li><a href="https://osu.ppy.sh" target="_blank" rel="noopener">osu! 공식 사이트</a></li>
                    <li><a href="https://osu.ppy.sh/community/forums/55" target="_blank" rel="noopener">토너먼트 포럼</a></li>
                </ul>
            </div>
            <div>
                <h5>정보</h5>
                <p><small>
                    &copy; 2025 Tourney Method<br>
                    <time datetime="<?= date('c') ?>">KST <?= date('Y-m-d H:i:s') ?></time>
                </small></p>
            </div>
        </div>
    </footer>
    
    <!-- Progressive Enhancement Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Add loading states to tournament cards
            const cards = document.querySelectorAll('.tournament-card');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    if (this.dataset.loading !== 'true') {
                        this.dataset.loading = 'true';
                        const originalContent = this.innerHTML;
                        this.innerHTML = originalContent + '<div class="loading-spinner" style="margin-top: 1rem;"></div>';
                    }
                });
            });
            
            // Lazy load images if any
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        });
    </script>
</body>
</html>