<?php
// Admin Dashboard Template

use TourneyMethod\Utils\SecurityHelper;

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();
?>

<div class="admin-dashboard">
    <header>
        <h1>관리자 대시보드</h1>
        <p>Tourney Method 관리 인터페이스에 오신 것을 환영합니다.</p>
    </header>
    
    <!-- Admin User Information -->
    <section>
        <h2>현재 사용자 정보</h2>
        <div class="grid">
            <article>
                <header><strong>osu! 사용자</strong></header>
                <p><?= SecurityHelper::escapeHtml($currentUser->getUsername()) ?></p>
                <footer><small>osu! ID: <?= $currentUser->getOsuId() ?></small></footer>
            </article>
            <article>
                <header><strong>세션 정보</strong></header>
                <p>관리자 권한 활성화</p>
                <footer><small>세션 시작: <?= date('Y-m-d H:i:s') ?></small></footer>
            </article>
        </div>
    </section>
    
    <!-- Quick Actions -->
    <section>
        <h2>빠른 작업</h2>
        <div class="grid">
            <article>
                <header><strong>토너먼트 관리</strong></header>
                <p>토너먼트를 승인, 편집 또는 삭제할 수 있습니다.</p>
                <footer>
                    <a href="/admin/tournaments.php" role="button">토너먼트 관리</a>
                </footer>
            </article>
            <article>
                <header><strong>파서 관리</strong></header>
                <p>forum post 파서를 실행하고 결과를 확인할 수 있습니다.</p>
                <footer>
                    <a href="/admin/parser.php" role="button" class="secondary">파서 실행</a>
                </footer>
            </article>
            <article>
                <header><strong>시스템 로그</strong></header>
                <p>시스템 로그와 오류를 확인할 수 있습니다.</p>
                <footer>
                    <a href="/admin/logs.php" role="button" class="outline">로그 보기</a>
                </footer>
            </article>
        </div>
    </section>
    
    <!-- System Status -->
    <section>
        <h2>시스템 상태</h2>
        <div class="grid">
            <div>
                <h3>보안 상태</h3>
                <ul>
                    <li>✅ OAuth 2.0 인증 활성화</li>
                    <li>✅ CSRF 보호 적용</li>
                    <li>✅ 세션 보안 설정</li>
                    <li>✅ 관리자 권한 확인됨</li>
                </ul>
            </div>
            <div>
                <h3>시스템 정보</h3>
                <ul>
                    <li><strong>PHP 버전:</strong> <?= PHP_VERSION ?></li>
                    <li><strong>현재 시간:</strong> <?= date('Y-m-d H:i:s T') ?></li>
                    <li><strong>타임존:</strong> <?= date_default_timezone_get() ?></li>
                    <li><strong>세션 ID:</strong> <?= substr(session_id(), 0, 12) ?>...</li>
                </ul>
            </div>
        </div>
    </section>
    
    <!-- Development Info -->
    <details>
        <summary>개발 정보</summary>
        <div>
            <h4>Story 1.2 구현 상태</h4>
            <ul>
                <li>✅ OAuth 2.0 인증 구현</li>
                <li>✅ 관리자 권한 검증</li>
                <li>✅ 안전한 세션 관리</li>
                <li>✅ CSRF 보호</li>
                <li>✅ 한국어 인터페이스</li>
            </ul>
            
            <h4>보안 기능</h4>
            <ul>
                <li>OAuth state parameter 검증</li>
                <li>하드코딩된 관리자 목록</li>
                <li>세션 타임아웃 (1시간)</li>
                <li>안전한 쿠키 설정</li>
                <li>입력 검증 및 출력 이스케이프</li>
            </ul>
        </div>
    </details>
</div>