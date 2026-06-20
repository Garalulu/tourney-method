<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the cleanup:orphaned-users command which removes
    | users who were accidentally created and have no tournament participation.
    |
    */

    // Whitelist of usernames to NEVER delete
    'whitelist' => [
        // Add usernames as strings:
        // 'special_user',
        // 'important_account',
    ],

    // Protection rules
    'protection_rules' => [
        // Roles to protect
        'roles' => ['admin', 'master'],

        // Protect users who logged in via OAuth
        // main_mode_source = 'oauth_setup' means user completed OAuth setup
        'oauth_setup_protected' => true,

        // Minimum days since creation before considering for cleanup
        'min_age_days' => 0,
    ],

    // Batch processing settings
    'batch_settings' => [
        'size' => 100,        // Users per batch
        'delay' => 0,         // Delay between batches (ms)
    ],

    // Logging configuration
    'logging' => [
        'enabled' => true,
        'channel' => 'cleanup',
    ],
];
