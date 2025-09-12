<?php
// Admin Tournament Edit Template

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;

$tournament = $GLOBALS['tournament'];
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>

<div class="admin-edit-tournament">
    <header>
        <nav>
            <a href="/admin/index.php">← 대시보드로 돌아가기</a>
        </nav>
        <h1>토너먼트 편집</h1>
        <p>토너먼트 정보를 검토하고 수정할 수 있습니다. 빈 필드는 강조 표시됩니다.</p>
    </header>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <article class="success-message">
            <p>✅ <?= SecurityHelper::escapeHtml($_SESSION['success_message']) ?></p>
        </article>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <article class="error-message">
            <p>❌ <?= SecurityHelper::escapeHtml($_SESSION['error_message']) ?></p>
        </article>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Tournament Edit Form -->
    <form method="POST" action="/admin/edit.php?id=<?= (int)$tournament['id'] ?>" class="tournament-edit-form">
        <input type="hidden" name="csrf_token" value="<?= SecurityHelper::escapeHtml($csrfToken) ?>">
        
        <section>
            <h2>기본 정보</h2>
            <div class="grid">
                <div class="form-group">
                    <label for="title">토너먼트 제목 <mark>*</mark></label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="<?= SecurityHelper::escapeHtml($tournament['title'] ?? '') ?>"
                           <?= empty($tournament['title']) ? 'class="field-null"' : '' ?>
                           required>
                    <?php if (empty($tournament['title'])): ?>
                        <small class="field-missing">이 필드는 필수입니다</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="rank_range">랭크 범위</label>
                    <?php 
                        $rankRangeDisplay = '';
                        if ($tournament['rank_range_min'] && $tournament['rank_range_max']) {
                            $rankRangeDisplay = '#' . number_format($tournament['rank_range_min']) . ' - #' . number_format($tournament['rank_range_max']);
                        } elseif ($tournament['rank_range_min']) {
                            $rankRangeDisplay = '#' . number_format($tournament['rank_range_min']) . '+';
                        }
                    ?>
                    <input type="text" 
                           id="rank_range" 
                           name="rank_range" 
                           value="<?= SecurityHelper::escapeHtml($rankRangeDisplay) ?>"
                           placeholder="예: #1,000 - #10,000"
                           <?= empty($rankRangeDisplay) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($rankRangeDisplay)): ?>
                        <small class="field-missing">랭크 범위가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section>
            <h2>토너먼트 설정</h2>
            <div class="grid">
                <div class="form-group">
                    <label for="team_size">팀 크기</label>
                    <input type="number" 
                           id="team_size" 
                           name="team_size" 
                           value="<?= (int)($tournament['team_size'] ?? 0) ?>"
                           min="1"
                           <?= empty($tournament['team_size']) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($tournament['team_size'])): ?>
                        <small class="field-missing">팀 크기가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="max_teams">최대 팀 수</label>
                    <input type="number" 
                           id="max_teams" 
                           name="max_teams" 
                           value="<?= (int)($tournament['max_teams'] ?? 0) ?>"
                           min="1"
                           <?= empty($tournament['max_teams']) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($tournament['max_teams'])): ?>
                        <small class="field-missing">최대 팀 수가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section>
            <h2>날짜 정보</h2>
            <div class="grid">
                <div class="form-group">
                    <label for="start_date">토너먼트 시작</label>
                    <?php 
                        $startDateValue = '';
                        if ($tournament['tournament_start']) {
                            $startDate = \DateTime::createFromFormat('Y-m-d H:i:s', $tournament['tournament_start']);
                            if ($startDate) {
                                $startDateValue = $startDate->format('Y-m-d\TH:i');
                            }
                        }
                    ?>
                    <input type="datetime-local" 
                           id="start_date" 
                           name="start_date" 
                           value="<?= SecurityHelper::escapeHtml($startDateValue) ?>"
                           <?= empty($startDateValue) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($startDateValue)): ?>
                        <small class="field-missing">시작 날짜가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="registration_close">등록 마감</label>
                    <?php 
                        $regCloseValue = '';
                        if ($tournament['registration_close']) {
                            $regClose = \DateTime::createFromFormat('Y-m-d H:i:s', $tournament['registration_close']);
                            if ($regClose) {
                                $regCloseValue = $regClose->format('Y-m-d\TH:i');
                            }
                        }
                    ?>
                    <input type="datetime-local" 
                           id="registration_close" 
                           name="registration_close" 
                           value="<?= SecurityHelper::escapeHtml($regCloseValue) ?>"
                           <?= empty($regCloseValue) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($regCloseValue)): ?>
                        <small class="field-missing">등록 마감이 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section>
            <h2>링크 정보</h2>
            <div class="grid">
                <div class="form-group">
                    <label for="google_sheet_id">Google 스프레드시트 ID</label>
                    <input type="text" 
                           id="google_sheet_id" 
                           name="google_sheet_id" 
                           value="<?= SecurityHelper::escapeHtml($tournament['google_sheet_id'] ?? '') ?>"
                           placeholder="또는 전체 URL을 sheet_link에 입력"
                           <?= empty($tournament['google_sheet_id']) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($tournament['google_sheet_id'])): ?>
                        <small class="field-missing">스프레드시트 ID가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="sheet_link">스프레드시트 링크</label>
                    <input type="url" 
                           id="sheet_link" 
                           name="sheet_link" 
                           value="<?= $tournament['google_sheet_id'] ? 'https://docs.google.com/spreadsheets/d/' . SecurityHelper::escapeHtml($tournament['google_sheet_id']) : '' ?>"
                           placeholder="https://docs.google.com/spreadsheets/d/..."
                           <?= empty($tournament['google_sheet_id']) ? 'class="field-null"' : '' ?>>
                    <?php if (empty($tournament['google_sheet_id'])): ?>
                        <small class="field-missing">스프레드시트 링크가 감지되지 않았습니다</small>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section>
            <h2>토너먼트 상태</h2>
            <div class="grid">
                <div class="form-group">
                    <label>현재 상태</label>
                    <p><mark><?= SecurityHelper::escapeHtml($tournament['status']) ?></mark></p>
                    <small>파싱 날짜: <?= DateHelper::formatKST($tournament['parsed_at'] ?? '') ?></small>
                </div>
                
                <div class="form-group">
                    <label>osu! 토픽 ID</label>
                    <p><?= (int)$tournament['osu_topic_id'] ?></p>
                    <small><a href="https://osu.ppy.sh/community/forums/topics/<?= (int)$tournament['osu_topic_id'] ?>" target="_blank">포럼에서 보기</a></small>
                </div>
            </div>
        </section>
        
        <!-- Form Actions -->
        <section class="form-actions">
            <div class="grid">
                <button type="submit" name="action" value="update" class="secondary">토너먼트 수정</button>
                <button type="submit" name="action" value="approve" class="approve-btn">승인 후 공개</button>
            </div>
        </section>
    </form>
    
    <!-- Raw Content Preview -->
    <?php if (isset($tournament['raw_post_content']) && !empty($tournament['raw_post_content'])): ?>
    <details>
        <summary>원본 포럼 게시물 내용</summary>
        <div class="raw-content">
            <pre><?= SecurityHelper::escapeHtml($tournament['raw_post_content']) ?></pre>
        </div>
    </details>
    <?php endif; ?>
