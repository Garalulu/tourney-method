<?php
// Error page content for Tournament Method
?>

<div class="empty-state" style="padding: 4rem 2rem;">
    <h2 style="color: var(--del-color); margin-bottom: 1rem;">⚠️ 시스템 오류</h2>
    <p class="korean-text" style="margin-bottom: 2rem; font-size: 1.1rem;">
        죄송합니다. 시스템에 일시적인 문제가 발생했습니다.<br>
        잠시 후 다시 시도해주세요.
    </p>
    
    <div style="background: var(--card-background-color); padding: 2rem; border-radius: var(--border-radius); margin: 2rem 0; text-align: left;">
        <h4>문제 해결 방법:</h4>
        <ul class="korean-text" style="margin: 1rem 0;">
            <li>페이지를 새로고침해보세요</li>
            <li>잠시 기다린 후 다시 접속해보세요</li>
            <li>브라우저 캐시를 삭제해보세요</li>
            <li>문제가 계속되면 관리자에게 문의하세요</li>
        </ul>
    </div>
    
    <div style="margin-top: 2rem;">
        <a href="/" class="oauth-button">홈으로 돌아가기</a>
        <a href="javascript:location.reload()" class="oauth-button" style="background: var(--muted-color); margin-left: 1rem;">
            페이지 새로고침
        </a>
    </div>
    
    <div style="margin-top: 3rem; font-size: 0.9rem; color: var(--muted-color);">
        <p>오류 시간: <?= date('Y-m-d H:i:s') ?> KST</p>
    </div>
</div>