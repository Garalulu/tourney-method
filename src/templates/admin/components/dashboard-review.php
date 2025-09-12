<?php
/**
 * Dashboard Tournament Review Component
 * Lists pending tournaments for review
 */

use TourneyMethod\Utils\SecurityHelper;
use TourneyMethod\Utils\DateHelper;
?>

<section class="review-section" aria-labelledby="review-title">
    <h2 id="review-title" class="section-title">검토 대기 토너먼트</h2>
    
    <?php if (empty($pendingTournaments)): ?>
        <div class="alert" role="status">
            <span class="alert-icon" aria-hidden="true">ℹ️</span>
            현재 검토 대기 중인 토너먼트가 없습니다.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="review-table" role="table" aria-label="검토 대기 토너먼트 목록">
                <thead>
                    <tr>
                        <th scope="col">토너먼트 제목</th>
                        <th scope="col">파싱 날짜</th>
                        <th scope="col">작업</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTournaments as $tournament): ?>
                        <tr>
                            <td>
                                <div class="tournament-title" title="<?= SecurityHelper::escapeHtml($tournament['title']) ?>">
                                    <?= SecurityHelper::escapeHtml($tournament['title']) ?>
                                </div>
                            </td>
                            <td>
                                <time datetime="<?= date('c', strtotime($tournament['parsed_at'])) ?>">
                                    <?= DateHelper::formatKST($tournament['parsed_at']) ?>
                                </time>
                            </td>
                            <td>
                                <a href="/admin/edit.php?id=<?= (int)$tournament['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>" 
                                   class="btn-edit" 
                                   role="button"
                                   aria-label="<?= SecurityHelper::escapeHtml($tournament['title']) ?> 편집">
                                    편집
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p role="status">
            <small>총 <?= count($pendingTournaments) ?>개의 토너먼트가 검토를 기다리고 있습니다.</small>
        </p>
    <?php endif; ?>
</section>