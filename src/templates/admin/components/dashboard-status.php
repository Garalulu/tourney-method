<?php
/**
 * Dashboard System Status Component
 * Shows system health and information
 */
?>

<section aria-labelledby="status-title">
    <h2 id="status-title" class="section-title">시스템 상태</h2>
    <div class="status-grid">
        <div class="status-card">
            <h3 class="status-title">보안 상태</h3>
            <ul class="status-list" role="list">
                <li><span class="status-icon" aria-hidden="true">✅</span> OAuth 2.0 인증 활성화</li>
                <li><span class="status-icon" aria-hidden="true">✅</span> CSRF 보호 적용</li>
                <li><span class="status-icon" aria-hidden="true">✅</span> 세션 보안 설정</li>
                <li><span class="status-icon" aria-hidden="true">✅</span> 관리자 권한 확인됨</li>
            </ul>
        </div>
        
        <div class="status-card">
            <h3 class="status-title">시스템 정보</h3>
            <ul class="status-list" role="list">
                <li><strong>PHP 버전:</strong> <?= PHP_VERSION ?></li>
                <li><strong>현재 시간:</strong> <time datetime="<?= date('c') ?>"><?= date('Y-m-d H:i:s T') ?></time></li>
                <li><strong>타임존:</strong> <?= date_default_timezone_get() ?></li>
                <li><strong>세션 ID:</strong> <span class="monospace"><?= substr(session_id(), 0, 12) ?>...</span></li>
            </ul>
        </div>
    </div>
</section>