<?php

/**
 * Development Configuration
 * Local development environment settings
 */

return [
    'database' => [
        'path' => __DIR__ . '/../data/database/tournaments.db',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    
    'oauth' => [
        'client_id' => getenv('OSU_CLIENT_ID') ?: '',
        'client_secret' => getenv('OSU_CLIENT_SECRET') ?: '',
        'redirect_uri' => 'http://localhost:8000/api/auth/callback'
    ],
    
    'app' => [
        'debug' => true,
        'timezone' => 'Asia/Seoul',
        'base_url' => 'http://localhost:8000',
        'environment' => 'development'
    ],
    
    'security' => [
        'csrf_token_name' => '_token',
        'session_name' => 'TOURNEY_SESSION',
        'session_lifetime' => 3600, // 1 hour
        'password_hash_algo' => PASSWORD_ARGON2ID
    ],
    
    'logging' => [
        'level' => 'DEBUG',
        'file' => __DIR__ . '/../logs/development.log'
    ],
    
    'api' => [
        'rate_limit' => 100, // requests per minute
        'osu_api_base' => 'https://osu.ppy.sh/api/v2'
    ]
];