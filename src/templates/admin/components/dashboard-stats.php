<?php
/**
 * Dashboard Statistics Component
 * Displays key metrics in card format
 */
?>

<section class="stats-section" aria-labelledby="stats-title">
    <h2 id="stats-title" class="sr-only">통계 개요</h2>
    <div class="stats-grid">
        <div class="stat-card" role="img" aria-label="총 토너먼트 수">
            <div class="stat-icon" aria-hidden="true">🏆</div>
            <h3 class="stat-title">총 토너먼트</h3>
            <div class="stat-number" id="total-tournaments"><?= $totalTournaments ?></div>
            <p class="stat-description">전체 등록된 토너먼트</p>
        </div>
        
        <div class="stat-card" role="img" aria-label="검토 대기 토너먼트 수">
            <div class="stat-icon" aria-hidden="true">⏳</div>
            <h3 class="stat-title">검토 대기</h3>
            <div class="stat-number" id="pending-count"><?= $pendingCount ?></div>
            <p class="stat-description">승인 대기 중</p>
        </div>
        
        <div class="stat-card" role="img" aria-label="이번 달 승인된 토너먼트 수">
            <div class="stat-icon" aria-hidden="true">✅</div>
            <h3 class="stat-title">이번 달 승인</h3>
            <div class="stat-number" id="monthly-approved"><?= $monthlyApproved ?></div>
            <p class="stat-description">승인된 토너먼트</p>
        </div>
        
        <div class="stat-card" role="img" aria-label="평균 스타 레이팅">
            <div class="stat-icon" aria-hidden="true">📈</div>
            <h3 class="stat-title">평균 SR</h3>
            <div class="stat-number" id="avg-sr"><?= number_format($avgStarRating, 1) ?></div>
            <p class="stat-description">그랜드 파이널 SR</p>
        </div>
    </div>
</section>