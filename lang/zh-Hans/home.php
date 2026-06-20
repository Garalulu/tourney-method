<?php

return [
    'hero' => [
        'title' => 'Tourney Method',
        'subtitle' => '探索 osu! 比赛与参赛记录',
    ],
    'guest_actions' => [
        'browse_tournaments' => '浏览比赛',
        'browse_tournaments_desc' => '探索来自 osu! 官方论坛的比赛',
        'login_to_search' => '登录以搜索与过滤',
        'login_to_search_desc' => '搜索用户并检查符合参赛条件的比赛',
    ],
    'auth_actions' => [
        'browse_tournaments' => '浏览比赛',
        'browse_tournaments_desc' => '浏览并过滤比赛',
        'already_logged_in' => '已登录',
        'already_logged_in_desc' => '请使用导航栏中的搜索与过滤功能',
    ],
    'sections' => [
        'tournament_discovery' => [
            'title' => '发现比赛',
            'content' => '比赛列表每天会根据 osu! 论坛内容自动更新。你可以按游戏模式、报名状态、排名要求等标准进行过滤，找到你可以参加的比赛。',
        ],
        'tournament_history' => [
            'title' => '比赛历史',
            'content' => '每个比赛页面都会显示工作人员名单、获胜者列表以及比赛日期。回顾过去的比赛，看看谁曾参与其中。',
        ],
        'user_profiles' => [
            'title' => '个人资料',
            'content' => '在个人资料页，你可以查看完整的参赛记录，包括获得名次的比赛以及作为工作人员参与的比赛。',
        ],
        'how_to_use' => [
            'title' => '如何使用 Tourney Method',
            'items' => [
                0 => [
                    'title' => '查找比赛',
                    'body' => '使用比赛筛选和搜索指令对比各项比赛的报名状态、排名范围、游戏模式、地区限制以及比赛规模。',
                ],
                1 => [
                    'title' => '关注报名情况',
                    'body' => '当您关注比赛时，应用内通知会在符合参赛资格的比赛报名开始或截止前的 24 小时内提醒您。',
                ],
                2 => [
                    'title' => '追踪您的历史记录',
                    'body' => '打开您的个人资料页面，您可以添加参赛记录，包括所参与的阶段、最终名次、队友信息以及对局链接。',
                ],
                3 => [
                    'title' => '完善数据',
                    'body' => '在比赛详情页面，滚动至贡献部分，您可以提交针对比赛数据、工作人员名单、获奖记录或相关链接的修正建议。',
                ],
            ],
        ],
        'eligibility_checking' => [
            'title' => '资格检查',
            'content' => '部分比赛使用奖牌加权种子 (BWS) 系统来确定参赛资格。本系统支持标准和自定义 BWS 格式，包括排除较旧奖牌的方法（例如 2022 年之前的奖牌不计入计算），让你了解自己在各种 BWS 计算方法下的参赛资格。',
        ],
        'data_sources' => [
            'title' => '数据来源',
            'osu_api' => 'osu! API',
            'osu_api_desc' => '用户认证、个人资料及比赛数据',
            'tcomm' => 'tcomm API',
            'tcomm_desc' => '带有奖牌的比赛数据',
            'otr' => 'o!TR 公开数据集',
            'otr_desc' => '比赛数据',
            'community' => '社区',
            'community_desc' => '比赛数据',
        ],
    ],
    'stats' => [
        'active_players' => '活跃玩家',
        'active_staff' => '活跃工作人员',
        'tournaments' => '比赛数',
    ],
];