</div>

<style>
.admin-edit-tournament .form-group {
    margin-bottom: 1rem;
}

.admin-edit-tournament .success-message {
    background-color: var(--color-green, #28a745);
    color: white;
    border: none;
    margin-bottom: 1rem;
}

.admin-edit-tournament .error-message {
    background-color: var(--color-red, #dc3545);
    color: white;
    border: none;
    margin-bottom: 1rem;
}

.admin-edit-tournament .field-null {
    border-color: var(--color-amber);
    background-color: var(--background-color);
}

.admin-edit-tournament .field-missing {
    color: var(--color-amber);
    font-style: italic;
    margin-top: 0.25rem;
    display: block;
}

.admin-edit-tournament .form-actions {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.admin-edit-tournament .approve-btn {
    background-color: var(--color-green);
    border-color: var(--color-green);
}

.admin-edit-tournament .approve-btn:hover {
    background-color: var(--color-green-dark, #28a745);
    border-color: var(--color-green-dark, #28a745);
}

.raw-content {
    background-color: var(--background-color);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1rem;
    margin-top: 1rem;
}

.raw-content pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

@media (max-width: 768px) {
    .admin-edit-tournament .form-actions .grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.tournament-edit-form');
    const approveBtn = document.querySelector('.approve-btn');
    
    // Form validation for Korean text
    const titleInput = document.getElementById('title');
    const hostInput = document.getElementById('host_name');
    
    function validateKoreanText(input) {
        const value = input.value.trim();
        if (value && !/^[\u3131-\u3163\uac00-\ud7a3\w\s\-\[\]().,!@#$%^&*+={}:;"'<>?\/|\\`~]*$/u.test(value)) {
            input.setCustomValidity('한글, 영문, 숫자 및 일반 기호만 입력 가능합니다.');
        } else {
            input.setCustomValidity('');
        }
    }
    
    titleInput?.addEventListener('input', function() { validateKoreanText(this); });
    hostInput?.addEventListener('input', function() { validateKoreanText(this); });
    
    // Confirm approval action
    approveBtn?.addEventListener('click', function(e) {
        if (!confirm('이 토너먼트를 승인하고 공개하시겠습니까? 승인 후에는 공개 목록에 표시됩니다.')) {
            e.preventDefault();
        }
    });
    
    // Auto-save draft functionality (optional enhancement)
    let saveTimeout;
    form?.addEventListener('input', function() {
        clearTimeout(saveTimeout);
        // Could implement auto-save to localStorage here if needed
    });
    
});
</script>