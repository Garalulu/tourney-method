<?php
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;

// Ensure required variables are available
$tournaments = $tournaments ?? [];
$statistics = $statistics ?? [];
$filters = $filters ?? [];
$csrfToken = $csrfToken ?? '';

// Available statuses
$availableStatuses = [
    'pending_review' => ['label' => '검토 대기', 'color' => 'orange'],
    'approved' => ['label' => '승인됨', 'color' => 'var(--ins-color)'],
    'rejected' => ['label' => '거부됨', 'color' => 'var(--del-color)'],
    'cancelled' => ['label' => '취소됨', 'color' => 'gray'],
    'archived' => ['label' => '보관됨', 'color' => 'var(--muted-color)']
];
?>

<div class="tournament-management-container">
    <h1>토너먼트 관리 대시보드</h1>
    
    <!-- Tournament Statistics Summary -->
    <div class="tournament-statistics" style="margin-bottom: 2rem;">
        <h2>토너먼트 현황</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <?php if (!empty($statistics)): ?>
                <?php foreach ($statistics as $stat): ?>
                    <?php 
                    $statusInfo = $availableStatuses[$stat['status']] ?? ['label' => $stat['status'], 'color' => '#666'];
                    ?>
                    <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: <?= $statusInfo['color'] ?>;">
                            <?= $stat['count'] ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--muted-color);">
                            <?= $statusInfo['label'] ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--muted-color);">
                            최근: <?= DateHelper::formatKST($stat['latest_parsed']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--muted-color); text-align: center;">통계 데이터가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls" style="margin-bottom: 2rem; padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius);">
        <h3>토너먼트 필터</h3>
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
            <div>
                <label for="status">상태 필터:</label>
                <select name="status" id="status">
                    <option value="">전체 상태</option>
                    <?php foreach ($availableStatuses as $statusKey => $statusData): ?>
                        <option value="<?= $statusKey ?>" <?= ($filters['status'] ?? '') === $statusKey ? 'selected' : '' ?>>
                            <?= SecurityHelper::escapeHtml($statusData['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="search">검색 (제목/내용):</label>
                <input type="text" name="search" id="search" value="<?= SecurityHelper::escapeHtml($filters['search'] ?? '') ?>" 
                       placeholder="토너먼트 제목이나 내용 검색...">
            </div>
            
            <div>
                <button type="submit">필터 적용</button>
                <a href="/admin/tournaments.php" role="button" class="secondary">초기화</a>
            </div>
        </form>
    </div>

    <!-- Tournaments Table -->
    <div class="tournaments-table">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3>토너먼트 목록 (총 <?= count($tournaments) ?>개)</h3>
            <a href="/admin/" role="button" class="secondary">대시보드로 돌아가기</a>
        </div>
        
        <?php if (!empty($tournaments)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 40px;">ID</th>
                            <th>토너먼트 제목</th>
                            <th style="width: 120px;">상태</th>
                            <th style="width: 120px;">랭크 범위</th>
                            <th style="width: 140px;">파싱 날짜</th>
                            <th style="width: 200px;">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tournaments as $tournament): ?>
                            <tr>
                                <td style="font-family: monospace;">
                                    <?= $tournament['id'] ?>
                                </td>
                                <td>
                                    <strong><?= SecurityHelper::escapeHtml($tournament['title']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--muted-color);">
                                        Topic ID: <?= $tournament['osu_topic_id'] ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $statusInfo = $availableStatuses[$tournament['status']] ?? ['label' => $tournament['status'], 'color' => '#666'];
                                    ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; 
                                                 background: <?= $statusInfo['color'] ?>; color: white;">
                                        <?= $statusInfo['label'] ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.9rem;">
                                    <?php if ($tournament['rank_range_min'] && $tournament['rank_range_max']): ?>
                                        <?= number_format($tournament['rank_range_min']) ?> - <?= number_format($tournament['rank_range_max']) ?>
                                    <?php else: ?>
                                        <span style="color: var(--muted-color);">미설정</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: monospace; font-size: 0.85rem;">
                                    <?= DateHelper::formatKST($tournament['parsed_at']) ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <!-- Edit Button -->
                                        <a href="/admin/edit.php?id=<?= $tournament['id'] ?>&csrf_token=<?= $csrfToken ?>" 
                                           role="button" class="secondary" style="padding: 4px 8px; font-size: 0.8rem;">
                                            편집
                                        </a>
                                        
                                        <!-- Status Change Button -->
                                        <button onclick="showStatusChangeModal(<?= SecurityHelper::escapeHtml(json_encode($tournament)) ?>)" 
                                                class="outline" style="padding: 4px 8px; font-size: 0.8rem;">
                                            상태 변경
                                        </button>
                                    </div>
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
                $hasMore = count($tournaments) === $limit; // If we got full page, there might be more
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
                <p>현재 조건에 맞는 토너먼트가 없습니다.</p>
                <p><small>파서가 실행되거나 새 토너먼트가 추가되면 여기에 표시됩니다.</small></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Change Modal -->
<div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--card-background-color); padding: 2rem; border-radius: var(--border-radius); min-width: 400px;">
        <h3>토너먼트 상태 변경</h3>
        <form id="statusChangeForm" method="POST">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="tournament_id" id="modal_tournament_id">
            <input type="hidden" name="old_status" id="modal_old_status">
            
            <div id="modalTournamentInfo" style="margin-bottom: 1rem; padding: 1rem; background: var(--code-background-color); border-radius: 4px;">
                <!-- Tournament info will be populated by JavaScript -->
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label for="new_status">새 상태:</label>
                <select name="new_status" id="new_status" required>
                    <?php foreach ($availableStatuses as $statusKey => $statusData): ?>
                        <option value="<?= $statusKey ?>"><?= SecurityHelper::escapeHtml($statusData['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label for="reason">변경 사유 (선택사항):</label>
                <textarea name="reason" id="reason" rows="3" placeholder="상태 변경 사유를 입력하세요..."></textarea>
            </div>
            
            <div style="text-align: right;">
                <button type="button" onclick="hideStatusModal()" class="secondary">취소</button>
                <button type="submit">상태 변경</button>
            </div>
        </form>
    </div>
</div>

<script>
function showStatusChangeModal(tournament) {
    const modal = document.getElementById('statusModal');
    const form = document.getElementById('statusChangeForm');
    const info = document.getElementById('modalTournamentInfo');
    
    // Populate form fields
    document.getElementById('modal_tournament_id').value = tournament.id;
    document.getElementById('modal_old_status').value = tournament.status;
    document.getElementById('new_status').value = tournament.status;
    
    // Show tournament info
    info.innerHTML = `
        <strong>토너먼트:</strong> ${tournament.title}<br>
        <strong>현재 상태:</strong> ${tournament.status}<br>
        <strong>Topic ID:</strong> ${tournament.osu_topic_id}
    `;
    
    modal.style.display = 'block';
}

function hideStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.getElementById('statusChangeForm').reset();
}

// Close modal when clicking outside
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideStatusModal();
    }
});

// Form validation
document.getElementById('statusChangeForm').addEventListener('submit', function(e) {
    const oldStatus = document.getElementById('modal_old_status').value;
    const newStatus = document.getElementById('new_status').value;
    
    if (oldStatus === newStatus) {
        e.preventDefault();
        alert('새로운 상태를 선택해주세요.');
        return false;
    }
    
    if (!confirm('정말로 토너먼트 상태를 변경하시겠습니까?')) {
        e.preventDefault();
        return false;
    }
});
</script>