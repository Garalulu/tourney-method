<?php

return [
    'changelog' => [
        'eyebrow' => 'What changed',
        'dont_show_again' => 'Do not show this update again',
        'ok' => 'Got it',
        'close' => 'Close changelog',
    ],
    'errors' => [
        404 => [
            'heading' => '페이지를 찾을 수 없습니다',
            'message' => '찾고 있는 페이지가 존재하지 않거나 이동되었습니다.',
            'go_home' => '홈으로 이동',
            'go_back' => '뒤로 가기',
        ],
        401 => [
            'heading' => '인증이 필요합니다',
        ],
        403 => [
            'heading' => '접근이 거부되었습니다',
        ],
        500 => [
            'heading' => '문제가 발생했습니다',
        ],
    ],
    'nav' => [
        'tournaments' => '토너먼트',
        'dashboard' => '대시보드',
        'admin' => '관리자',
        'profile' => '프로필',
        'settings' => '설정',
        'device_theme' => 'Device theme',
        'use_device_theme' => 'Use Device theme',
        'dark_theme' => 'Dark theme',
        'light_theme' => 'Light theme',
        'search' => '검색',
        'logout' => '로그아웃',
        'login_with_osu' => 'osu!로 로그인',
    ],
    'actions' => [
        'reload' => '다시 시도',
    ],
    'messages' => [
        'language_updated' => '언어가 변경되었습니다.',
    ],
    'notifications' => [
        'title' => '알림',
        'read_all' => '모든 알림 읽기',
        'remove_read' => 'Remove all read notifications',
        'loading' => '알림 로드 중...',
        'empty' => '읽지 않은 알림이 없습니다.',
        'fields' => [
            'team_name' => '팀 이름',
            'stage_value' => '최종 스테이지',
            'seed' => '시드',
        ],
        'messages' => [
            'teammate_added' => [
                'title' => '팀원으로 추가됨',
                'body' => ':actor님이 :tournament에서 당신을 팀원으로 추가했습니다.',
            ],
            'teammate_removed' => [
                'title' => '팀원에서 제거됨',
                'body' => ':actor님이 :tournament에서 당신을 팀원에서 제거했습니다.',
            ],
            'participation_updated' => [
                'title' => '참가 정보가 업데이트됨',
                'body' => ':actor님이 :tournament의 :fields을(를) 업데이트했습니다.',
            ],
            'participation_add_approved' => [
                'title' => '참가 승인됨',
                'body' => ':tournament 포디움 참가 요청이 승인되었습니다.',
            ],
            'participation_add_denied' => [
                'title' => '참가 거부됨',
                'body' => ':tournament 포디움 참가 요청이 거부되었습니다.',
            ],
            'teammate_osu_id_failed' => [
                'title' => '팀원을 추가할 수 없습니다',
                'body' => ':tournament에서 osu! ID :ids 추가에 실패했습니다.',
            ],
            'deletion_approved' => [
                'title' => '삭제 요청 승인됨',
                'body' => ':tournament 삭제 요청이 승인되었습니다.',
            ],
            'deletion_denied' => [
                'title' => '삭제 요청 거부됨',
                'body' => ':tournament 삭제 요청이 거부되었습니다.',
            ],
            'eligible_registration_open' => [
                'title' => '참가 신청 오픈',
                'body' => ':tournament 참가 신청이 시작되었습니다.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => '참가 신청 마감 임박',
                'body' => ':tournament 참가 신청이 24시간 이내에 마감됩니다.',
            ],
        ],
    ],
    'footer' => [
        'contact' => '연락',
        'discord' => '디스코드',
        'donate' => '후원',
        'contribute' => 'Contribute',
        'copyright' => '© 2026 osu! Korean Tourney Hub',
    ],
];
