<?php
// Admin Tournament Edit Template

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;

$tournament = $GLOBALS['tournament'];
?>

<div class="admin-edit-tournament">
    <header>
        <nav>
            <a href="/admin/index.php">← 대시보드로 돌아가기</a>
        </nav>
        <h1>토너먼트 편집</h1>
        <p>토너먼트 정보를 검토하고 승인 상태를 변경할 수 있습니다.</p>
    </header>
    
    <!-- Tournament Information Display -->
    <section>
        <h2>토너먼트 정보</h2>
        <div class="grid">
            <article>
                <header><strong>기본 정보</strong></header>
                <dl>
                    <dt>제목:</dt>
                    <dd><?= SecurityHelper::escapeHtml($tournament['title']) ?></dd>
                    
                    <dt>상태:</dt>
                    <dd><mark><?= SecurityHelper::escapeHtml($tournament['status']) ?></mark></dd>
                    
                    <dt>파싱 날짜:</dt>
                    <dd><?= DateHelper::formatToKST($tournament['parsed_at'] ?? $tournament['created_at'] ?? '') ?></dd>
                </dl>
            </article>
            
            <article>
                <header><strong>토너먼트 세부사항</strong></header>
                <dl>
                    <dt>osu! 토픽 ID:</dt>
                    <dd><?= (int)$tournament['osu_topic_id'] ?></dd>
                    
                    <dt>랭크 범위:</dt>
                    <dd>
                        <?php if (isset($tournament['rank_range_min']) && isset($tournament['rank_range_max'])): ?>
                            #<?= (int)$tournament['rank_range_min'] ?> - #<?= (int)$tournament['rank_range_max'] ?>
                        <?php else: ?>
                            <em>설정되지 않음</em>
                        <?php endif; ?>
                    </dd>
                </dl>
            </article>
        </div>
    </section>
    
    <!-- Status Change Actions -->
    <section>
        <h2>승인 작업</h2>
        <div class="grid">
            <article>
                <header><strong>토너먼트 승인</strong></header>
                <p>이 토너먼트가 모든 요구사항을 충족하는 경우 승인하십시오.</p>
                <footer>
                    <button type="button" class="approve-tournament" data-id="<?= (int)$tournament['id'] ?>">승인</button>
                </footer>
            </article>
            
            <article>
                <header><strong>토너먼트 거부</strong></header>
                <p>이 토너먼트가 요구사항을 충족하지 않는 경우 거부하십시오.</p>
                <footer>
                    <button type="button" class="secondary reject-tournament" data-id="<?= (int)$tournament['id'] ?>">거부</button>
                </footer>
            </article>
            
            <article>
                <header><strong>추가 검토 필요</strong></header>
                <p>더 많은 정보가 필요한 경우 보류 상태로 유지하십시오.</p>
                <footer>
                    <button type="button" class="outline keep-pending" data-id="<?= (int)$tournament['id'] ?>">보류 유지</button>
                </footer>
            </article>
        </div>
    </section>
    
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
.admin-edit-tournament dl {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 0.5rem;
    margin: 0;
}

.admin-edit-tournament dt {
    font-weight: bold;
    color: var(--muted-color);
}

.admin-edit-tournament dd {
    margin: 0;
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
</style>

<script>
// Tournament status change handlers would be implemented here
// For Story 1.5, we only need the display functionality
document.addEventListener('DOMContentLoaded', function() {
    // Placeholder for future functionality
    console.log('Tournament edit page loaded for ID: <?= (int)$tournament['id'] ?>');
});
</script>