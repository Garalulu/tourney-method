<?php
// Homepage content for Tournament Method - Load from database
use TourneyMethod\Models\Tournament;

// Initialize database connection - use absolute path from project root
$configPath = dirname(__DIR__, 3) . '/config/database.php';
if (!file_exists($configPath)) {
    // Fallback for different path structures
    $configPath = __DIR__ . '/../../../config/database.php';
}

try {
    require_once $configPath;
    $db = getDatabaseConnection();
} catch (Exception $e) {
    // Handle database connection error gracefully
    error_log("Database connection failed in homepage: " . $e->getMessage());
    $db = null;
    $tournaments = [];
    $stats = [
        'active_registrations' => 0,
        'approved_count' => 0,
        'estimated_participants' => 0,
        'completed_tournaments' => 0
    ];
}

// Initialize Tournament model and get data if database is available
if ($db !== null) {
    $tournamentModel = new Tournament($db);

    // Get search and filter parameters
    $searchQuery = $_GET['search'] ?? '';
    $gameMode = $_GET['mode'] ?? '';
    $status = $_GET['status'] ?? '';

    // Build filters array
    $filters = [];
    if (!empty($searchQuery)) {
        $filters['search'] = $searchQuery;
    }
    if (!empty($gameMode)) {
        $filters['game_mode'] = $gameMode;
    }
    if (!empty($status)) {
        $filters['registration_status'] = $status;
    }

    // Get tournaments from database
    try {
        $tournaments = $tournamentModel->getApprovedTournaments(20, 0, $filters);
        $stats = $tournamentModel->getPublicStatistics();
    } catch (Exception $e) {
        error_log("Failed to load tournament data: " . $e->getMessage());
        $tournaments = [];
        $stats = [
            'active_registrations' => 0,
            'approved_count' => 0,
            'estimated_participants' => 0,
            'completed_tournaments' => 0
        ];
    }
} else {
    // Database not available - use empty data
    $tournamentModel = null;
    $searchQuery = '';
    $gameMode = '';
    $status = '';
    $tournaments = [];
}
?>

<!-- Search Section -->
<section class="search-section" style="margin: -2rem -50vw 3rem -50vw; padding: 3rem 50vw; position: relative; left: 50%; right: 50%;">
    <div class="search-container container">
        <form method="GET" style="display: contents;">
            <div>
                <input type="text" class="search-input" name="search" 
                       placeholder="토너먼트 이름이나 호스트로 검색..." 
                       value="<?= SecurityHelper::escapeHtml($searchQuery) ?>" 
                       id="tournament-search">
            </div>
            <div>
                <select class="search-input" name="mode" onchange="this.form.submit()">
                    <option value="">모든 모드</option>
                    <option value="STD" <?= $gameMode === 'STD' ? 'selected' : '' ?>>osu! Standard</option>
                    <option value="TAIKO" <?= $gameMode === 'TAIKO' ? 'selected' : '' ?>>osu! Taiko</option>
                    <option value="CATCH" <?= $gameMode === 'CATCH' ? 'selected' : '' ?>>osu! Catch</option>
                    <option value="MANIA4" <?= $gameMode === 'MANIA4' ? 'selected' : '' ?>>osu! Mania 4K</option>
                    <option value="MANIA7" <?= $gameMode === 'MANIA7' ? 'selected' : '' ?>>osu! Mania 7K</option>
                    <option value="MANIA0" <?= $gameMode === 'MANIA0' ? 'selected' : '' ?>>osu! Mania</option>
                </select>
            </div>
            <div class="hide-on-small">
                <select class="search-input" name="status" onchange="this.form.submit()">
                    <option value="">모든 상태</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>참가 모집 중</option>
                    <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>개최 예정</option>
                    <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>진행 중</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>완료됨</option>
                </select>
            </div>
        </form>
    </div>
</section>

<!-- Stats Section -->
<section id="tournaments">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['active_registrations'] ?: 0 ?></div>
            <div class="stat-label korean-text">참가 모집 중</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['approved_count'] ?: 0 ?></div>
            <div class="stat-label korean-text">승인된 토너먼트</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['estimated_participants'] ?: 0) ?></div>
            <div class="stat-label korean-text">예상 참여자</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['completed_tournaments'] ?: 0 ?></div>
            <div class="stat-label korean-text">완료된 토너먼트</div>
        </div>
    </div>
</section>

