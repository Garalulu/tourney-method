<?php

return [
    'title' => 'Settings',
    'discord_webhook' => [
        'title' => 'Discord Webhook',
        'description' => 'Receive tournament notifications directly in your Discord server or DM.',
        'webhook_url' => 'Webhook URL',
        'valid' => 'Valid',
        'invalid' => 'Invalid',
        'how_to' => 'How to create a Discord webhook',
        'how_to_url' => 'https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks',
        'test_button' => 'Send Test Message',
        'test_sending' => 'Sending...',
        'test_success' => 'Test message sent! Check your Discord.',
        'test_error' => 'Failed to send test message.',
    ],
    'game_mode' => [
        'title' => 'Main Game Mode',
        'description' => 'Your primary game mode for dashboard.',
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
    'save_button' => 'Save Settings',
    'saving' => 'Saving...',
    'saved' => 'Settings saved successfully!',
    'save_error' => 'Failed to save settings. Please try again.',
];
