<?php

return [
    'title' => '設定',
    'discord_webhook' => [
        'title' => 'Discord Webhook',
        'description' => '直接在你的 Discord 伺服器或私聊中接受比賽通知。',
        'webhook_url' => 'Webhook URL',
        'valid' => '有效',
        'invalid' => '無效',
        'how_to' => '如何創建 Discord Webhook',
        'how_to_url' => 'https://support.discord.com/hc/zh-tw/articles/228383668-Webhook-%E5%85%A5%E9%96%80',
        'test_button' => '發送測試訊息',
        'test_sending' => '發送中...',
        'test_success' => '測試消息已發送！請檢查你的 Discord。',
        'test_error' => '測試消息發送失敗。',
    ],
    'game_mode' => [
        'title' => '主要遊戲模式',
        'description' => '你在儀表盤中顯示的首選遊戲模式。',
    ],
    'in_app_notifications' => [
        'title' => 'Notification Preferences',
        'description' => 'Choose which in-app notifications you want to receive.',
        'categories' => [
            'participation_records' => 'Participation records',
            'new_tournament_alert' => 'New tournament alerts',
        ],
        'events' => [
            'teammate_changes' => [
                'title' => 'Teammate changes',
                'description' => 'When another user adds or removes you as a teammate in participation records.',
            ],
            'participation_updates' => [
                'title' => 'Participation details changed',
                'description' => 'When another user changes team name, final stage, or seed for a record you participated in.',
            ],
            'deletion_requests' => [
                'title' => 'Deletion request results',
                'description' => 'When your participation deletion request is approved or denied.',
            ],
            'eligible_registration_open' => [
                'title' => 'Eligible registration open',
                'description' => 'When an eligible tournament for your main mode opens registration.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => 'Eligible registration closing soon',
                'description' => 'When an eligible tournament for your main mode closes registration within 24 hours.',
            ],
        ],
    ],
    'save_button' => '保存設置',
    'saving' => '保存中...',
    'saved' => '設定保存成功！',
    'save_error' => '保存設定失敗，請重試。',
];
