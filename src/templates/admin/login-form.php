<?php
// Login form template - should only be included from admin layout

use TourneyMethod\Utils\SecurityHelper;
?>
<div class="login-container">
    <header class="text-center">
        <h1>🏆 Tourney Method</h1>
        <h2>관리자 로그인</h2>
        <p>osu! 계정으로 안전하게 로그인하세요</p>
    </header>
    
    <div class="text-center">
        <a href="?login=osu" class="oauth-button" role="button">
            osu!로 로그인하기
        </a>
    </div>
    
    <footer class="text-center" style="margin-top: 2rem;">
        <small>
            <p><strong>관리자 전용</strong></p>
            <p>승인된 관리자만 접근할 수 있습니다.<br>
            로그인은 osu! OAuth 2.0을 통해 안전하게 처리됩니다.</p>
        </small>
        
        <details style="margin-top: 1rem;">
            <summary>보안 정보</summary>
            <small>
                <ul style="text-align: left; margin-top: 0.5rem;">
                    <li>CSRF 보호 적용</li>
                    <li>세션 보안 설정</li>
                    <li>osu! API v2 연동</li>
                    <li>관리자 권한 검증</li>
                </ul>
            </small>
        </details>
    </footer>
</div>