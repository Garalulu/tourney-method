<?php

return [
    'title' => '设置',
    'discord_webhook' => [
        'title' => 'Discord Webhook',
        'description' => '直接在你的 Discord 服务器或私聊中接收比赛通知。',
        'webhook_url' => 'Webhook URL',
        'valid' => '有效',
        'invalid' => '无效',
        'how_to' => '如何创建 Discord Webhook',
        'how_to_url' => 'https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks',
        'test_button' => '发送测试消息',
        'test_sending' => '发送中...',
        'test_success' => '测试消息已发送！请检查你的 Discord。',
        'test_error' => '测试消息发送失败。',
    ],
    'game_mode' => [
        'title' => '主要游戏模式',
        'description' => '你在仪表盘中显示的首选游戏模式。',
    ],
    'in_app_notifications' => [
        'title' => '通知偏好设置',
        'description' => '选择您想要收到的应用内通知。',
        'categories' => [
            'participation_records' => '参赛记录',
            'new_tournament_alert' => '新比赛提醒',
        ],
        'events' => [
            'teammate_changes' => [
                'title' => '队伍更变',
                'description' => '当其他用户在参赛记录中添加或移除您作为队友。',
            ],
            'participation_updates' => [
                'title' => '参赛记录细节变更',
                'description' => '当其他用户修改了您曾参与的比赛记录中的队伍名称、最终轮次或种子排名时，系统会通知您。',
            ],
            'deletion_requests' => [
                'title' => '删除请求结果',
                'description' => '当您提交的参赛记录删除请求被审批通过或拒绝时，您将收到通知。',
            ],
            'eligible_registration_open' => [
                'title' => '符合参赛资格的报名开始',
                'description' => '当有符合您主玩模式的参赛资格的比赛报名开始时，您将收到通知。',
            ],
            'eligible_registration_closing_24h' => [
                'title' => '符合参赛资格的报名截止',
                'description' => '当有符合您主玩模式的参赛资格的比赛报名在 24 小时内截止时，您将收到通知。',
            ],
        ],
    ],
    'save_button' => '保存设置',
    'saving' => '保存中...',
    'saved' => '设置保存成功！',
    'save_error' => '设置保存失败，请重试。',
];