<!-- Tournament Grid -->
<section class="tournament-grid">
    <?php if (empty($tournaments)): ?>
        <!-- Empty State -->
        <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;">
            <h3 class="korean-text">
                <?php if (!empty($searchQuery) || !empty($gameMode) || !empty($status)): ?>
                    검색 결과가 없습니다
                <?php else: ?>
                    아직 승인된 토너먼트가 없습니다
                <?php endif; ?>
            </h3>
            <p class="korean-text" style="margin-bottom: 2rem; color: var(--muted-color);">
                <?php if (!empty($searchQuery) || !empty($gameMode) || !empty($status)): ?>
                    다른 검색 조건을 시도해보세요.
                <?php else: ?>
                    새로운 토너먼트가 곧 추가될 예정입니다.
                <?php endif; ?>
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if (!empty($searchQuery) || !empty($gameMode) || !empty($status)): ?>
                    <a href="/" class="oauth-button">모든 토너먼트 보기</a>
                <?php endif; ?>
                <a href="https://osu.ppy.sh/community/forums/55" target="_blank" rel="noopener" class="oauth-button" style="background: var(--muted-color);">
                    osu! 토너먼트 포럼
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($tournaments as $tournament): 
            // Only format if tournament model is available
            if ($tournamentModel) {
                $displayStatus = $tournamentModel->getTournamentDisplayStatus($tournament);
                $gameMode = $tournamentModel->formatGameMode($tournament['game_mode']);
                $rankRange = $tournamentModel->formatRankRange($tournament);
                $teamInfo = $tournamentModel->formatTeamInfo($tournament);
            } else {
                // Fallback formatting if model is unavailable
                $displayStatus = ['class' => 'inactive', 'text' => '정보 없음'];
                $gameMode = $tournament['game_mode'] ?? 'osu! Standard';
                $rankRange = 'Open Rank';
                $teamInfo = '1v1';
            }
            
            // Format dates
            $regCloseDate = !empty($tournament['registration_close']) 
                ? date('Y-m-d', strtotime($tournament['registration_close']))
                : '정보 없음';
            $tournamentStartDate = !empty($tournament['tournament_start'])
                ? date('Y-m-d', strtotime($tournament['tournament_start']))
                : '정보 없음';
                
            // Create forum URL
            $forumUrl = 'https://osu.ppy.sh/community/forums/topics/' . $tournament['osu_topic_id'];
        ?>
        <div class="tournament-card" data-tournament-id="<?= $tournament['id'] ?>">
            <div class="tournament-title korean-text">
                <?= SecurityHelper::escapeHtml($tournament['title']) ?>
                <?php if ($tournament['has_badge']): ?>
                    <span style="color: var(--tournament-gold); margin-left: 0.5rem;" title="프로필 배지 지급">🏅</span>
                <?php endif; ?>
                <?php if ($tournament['is_bws']): ?>
                    <span style="color: var(--info-blue); margin-left: 0.5rem;" title="BWS 토너먼트">⚡</span>
                <?php endif; ?>
            </div>
            
            <div class="tournament-meta korean-text">
                <div><strong>게임 모드:</strong> <?= SecurityHelper::escapeHtml($gameMode) ?></div>
                <div><strong>랭크 제한:</strong> <?= SecurityHelper::escapeHtml($rankRange) ?></div>
                <div><strong>형식:</strong> <?= SecurityHelper::escapeHtml($teamInfo) ?></div>
                <?php if (!empty($tournament['host_name'])): ?>
                    <div><strong>호스트:</strong> <?= SecurityHelper::escapeHtml($tournament['host_name']) ?></div>
                <?php endif; ?>
                <div><strong>등록 마감:</strong> <?= SecurityHelper::escapeHtml($regCloseDate) ?></div>
                <?php if ($tournamentStartDate !== '정보 없음'): ?>
                    <div><strong>시작 일정:</strong> <?= SecurityHelper::escapeHtml($tournamentStartDate) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="tournament-status <?= $displayStatus['class'] ?>">
                <?= SecurityHelper::escapeHtml($displayStatus['text']) ?>
            </div>
            
            <!-- Action Links -->
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?= $forumUrl ?>" target="_blank" rel="noopener" 
                   class="oauth-button" style="font-size: 0.8rem; padding: 0.5rem 1rem;">
                    포럼 보기
                </a>
                <?php if (!empty($tournament['google_form_id'])): ?>
                    <a href="https://docs.google.com/forms/d/<?= SecurityHelper::escapeHtml($tournament['google_form_id']) ?>" 
                       target="_blank" rel="noopener"
                       class="oauth-button" style="font-size: 0.8rem; padding: 0.5rem 1rem; background: var(--success-green);">
                        등록하기
                    </a>
                <?php endif; ?>
                <?php if (!empty($tournament['discord_link'])): ?>
                    <a href="https://discord.gg/<?= SecurityHelper::escapeHtml($tournament['discord_link']) ?>" 
                       target="_blank" rel="noopener"
                       class="oauth-button" style="font-size: 0.8rem; padding: 0.5rem 1rem; background: #5865F2;">
                        Discord
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Call to Action -->
<section style="text-align: center; margin: 4rem 0; padding: 3rem; background: var(--card-background-color); border-radius: var(--border-radius);">
    <h2 class="korean-text">
        <?php if (empty($tournaments)): ?>
            더 많은 토너먼트를 기다리고 있습니다!
        <?php else: ?>
            토너먼트를 놓치지 마세요!
        <?php endif; ?>
    </h2>
    <p class="korean-text" style="margin-bottom: 2rem;">
        <?php if (empty($tournaments)): ?>
            osu! 포럼에서 새로운 토너먼트 정보를 확인하고,<br>
            관리자 승인을 통해 이 사이트에도 등록될 예정입니다.
        <?php else: ?>
            새로운 토너먼트가 자동으로 업데이트됩니다.<br>
            관심 있는 토너먼트를 클릭하여 자세한 정보를 확인하세요.
        <?php endif; ?>
    </p>
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="https://osu.ppy.sh/community/forums/55" target="_blank" rel="noopener" class="oauth-button">
            osu! 토너먼트 포럼 보기
        </a>
        <?php if (SecurityHelper::isCurrentUserAdmin()): ?>
            <a href="/admin/tournaments.php" class="oauth-button" style="background: var(--muted-color);">
                관리자 패널
            </a>
        <?php endif; ?>
    </div>
</section>

<script>
// Enhanced search functionality with form submission
document.getElementById('tournament-search').addEventListener('input', function(e) {
    // Debounce search to avoid too many requests
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(function() {
        document.querySelector('form').submit();
    }, 500);
});

// Add click tracking for tournament cards
document.querySelectorAll('.tournament-card').forEach(card => {
    card.addEventListener('click', function(e) {
        // Don't trigger if clicking on a link
        if (e.target.tagName.toLowerCase() === 'a') return;
        
        // Find the forum link and open it
        const forumLink = this.querySelector('a[href*="forums/topics"]');
        if (forumLink) {
            window.open(forumLink.href, '_blank');
        }
    });
});
</script>