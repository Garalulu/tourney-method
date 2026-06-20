<?php

return [
    'hero' => [
        'title' => 'Tourney Method',
        'subtitle' => '探索 osu! 比賽與參與紀錄',
    ],
    'guest_actions' => [
        'browse_tournaments' => '瀏覽比賽',
        'browse_tournaments_desc' => '探索來自 osu! 官方論壇的比賽',
        'login_to_search' => '登入以搜尋與過濾',
        'login_to_search_desc' => '搜尋用戶並檢查符合參賽條件的比賽',
    ],
    'auth_actions' => [
        'browse_tournaments' => '瀏覽比賽',
        'browse_tournaments_desc' => '瀏覽並過濾比賽',
        'already_logged_in' => '已登入',
        'already_logged_in_desc' => '請使用導航欄中的搜尋與過濾功能',
    ],
    'sections' => [
        'tournament_discovery' => [
            'title' => '發現比賽',
            'content' => '比賽列表每天會根據 osu! 論壇內容自動更新。你可以按遊戲模式、報名狀態、排名要求等標準進行過濾，找到你可以參加的比賽。',
        ],
        'tournament_history' => [
            'title' => '比賽歷史',
            'content' => '每個比賽頁面都會顯示工作人員名單、獲勝者列表以及比賽日期。回顧過去的比賽，看看誰曾參與其中。',
        ],
        'user_profiles' => [
            'title' => '個人資料',
            'content' => '在個人資料頁，你可以查看完整的參賽記錄，包括獲得名次的比賽以及作為工作人員參與的比賽。',
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
            'title' => '資格檢查',
            'content' => '部分比賽使用獎牌加權種子 (BWS) 系統來確定參賽資格。本系統支持標準和自定義 BWS 格式，包括排除較舊牌子的方法（例如 2022 年之前的牌子不列入計算），讓你了解自己在各種 BWS 計算方法下的參賽資格。',
        ],
        'data_sources' => [
            'title' => '數據來源',
            'osu_api' => 'osu! API',
            'osu_api_desc' => '用戶認證、個人資料及比賽數據',
            'tcomm' => 'tcomm API',
            'tcomm_desc' => '帶有獎牌的比賽數據',
            'otr' => 'o!TR 公開數據集',
            'otr_desc' => '比賽數據',
            'community' => '社區',
            'community_desc' => '比賽數據',
        ],
    ],
    'stats' => [
        'active_players' => 'Active Players',
        'active_staff' => 'Active Staff',
        'tournaments' => '比賽數',
    ],
];
