<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'osu' => [
        'client_id' => env('OSU_CLIENT_ID'),
        'client_secret' => env('OSU_CLIENT_SECRET'),
        'redirect' => env('OSU_REDIRECT_URI'),
    ],

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
    ],

    'discord' => [
        'central_webhooks' => [
            'osu_badge' => env('DISCORD_OSU_BADGE_WEBHOOK'),
            'osu_nonbadge' => env('DISCORD_OSU_NONBADGE_WEBHOOK'),
            'taiko_badge' => env('DISCORD_TAIKO_BADGE_WEBHOOK'),
            'taiko_nonbadge' => env('DISCORD_TAIKO_NONBADGE_WEBHOOK'),
            'catch_badge' => env('DISCORD_CATCH_BADGE_WEBHOOK'),
            'catch_nonbadge' => env('DISCORD_CATCH_NONBADGE_WEBHOOK'),
            'mania_badge' => env('DISCORD_MANIA_BADGE_WEBHOOK'),
            'mania_nonbadge' => env('DISCORD_MANIA_NONBADGE_WEBHOOK'),
        ],
    ],

    'tcomm' => [
        'api_key' => env('TCOMM_API_KEY'),
        'base_url' => env('TCOMM_BASE_URL', 'https://tcomm.hivie.tn/api'),
    ],

    'otr' => [
        'api_key' => env('OTR_API_KEY'),
        'base_url' => env('OTR_BASE_URL', 'https://otr.stagec.net/api'),
    ],

    'osulobbyfinder' => [
        'base_url' => env('OSULOBBYFINDER_BASE_URL', 'https://osulobbyfinder.dri3x.cz/api'),
    ],

    'sip' => [
        'api_token' => env('SIP_API_TOKEN'),
    ],

];
