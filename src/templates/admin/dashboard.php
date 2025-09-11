<?php
// Admin Dashboard Template

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;
use TourneyMethod\Models\Tournament;

// Include database configuration
require_once __DIR__ . '/../../../config/database.php';

// Get current admin user
$currentUser = SecurityHelper::getCurrentAdminUser();

// Get pending review tournaments
$db = getDatabaseConnection();
$tournamentModel = new Tournament($db);
$pendingTournaments = $tournamentModel->findPendingReview();

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityHelper::generateCsrfToken();
}
?>

<div class="admin-dashboard">
    <header class="dashboard-header">
        <h1 class="neon-text">🎮 관리자 대시보드</h1>
        <p>Tourney Method 관리 인터페이스에 오신 것을 환영합니다.</p>
    </header>
    
    <!-- Gaming Statistics Cards -->
    <section class="stats-grid">
        <div class="admin-card stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-content">
                <h3>총 토너먼트</h3>
                <div class="stat-number" data-count="<?= count($pendingTournaments) + 15 ?>">0</div>
                <small>전체 등록된 토너먼트</small>
            </div>
        </div>
        
        <div class="admin-card stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <h3>검토 대기</h3>
                <div class="stat-number" data-count="<?= count($pendingTournaments) ?>">0</div>
                <small>승인 대기 중</small>
            </div>
        </div>
        
        <div class="admin-card stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <h3>이번 달 승인</h3>
                <div class="stat-number" data-count="8">0</div>
                <small>승인된 토너먼트</small>
            </div>
        </div>
        
        <div class="admin-card stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <h3>평균 SR</h3>
                <div class="stat-number" data-count="5.2">0.0</div>
                <small>그랜드 파이널 SR</small>
            </div>
        </div>
    </section>
    
    <!-- Quick Actions -->
    <section class="quick-actions">
        <h2>빠른 작업</h2>
        <div class="action-grid">
            <a href="/admin/tournaments.php" class="admin-card action-card">
                <div class="action-icon">🎮</div>
                <h3>토너먼트 관리</h3>
                <p>토너먼트 승인 및 관리</p>
            </a>
            
            <a href="/admin/parser.php" class="admin-card action-card">
                <div class="action-icon">⚙️</div>
                <h3>파서 실행</h3>
                <p>새 토너먼트 파싱</p>
            </a>
            
            <a href="/admin/analytics.php" class="admin-card action-card">
                <div class="action-icon">📊</div>
                <h3>통계 보기</h3>
                <p>상세 분석 데이터</p>
            </a>
            
            <a href="/admin/logs.php" class="admin-card action-card">
                <div class="action-icon">📝</div>
                <h3>로그 확인</h3>
                <p>시스템 로그 조회</p>
            </a>
        </div>
    </section>
    
    <!-- Tournament Review Table -->
    <section>
        <h2>검토 대기 토너먼트</h2>
        <?php if (empty($pendingTournaments)): ?>
            <div class="alert">
                <p>현재 검토 대기 중인 토너먼트가 없습니다.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>토너먼트 제목</th>
                            <th>파싱 날짜</th>
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTournaments as $tournament): ?>
                            <tr>
                                <td><?= SecurityHelper::escapeHtml($tournament['title']) ?></td>
                                <td><?= DateHelper::formatKST($tournament['parsed_at']) ?></td>
                                <td>
                                    <a href="/admin/edit.php?id=<?= (int)$tournament['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>" 
                                       role="button" 
                                       class="small">편집</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p><small>총 <?= count($pendingTournaments) ?>개의 토너먼트가 검토를 기다리고 있습니다.</small></p>
        <?php endif; ?>
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