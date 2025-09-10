<?php
use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;

// Ensure required variables are available
$currentStatus = $currentStatus ?? null;
$statistics = $statistics ?? [];
$recentRuns = $recentRuns ?? [];
$csrfToken = $csrfToken ?? '';

// Status colors
$statusColors = [
    'active' => 'var(--ins-color)',
    'paused' => 'orange',
    'running' => 'blue',
    'error' => 'var(--del-color)'
];

$statusLabels = [
    'active' => '활성',
    'paused' => '일시정지',
    'running' => '실행중',
    'error' => '오류'
];
?>

<div class="parser-management-container">
    <h1>파서 관리 인터페이스</h1>
    
    <!-- Parser Status Overview -->
    <div class="parser-status-overview" style="margin-bottom: 2rem;">
        <h2>파서 상태</h2>
        
        <?php if ($currentStatus): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <!-- Current Status -->
                <div style="padding: 1.5rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: <?= $statusColors[$currentStatus['status']] ?? '#666' ?>; margin-bottom: 0.5rem;">
                        <?= $statusLabels[$currentStatus['status']] ?? $currentStatus['status'] ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">현재 상태</div>
                    <div style="font-size: 0.8rem; color: var(--muted-color); margin-top: 0.5rem;">
                        <?= $currentStatus['is_enabled'] ? '✅ 활성화됨' : '⏸️ 비활성화됨' ?>
                    </div>
                </div>
                
                <!-- Last Run -->
                <div style="padding: 1.5rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?= $currentStatus['last_run'] ? DateHelper::formatKST($currentStatus['last_run']) : '없음' ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">마지막 실행</div>
                </div>
                
                <!-- Next Run -->
                <div style="padding: 1.5rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?= $currentStatus['next_run_calculated'] ? DateHelper::formatKST($currentStatus['next_run_calculated']) : '미정' ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">다음 예정 실행</div>
                </div>
                
                <!-- Schedule -->
                <div style="padding: 1.5rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem; font-family: monospace;">
                        <?= SecurityHelper::escapeHtml($currentStatus['schedule_interval']) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">실행 스케줄</div>
                </div>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: var(--muted-color);">
                파서 상태를 로드할 수 없습니다.
            </div>
        <?php endif; ?>
    </div>

    <!-- Parser Statistics -->
    <?php if (!empty($statistics)): ?>
        <div class="parser-statistics" style="margin-bottom: 2rem;">
            <h2>파서 통계</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--ins-color);">
                        <?= $statistics['total_runs'] ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">총 실행 횟수</div>
                </div>
                
                <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--ins-color);">
                        <?= $statistics['successful_runs'] ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">성공</div>
                </div>
                
                <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--del-color);">
                        <?= $statistics['failed_runs'] ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">실패</div>
                </div>
                
                <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold;">
                        <?= $statistics['success_rate'] ?>%
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">성공률</div>
                </div>
                
                <div style="padding: 1rem; background: var(--card-background-color); border-radius: var(--border-radius); text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold;">
                        <?= $statistics['avg_runs_per_day'] ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--muted-color);">일평균 실행</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Parser Controls -->
    <div class="parser-controls" style="margin-bottom: 2rem; padding: 1.5rem; background: var(--card-background-color); border-radius: var(--border-radius);">
        <h2>파서 제어</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <!-- Toggle Parser -->
            <div>
                <h4>파서 활성화/비활성화</h4>
                <form method="POST" style="margin-bottom: 1rem;">
                    <input type="hidden" name="action" value="toggle_parser">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <?php if ($currentStatus && $currentStatus['is_enabled']): ?>
                        <input type="hidden" name="enable" value="0">
                        <button type="submit" class="outline" onclick="return confirm('파서를 일시정지하시겠습니까?')">
                            ⏸️ 파서 일시정지
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="enable" value="1">
                        <button type="submit" onclick="return confirm('파서를 활성화하시겠습니까?')">
                            ▶️ 파서 활성화
                        </button>
                    <?php endif; ?>
                </form>
                <p style="font-size: 0.8rem; color: var(--muted-color);">
                    파서를 일시정지하면 자동 실행이 중단됩니다.
                </p>
            </div>
            
            <!-- Manual Run -->
            <div>
                <h4>수동 실행</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="run_parser">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <button type="submit" class="secondary" onclick="return confirm('파서를 지금 실행하시겠습니까?')">
                        🚀 지금 실행
                    </button>
                </form>
                <p style="font-size: 0.8rem; color: var(--muted-color);">
                    즉시 파서를 실행합니다. 결과는 로그에서 확인하세요.
                </p>
            </div>
            
            <!-- Schedule Update -->
            <div>
                <h4>실행 스케줄 변경</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="update_schedule">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="old_schedule" value="<?= SecurityHelper::escapeHtml($currentStatus['schedule_interval'] ?? '') ?>">
                    
                    <select name="schedule" style="margin-bottom: 0.5rem;">
                        <option value="0 2 * * *" <?= ($currentStatus['schedule_interval'] ?? '') === '0 2 * * *' ? 'selected' : '' ?>>
                            매일 오전 2시
                        </option>
                        <option value="0 */6 * * *" <?= ($currentStatus['schedule_interval'] ?? '') === '0 */6 * * *' ? 'selected' : '' ?>>
                            6시간마다
                        </option>
                        <option value="0 */12 * * *" <?= ($currentStatus['schedule_interval'] ?? '') === '0 */12 * * *' ? 'selected' : '' ?>>
                            12시간마다
                        </option>
                        <option value="0 0 * * 0" <?= ($currentStatus['schedule_interval'] ?? '') === '0 0 * * 0' ? 'selected' : '' ?>>
                            매주 일요일 자정
                        </option>
                    </select>
                    
                    <button type="submit" class="outline">스케줄 업데이트</button>
                </form>
                <p style="font-size: 0.8rem; color: var(--muted-color);">
                    파서 자동 실행 일정을 변경합니다.
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Parser Activity -->
    <div class="recent-activity">
        <h2>최근 파서 활동 로그</h2>
        
        <?php if (!empty($recentRuns)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 140px;">시간 (KST)</th>
                            <th style="width: 80px;">레벨</th>
                            <th>메시지</th>
                            <th style="width: 60px;">상세</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.9rem;">
                                    <?= DateHelper::formatKST($run['created_at']) ?>
                                </td>
                                <td>
                                    <span style="padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;
                                                 background: <?= $run['level'] === 'ERROR' ? 'var(--del-color)' : 
                                                              ($run['level'] === 'WARNING' ? 'orange' : 'var(--ins-color)') ?>; 
                                                 color: white;">
                                        <?= SecurityHelper::escapeHtml($run['level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= SecurityHelper::escapeHtml($run['message']) ?>
                                </td>
                                <td>
                                    <?php if ($run['context']): ?>
                                        <button onclick="showRunDetails(<?= SecurityHelper::escapeHtml(json_encode($run)) ?>)" 
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
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="/admin/logs.php?source=parser" role="button" class="secondary">
                    모든 파서 로그 보기
                </a>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--muted-color);">
                <p>최근 파서 활동이 없습니다.</p>
                <p><small>파서를 실행하면 활동 로그가 여기에 표시됩니다.</small></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Run Details Modal -->
<div id="runModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--card-background-color); padding: 2rem; border-radius: var(--border-radius); max-width: 80%; max-height: 80%; overflow-y: auto;">
        <h3>파서 실행 상세 정보</h3>
        <div id="runModalContent"></div>
        <button onclick="hideRunDetails()" class="secondary">닫기</button>
    </div>
</div>

<script>
function showRunDetails(run) {
    const modal = document.getElementById('runModal');
    const content = document.getElementById('runModalContent');
    
    let contextHtml = '';
    if (run.context) {
        try {
            const context = typeof run.context === 'string' ? JSON.parse(run.context) : run.context;
            contextHtml = '<pre style="background: var(--code-background-color); padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem;">' + 
                         JSON.stringify(context, null, 2) + '</pre>';
        } catch (e) {
            contextHtml = '<p>Context 파싱 오류: ' + run.context + '</p>';
        }
    }
    
    content.innerHTML = `
        <p><strong>시간:</strong> ${run.created_at}</p>
        <p><strong>레벨:</strong> ${run.level}</p>
        <p><strong>메시지:</strong></p>
        <div style="background: var(--code-background-color); padding: 1rem; border-radius: 4px; white-space: pre-wrap;">${run.message}</div>
        ${contextHtml ? '<p><strong>Context:</strong></p>' + contextHtml : ''}
    `;
    
    modal.style.display = 'block';
}

function hideRunDetails() {
    document.getElementById('runModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('runModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideRunDetails();
    }
});

// Auto-refresh page every 30 seconds if parser is running
<?php if ($currentStatus && $currentStatus['status'] === 'running'): ?>
setTimeout(function() {
    window.location.reload();
}, 30000);
<?php endif; ?>
</script>