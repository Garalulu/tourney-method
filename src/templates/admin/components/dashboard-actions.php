<?php
/**
 * Dashboard Quick Actions Component
 * Navigation links for common admin tasks
 */
?>

<section class="actions-section" aria-labelledby="actions-title">
    <h2 id="actions-title" class="section-title">빠른 작업</h2>
    <div class="actions-grid">
        <a href="/admin/tournaments.php" class="action-card" aria-describedby="action-tournaments-desc">
            <div class="action-icon" aria-hidden="true">🎮</div>
            <h3 class="action-title">토너먼트 관리</h3>
            <p id="action-tournaments-desc" class="action-description">토너먼트 승인 및 관리</p>
        </a>
        
        <a href="/admin/parser.php" class="action-card" aria-describedby="action-parser-desc">
            <div class="action-icon" aria-hidden="true">⚙️</div>
            <h3 class="action-title">파서 실행</h3>
            <p id="action-parser-desc" class="action-description">새 토너먼트 파싱</p>
        </a>
        
        <a href="/admin/analytics.php" class="action-card" aria-describedby="action-analytics-desc">
            <div class="action-icon" aria-hidden="true">📊</div>
            <h3 class="action-title">통계 보기</h3>
            <p id="action-analytics-desc" class="action-description">상세 분석 데이터</p>
        </a>
        
        <a href="/admin/logs.php" class="action-card" aria-describedby="action-logs-desc">
            <div class="action-icon" aria-hidden="true">📝</div>
            <h3 class="action-title">로그 확인</h3>
            <p id="action-logs-desc" class="action-description">시스템 로그 조회</p>
        </a>
    </div>
</section>