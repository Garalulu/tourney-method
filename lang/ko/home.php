<?php

return [
    'hero' => [
        'title' => 'Tourney Method',
        'subtitle' => 'osu! 토너먼트 현황 및 결과 정리',
    ],
    'guest_actions' => [
        'browse_tournaments' => '토너먼트 찾기',
        'browse_tournaments_desc' => '토너먼트 검색 페이지로 이동합니다',
        'login_to_search' => '로그인 후 검색 기능 사용',
        'login_to_search_desc' => '유저 및 참여 가능한 토너먼트를 확인하세요',
    ],
    'auth_actions' => [
        'browse_tournaments' => '토너먼트 찾기',
        'browse_tournaments_desc' => '토너먼트 검색 페이지로 이동합니다',
        'already_logged_in' => '로그인 완료',
        'already_logged_in_desc' => '상단 검색 버튼을 이용해보세요',
    ],
    'sections' => [
        'tournament_discovery' => [
            'title' => '토너먼트 현황',
            'content' => '토너먼트 명단은 매일 osu! 포럼에서 자동으로 수집됩니다. 게임 모드, 참가 신청중, 랭크 요건 등을 기준으로 필터링하여 참가 가능한 토너먼트를 찾을 수 있습니다.',
        ],
        'tournament_history' => [
            'title' => '토너먼트 기록',
            'content' => '각 토너먼트 페이지에는 스태프/입상 명단, 토너먼트 날짜가 표시됩니다. 지난 토너먼트를 살펴보며 누가 참가했는지 확인해 보세요.',
        ],
        'user_profiles' => [
            'title' => '유저 프로필',
            'content' => '유저 프로필에서 입상한 대회, 스태프로 참여한 대회를 포함한 전체 대회 참가 내역을 확인할 수 있습니다.',
        ],
        'how_to_use' => [
            'title' => 'How to use Tourney Method',
            'items' => [
                0 => [
                    'title' => 'Find tournaments',
                    'body' => 'Use tournament filters and search commands to compare registration status, rank ranges, modes, regions, and formats.',
                ],
                1 => [
                    'title' => 'Watch registration',
                    'body' => 'When you watch tournaments, in-app notifications can alert you when eligible registration opens or closes within 24 hours.',
                ],
                2 => [
                    'title' => 'Track your history',
                    'body' => 'Open your profile and add participation records for tournaments you played, including stage reached, placement, teammates, and match links.',
                ],
                3 => [
                    'title' => 'Improve the data',
                    'body' => 'Open a tournament detail page, scroll to Contributions, and submit corrections for metadata, staff, podiums, or links.',
                ],
            ],
        ],
        'eligibility_checking' => [
            'title' => '참가 자격 확인',
            'content' => '일부 토너먼트는 참가 자격을 결정하기 위해 배지 가중치 시드(BWS) 시스템을 사용합니다. 이 시스템은 표준 및 사용자 지정 BWS 형식을 모두 지원하며, 오래된 배지를 제외하는 방식(예: 2022년 이전에 획득한 배지는 계산에서 제외)도 포함하고 있어, 다양한 BWS 계산 방식에 따라 현재 참가 자격이 있는 토너먼트를 확인할 수 있습니다.',
        ],
        'data_sources' => [
            'title' => '데이터 소스',
            'osu_api' => 'osu! API',
            'osu_api_desc' => '사용자 인증, 프로필 데이터, 토너먼트 데이터',
            'tcomm' => 'tcomm API',
            'tcomm_desc' => '배지 토너먼트 데이터',
            'otr' => 'o!TR 공개 데이터셋',
            'otr_desc' => '토너먼트 데이터',
            'community' => '커뮤니티',
            'community_desc' => '토너먼트 데이터',
        ],
    ],
    'stats' => [
        'active_players' => 'Active Players',
        'active_staff' => 'Active Staff',
        'tournaments' => '토너먼트',
    ],
];
