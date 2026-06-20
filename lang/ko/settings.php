<?php

return [
    'title' => '설정',
    'discord_webhook' => [
        'title' => '디스코드 웹훅',
        'description' => '디스코드 서버에서 직접 알림을 받게 설정하세요.',
        'webhook_url' => '웹훅 URL',
        'valid' => '유효한 URL입니다.',
        'invalid' => '유효하지 않은 URL입니다.',
        'how_to' => '디스코드 웹훅 생성하는 법',
        'how_to_url' => 'https://support.discord.com/hc/ko/articles/228383668-%EC%9B%B9%ED%9B%84%ED%81%AC-%EC%86%8C%EA%B0%9C',
        'test_button' => '테스트 메시지 전송하기',
        'test_sending' => '전송 중...',
        'test_success' => '테스트 메시지가 전송되었습니다! 디스코드를 확인해보세요.',
        'test_error' => '메시지 전송에 실패했습니다.',
    ],
    'game_mode' => [
        'title' => '메인 게임모드',
        'description' => '대시보드에서 확인할 메인 게임모드를 설정하세요.',
    ],
    'in_app_notifications' => [
        'title' => '알림 설정',
        'description' => '받고 싶은 앱 내 알림을 선택하세요.',
        'categories' => [
            'participation_records' => '참가 기록',
            'new_tournament_alert' => '새 토너먼트 알림',
        ],
        'events' => [
            'teammate_changes' => [
                'title' => '팀원 변경',
                'description' => '다른 사용자가 대회 참여 기록에서 당신을 팀원으로 추가하거나 제거할 때',
            ],
            'participation_updates' => [
                'title' => '참가 정보 변경',
                'description' => '다른 사용자가 당신이 참여한 대회 참여 기록의 팀 이름, 최종 스테이지, 또는 시드를 변경할 때',
            ],
            'deletion_requests' => [
                'title' => '삭제 요청 결과',
                'description' => '당신의 대회 참여 기록 삭제 요청 승인/거부 결과',
            ],
            'eligible_registration_open' => [
                'title' => '새 토너먼트 알림',
                'description' => '참가 가능한 토너먼트 알람 받기',
            ],
            'eligible_registration_closing_24h' => [
                'title' => '토너먼트 등록 마감 알림',
                'description' => '참가 가능한 토너먼트 등록 마감 24시간 전 알람 받기',
            ],
        ],
    ],
    'save_button' => '설정 저장',
    'saving' => '저장 중...',
    'saved' => '설정이 성공적으로 저장되었습니다!',
    'save_error' => '설정 저장에 실패했습니다. 다시 시도해 주세요.',
];
