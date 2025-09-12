<?php
/**
 * Tournament Discovery Page Template
 * Displays tournament listing with filters and modal functionality
 */

use TourneyMethod\Utils\SecurityHelper;

// Ensure this template is not accessed directly
if (!defined('MAIN_TEMPLATE')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
?>

<!-- Include tournament-specific CSS and JS -->
<link rel="stylesheet" href="/assets/css/tournaments.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<div class="tournaments-layout">
    
    <!-- Filter Panel -->
    <aside class="filter-panel" role="complementary" aria-label="Tournament Filters">
        <h3>필터 <span class="active-filter-count"></span></h3>
        
        <!-- Mobile Filter Toggle -->
        <button id="mobile-filter-toggle" class="mobile-filter-toggle" aria-expanded="false">
            필터 보기/숨기기
        </button>
        
        <!-- Search Filter -->
        <div class="filter-group">
            <label for="tournament-search">토너먼트 검색</label>
            <input 
                type="search" 
                id="tournament-search" 
                placeholder="토너먼트 제목이나 주최자 검색..."
                autocomplete="off"
            >
        </div>
        
        <!-- Rank Range Filter -->
        <div class="filter-group">
            <label for="rank-range-filter">랭크 범위</label>
            <select id="rank-range-filter">
                <option value="All">모든 랭크</option>
                <option value="Open">오픈 랭크</option>
                <option value="100+">100위 이하</option>
                <option value="500+">500위 이하</option>
                <option value="1k+">1,000위 이하</option>
                <option value="5k+">5,000위 이하</option>
                <option value="10k+">10,000위 이하</option>
            </select>
        </div>
        
        <!-- Registration Status Filter -->
        <div class="filter-group">
            <label>참가 모집 상태</label>
            <div class="radio-group">
                <div class="radio-item">
                    <input type="radio" name="registration-status" value="All" id="status-all" checked>
                    <label for="status-all">모든 상태</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="registration-status" value="Open" id="status-open">
                    <label for="status-open">모집 중</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="registration-status" value="Closed" id="status-closed">
                    <label for="status-closed">모집 마감</label>
                </div>
                <div class="radio-item">
                    <input type="radio" name="registration-status" value="Ongoing" id="status-ongoing">
                    <label for="status-ongoing">진행 중</label>
                </div>
            </div>
        </div>
        
        <!-- Game Mode Filter -->
        <div class="filter-group">
            <label>게임 모드</label>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" name="game-mode" value="Standard" id="mode-standard">
                    <label for="mode-standard">osu! Standard</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="game-mode" value="Taiko" id="mode-taiko">
                    <label for="mode-taiko">osu! Taiko</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="game-mode" value="Catch" id="mode-catch">
                    <label for="mode-catch">osu! Catch</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="game-mode" value="Mania" id="mode-mania">
                    <label for="mode-mania">osu! Mania</label>
                </div>
            </div>
        </div>
        
        <!-- Clear Filters Button -->
        <button id="clear-filters" class="clear-filters-btn secondary">
            모든 필터 초기화
        </button>
        
    </aside>
    
    <!-- Main Tournament Content -->
    <main class="tournaments-content" role="main">
        
        <!-- Tournament Statistics -->
        <?php if (isset($tournamentStats) && is_array($tournamentStats)): ?>
        <section class="tournament-stats" aria-label="Tournament Statistics">
            <h2>토너먼트 현황</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div style="text-align: center; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--primary);">
                        <?= $tournamentStats['approved_count'] ?? 0 ?>
                    </div>
                    <div style="color: var(--muted-color);">승인된 토너먼트</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                        <?= $tournamentStats['active_registrations'] ?? 0 ?>
                    </div>
                    <div style="color: var(--muted-color);">모집 중인 토너먼트</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
                    <div style="font-size: 2rem; font-weight: bold; color: #ffc107;">
                        <?= $tournamentStats['upcoming_tournaments'] ?? 0 ?>
                    </div>
                    <div style="color: var(--muted-color);">개최 예정</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--muted-color);">
                        <?= number_format($tournamentStats['estimated_participants'] ?? 0) ?>
                    </div>
                    <div style="color: var(--muted-color);">예상 참가자</div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Tournament Grid -->
        <section class="tournament-listing" aria-label="Tournament List">
            <h2>모든 토너먼트</h2>
            
            <div class="tournament-grid" role="grid" aria-label="Tournament cards">
                <?php if (!empty($tournaments) && is_array($tournaments)): ?>
                    <?php foreach ($tournaments as $tournament): ?>
                        <?php
                        // Initialize tournament model for helper methods
                        if (!isset($tournamentModel)) {
                            $pdo = new PDO("sqlite:" . __DIR__ . "/../../../data/tournament_method.db");
                            $tournamentModel = new TourneyMethod\Models\Tournament($pdo);
                        }
                        
                        $displayStatus = $tournamentModel->getTournamentDisplayStatus($tournament);
                        $gameMode = $tournamentModel->formatGameMode($tournament['game_mode'] ?? null);
                        $rankRange = $tournamentModel->formatRankRange($tournament);
                        $teamInfo = $tournamentModel->formatTeamInfo($tournament);
                        $forumUrl = 'https://osu.ppy.sh/community/' . ($tournament['forum_url_slug'] ?? 'forums/topics/' . $tournament['osu_topic_id']);
                        ?>
                        
                        <div class="tournament-card" 
                             data-tournament-id="<?= $tournament['id'] ?>"
                             role="button"
                             tabindex="0"
                             aria-label="<?= SecurityHelper::escapeHtml($tournament['title']) ?> tournament details">
                            
                            <!-- Tournament Banner -->
                            <?php if (!empty($tournament['banner_url'])): ?>
                                <img data-src="<?= SecurityHelper::escapeHtml($tournament['banner_url']) ?>" 
                                     alt="<?= SecurityHelper::escapeHtml($tournament['title']) ?> 배너" 
                                     class="tournament-banner lazy" 
                                     loading="lazy">
                            <?php else: ?>
                                <div class="tournament-banner-placeholder" aria-hidden="true">🏆</div>
                            <?php endif; ?>
                            
                            <!-- Tournament Content -->
                            <div class="tournament-content">
                                <h3 class="tournament-title">
                                    <a href="<?= $forumUrl ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       aria-label="<?= SecurityHelper::escapeHtml($tournament['title']) ?> 포럼 게시글 보기 (새 창)">
                                        <?= SecurityHelper::escapeHtml($tournament['title']) ?>
                                    </a>
                                </h3>
                                
                                <div class="tournament-meta">
                                    <span class="tournament-host">
                                        주최: <?= SecurityHelper::escapeHtml($tournament['host_name'] ?? 'Unknown Host') ?>
                                    </span>
                                    <span class="tournament-mode"><?= $gameMode ?></span>
                                </div>
                                
                                <div class="tournament-details">
                                    <span class="rank-range">랭크: <?= $rankRange ?></span>
                                    <span class="team-info"><?= $teamInfo ?></span>
                                </div>
                                
                                <div class="tournament-status">
                                    <span class="status-badge status-<?= $displayStatus['class'] ?>">
                                        <?= $displayStatus['text'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>토너먼트를 찾을 수 없습니다</h3>
                        <p>현재 승인된 토너먼트가 없거나 필터 조건에 맞는 토너먼트가 없습니다.</p>
                        <a href="/admin/" class="button">관리자 페이지</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div class="pagination-container" role="navigation" aria-label="Tournament pagination">
                <?php if (!empty($tournaments) && count($tournaments) >= 10): ?>
                    <button class="show-more-btn" data-limit="25" aria-label="Show 25 more tournaments">
                        25개 더 보기
                    </button>
                <?php endif; ?>
            </div>
        </section>
        
    </main>
    
</div>

<!-- Tournament Detail Modal -->
<div class="tournament-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-overlay" aria-label="Close modal"></div>
    <div class="modal-content">
        <header class="modal-header">
            <h2 id="modal-title" class="modal-title"></h2>
            <button class="modal-close" aria-label="Close modal">✕</button>
        </header>
        <div class="modal-body">
            <div class="modal-banner"></div>
            <div class="modal-meta">
                <div class="modal-host"></div>
                <div class="modal-mode"></div>
                <div class="modal-rank-range"></div>
                <div class="modal-status"></div>
            </div>
            <div class="modal-links" role="navigation" aria-label="Tournament links"></div>
        </div>
    </div>
</div>

<!-- Include Tournament JavaScript -->
<script src="/assets/js/tournaments.js"></script>

<!-- Add tournament-specific enhancements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Keyboard navigation for tournament cards
    document.querySelectorAll('.tournament-card[tabindex="0"]').forEach(card => {
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
    });
    
    // Announce filter changes to screen readers
    const announcer = document.createElement('div');
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    announcer.className = 'sr-only';
    document.body.appendChild(announcer);
    
    // Store reference for tournament manager
    window.tournamentAnnouncer = announcer;
});
</script>