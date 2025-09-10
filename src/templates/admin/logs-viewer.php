<?php
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;
use TourneyMethod\Models\SystemLog;

// Ensure required variables are available
$logs = $logs ?? [];
$statistics = $statistics ?? [];
$recentErrors = $recentErrors ?? [];
$filters = $filters ?? [];
?>

<div class="logs-container">
    <h1>시스템 로그 뷰어</h1>
    
    <!-- Log Statistics Summary -->
    <div class="log-statistics" style="margin-bottom: 2rem;">
        <h2>지난 7일 로그 통계</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <?php if (!empty($statistics)): ?>
                <?php foreach ($statistics as $stat): ?>
                    <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: <?= $stat['level'] === 'ERROR' ? 'var(--del-color)' : ($stat['level'] === 'WARNING' ? 'orange' : 'var(--ins-color)') ?>;">
                            <?= $stat['count'] ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--muted-color);">
                            <?= SecurityHelper::escapeHtml($stat['level']) ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--muted-color);">
                            최근: <?= DateHelper::formatKST($stat['latest']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--muted-color); text-align: center;">지난 7일간 로그가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Parser Errors Alert -->
    <?php if (!empty($recentErrors)): ?>
        <div style="background: var(--del-color); color: white; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1rem 0;">⚠️ 최근 파서 오류 (<?= count($recentErrors) ?>개)</h3>
            <?php foreach (array_slice($recentErrors, 0, 3) as $error): ?>
                <div style="margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 4px;">
                    <strong><?= DateHelper::formatKST($error['created_at']) ?></strong>: 
                    <?= SecurityHelper::escapeHtml(mb_substr($error['message'], 0, 100)) ?><?= mb_strlen($error['message']) > 100 ? '...' : '' ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($recentErrors) > 3): ?>
                <div style="font-size: 0.9rem; opacity: 0.8;">그 외 <?= count($recentErrors) - 3 ?>개의 오류가 더 있습니다.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Controls -->
    <div class="filter-controls" style="margin-bottom: 2rem; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
        <h3>로그 필터</h3>
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div>
                <label for="level">로그 레벨:</label>
                <select name="level" id="level">
                    <option value="">전체 레벨</option>
                    <?php foreach (SystemLog::getAvailableLevels() as $level): ?>
                        <option value="<?= $level ?>" <?= ($filters['level'] ?? '') === $level ? 'selected' : '' ?>>
                            <?= SecurityHelper::escapeHtml($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="source">소스 컴포넌트:</label>
                <select name="source" id="source">
                    <option value="">전체 소스</option>
                    <option value="parser" <?= ($filters['source'] ?? '') === 'parser' ? 'selected' : '' ?>>파서</option>
                    <option value="auth" <?= ($filters['source'] ?? '') === 'auth' ? 'selected' : '' ?>>인증</option>
                    <option value="admin" <?= ($filters['source'] ?? '') === 'admin' ? 'selected' : '' ?>>관리자</option>
                    <option value="api" <?= ($filters['source'] ?? '') === 'api' ? 'selected' : '' ?>>API</option>
                </select>
            </div>
            
            <div>
                <button type="submit">필터 적용</button>
                <a href="/admin/logs.php" role="button" class="secondary">초기화</a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="logs-table">
        <h3>시스템 로그 (최신순)</h3>
        
        <?php if (!empty($logs)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 140px;">시간 (KST)</th>
                            <th style="width: 80px;">레벨</th>
                            <th style="width: 100px;">소스</th>
                            <th>메시지</th>
                            <th style="width: 60px;">상세</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.9rem;">
                                    <?= DateHelper::formatKST($log['created_at']) ?>
                                </td>
                                <td>
                                    <span class="log-level log-level-<?= strtolower($log['level']) ?>" 
                                          style="padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; 
                                                 background: <?= $log['level'] === 'ERROR' || $log['level'] === 'CRITICAL' ? 'var(--del-color)' : 
                                                              ($log['level'] === 'WARNING' ? 'orange' : 
                                                              ($log['level'] === 'INFO' || $log['level'] === 'NOTICE' ? 'var(--ins-color)' : '#666')) ?>; 
                                                 color: white;">
                                        <?= SecurityHelper::escapeHtml($log['level']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.9rem;">
                                    <?= $log['source'] ? SecurityHelper::escapeHtml($log['source']) : '-' ?>
                                </td>
                                <td>
                                    <?= SecurityHelper::escapeHtml($log['message']) ?>
                                </td>
                                <td>
                                    <?php if ($log['context']): ?>
                                        <button onclick="showLogDetails(<?= SecurityHelper::escapeHtml(json_encode($log)) ?>)" 
                                                class="secondary" style="padding: 4px 8px; font-size: 0.8rem;">
                                            상세
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination" style="margin-top: 2rem; text-align: center;">
                <?php 
                $currentPage = $page ?? 1;
                $hasMore = count($logs) === $limit; // If we got full page, there might be more
                ?>
                
                <?php if ($currentPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" 
                       role="button" class="secondary">← 이전</a>
                <?php endif; ?>
                
                <span style="margin: 0 1rem;">페이지 <?= $currentPage ?></span>
                
                <?php if ($hasMore): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" 
                       role="button" class="secondary">다음 →</a>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--muted-color);">
                <p>현재 조건에 맞는 로그가 없습니다.</p>
                <p><small>파서가 실행되거나 시스템 이벤트가 발생하면 로그가 여기에 표시됩니다.</small></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--card-background-color); padding: 2rem; border-radius: var(--border-radius); max-width: 80%; max-height: 80%; overflow-y: auto;">
        <h3>로그 상세 정보</h3>
        <div id="logModalContent"></div>
        <button onclick="hideLogDetails()" class="secondary">닫기</button>
    </div>
</div>

<script>
function showLogDetails(log) {
    const modal = document.getElementById('logModal');
    const content = document.getElementById('logModalContent');
    
    let contextHtml = '';
    if (log.context) {
        try {
            const context = typeof log.context === 'string' ? JSON.parse(log.context) : log.context;
            contextHtml = '<pre style="background: var(--code-background-color); padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;">' + 
                         JSON.stringify(context, null, 2) + '</pre>';
        } catch (e) {
            contextHtml = '<p>Context 파싱 오류: ' + log.context + '</p>';
        }
    }
    
    content.innerHTML = `
        <p><strong>시간:</strong> ${log.created_at}</p>
        <p><strong>레벨:</strong> ${log.level}</p>
        <p><strong>소스:</strong> ${log.source || '없음'}</p>
        <p><strong>메시지:</strong></p>
        <div style="background: var(--code-background-color); padding: 1rem; border-radius: 4px; white-space: pre-wrap;">${log.message}</div>
        ${contextHtml ? '<p><strong>Context:</strong></p>' + contextHtml : ''}
    `;
    
    modal.style.display = 'block';
}

function hideLogDetails() {
    document.getElementById('logModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLogDetails();
    }
});
</script>