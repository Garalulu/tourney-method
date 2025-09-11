<?php
// Admin Analytics Template
use TourneyMethod\Utils\SecurityHelper;
?>

<div class="analytics-dashboard">
    <header class="dashboard-header">
        <h1 class="neon-text">📊 통계 분석</h1>
        <p>토너먼트 데이터 분석 및 트렌드 확인</p>
    </header>
    
    <!-- Chart Navigation -->
    <nav class="chart-nav">
        <button class="btn-gaming active" data-chart="monthly">월별 현황</button>
        <button class="btn-gaming" data-chart="sr-distribution">SR 분포</button>
        <button class="btn-gaming" data-chart="status">상태별 분포</button>
        <button class="btn-gaming" data-chart="peak-hours">피크 시간</button>
    </nav>
    
    <!-- Chart Container -->
    <section class="chart-section">
        <div class="admin-card chart-card">
            <div class="chart-header">
                <h2 id="chart-title">월별 토너먼트 현황</h2>
                <div class="chart-controls">
                    <button class="btn-gaming btn-sm" onclick="exportChart()">📥 내보내기</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="mainChart" width="800" height="400"></canvas>
            </div>
        </div>
    </section>
    
    <!-- Statistics Summary -->
    <section class="stats-summary">
        <h2>📈 주요 지표</h2>
        <div class="summary-grid">
            <div class="admin-card summary-card">
                <div class="summary-icon">🏆</div>
                <div class="summary-content">
                    <h3>총 토너먼트</h3>
                    <div class="summary-number">200</div>
                    <div class="summary-change positive">+12% 증가</div>
                </div>
            </div>
            
            <div class="admin-card summary-card">
                <div class="summary-icon">⭐</div>
                <div class="summary-content">
                    <h3>평균 SR</h3>
                    <div class="summary-number">5.2</div>
                    <div class="summary-change neutral">변동 없음</div>
                </div>
            </div>
            
            <div class="admin-card summary-card">
                <div class="summary-icon">👥</div>
                <div class="summary-content">
                    <h3>평균 참가자</h3>
                    <div class="summary-number">64</div>
                    <div class="summary-change positive">+8% 증가</div>
                </div>
            </div>
            
            <div class="admin-card summary-card">
                <div class="summary-icon">🎮</div>
                <div class="summary-content">
                    <h3>진행 중인 토너먼트</h3>
                    <div class="summary-number">24</div>
                    <div class="summary-change negative">-5% 감소</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Data Insights -->
    <section class="insights-section">
        <h2>💡 데이터 인사이트</h2>
        <div class="insights-grid">
            <div class="admin-card insight-card">
                <div class="insight-icon">📈</div>
                <h3>월별 트렌드</h3>
                <p>9월에 토너먼트 개최가 가장 활발했습니다. 주로 가을 시즌에 대규모 토너먼트가 집중되는 경향을 보입니다.</p>
            </div>
            
            <div class="admin-card insight-card">
                <div class="insight-icon">⭐</div>
                <h3>난이도 분석</h3>
                <p>5-6성 난이도 토너먼트가 가장 인기가 높으며, 초보자와 고급자를 위한 토너먼트 균형이 잘 맞춰져 있습니다.</p>
            </div>
            
            <div class="admin-card insight-card">
                <div class="insight-icon">🕒</div>
                <h3>시간대 분석</h3>
                <p>저녁 8-10시에 토너먼트 활동이 가장 활발합니다. 이는 한국 사용자들의 게임 플레이 패턴과 일치합니다.</p>
            </div>
            
            <div class="admin-card insight-card">
                <div class="insight-icon">✅</div>
                <h3>완료율</h3>
                <p>토너먼트 완료율이 78%로 높은 편입니다. 취소율을 더 낮추기 위한 사전 검증 프로세스가 효과적입니다.</p>
            </div>
        </div>
    </section>
    
    <!-- Raw Data Table -->
    <section class="raw-data-section">
        <h2>📊 원시 데이터</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>기간</th>
                        <th>토너먼트 수</th>
                        <th>평균 SR</th>
                        <th>완료율</th>
                        <th>평균 참가자</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2024년 9월</td>
                        <td>30</td>
                        <td>5.2</td>
                        <td>83%</td>
                        <td>64</td>
                    </tr>
                    <tr>
                        <td>2024년 8월</td>
                        <td>25</td>
                        <td>5.1</td>
                        <td>76%</td>
                        <td>58</td>
                    </tr>
                    <tr>
                        <td>2024년 7월</td>
                        <td>20</td>
                        <td>4.9</td>
                        <td>80%</td>
                        <td>62</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart data
const chartData = {
    monthly: {
        title: '월별 토너먼트 현황',
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: '토너먼트 수',
                data: [5, 8, 12, 15, 18, 22, 20, 25, 30, 28, 24, 26],
                borderColor: '#00ffff',
                backgroundColor: 'rgba(0, 255, 255, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        }
    },
    'sr-distribution': {
        title: 'SR 난이도 분포',
        type: 'bar',
        data: {
            labels: ['1-2★', '2-3★', '3-4★', '4-5★', '5-6★', '6-7★', '7-8★', '8-9★', '9-10★'],
            datasets: [{
                label: '토너먼트 수',
                data: [15, 25, 35, 45, 55, 35, 20, 8, 2],
                backgroundColor: [
                    '#00ffff', '#ff00ff', '#00ff88', '#ffaa00', '#ff0055',
                    '#0088ff', '#88ff00', '#ff8800', '#ff0088'
                ],
                borderWidth: 2,
                borderColor: 'rgba(255, 255, 255, 0.2)'
            }]
        }
    },
    status: {
        title: '토너먼트 상태별 분포',
        type: 'doughnut',
        data: {
            labels: ['완료', '진행 중', '대기', '취소'],
            datasets: [{
                data: [156, 24, 12, 8],
                backgroundColor: ['#00ff88', '#00ffff', '#ffaa00', '#ff0055'],
                borderWidth: 2,
                borderColor: '#131823'
            }]
        }
    },
    'peak-hours': {
        title: '시간대별 활동',
        type: 'radar',
        data: {
            labels: ['00시', '06시', '12시', '18시', '20시', '22시'],
            datasets: [{
                label: '토너먼트 활동',
                data: [2, 5, 15, 35, 42, 28],
                borderColor: '#ff00ff',
                backgroundColor: 'rgba(255, 0, 255, 0.1)',
                borderWidth: 3
            }]
        }
    }
};

let currentChart = null;

// Initialize chart
function initChart(chartType = 'monthly') {
    const ctx = document.getElementById('mainChart').getContext('2d');
    
    if (currentChart) {
        currentChart.destroy();
    }
    
    const config = chartData[chartType];
    document.getElementById('chart-title').textContent = config.title;
    
    currentChart = new Chart(ctx, {
        type: config.type,
        data: config.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: config.type !== 'doughnut' && config.type !== 'radar' ? {
                y: {
                    ticks: {
                        color: '#a8b2d1'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#a8b2d1'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            } : {}
        }
    });
}

// Chart navigation
$(document).ready(function() {
    initChart('monthly');
    
    $('.chart-nav button').click(function() {
        const chartType = $(this).data('chart');
        $('.chart-nav button').removeClass('active');
        $(this).addClass('active');
        initChart(chartType);
    });
});

function exportChart() {
    const link = document.createElement('a');
    link.download = 'tournament-chart.png';
    link.href = currentChart.toBase64Image();
    link.click();
}
</script>