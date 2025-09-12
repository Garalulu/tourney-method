<?php
/**
 * Production configuration for DigitalOcean App Platform
 */
return [
    'environment' => 'production',
    'debug' => false,
    
    'app_url' => getenv('APP_URL') ?: 'https://tourneymethod.com',
    
    'database' => [
        // App Platform provides persistent storage at /tmp (SQLite compatible)
        'path' => getenv('DB_PATH') ?: '/tmp/tournaments.db',
        'backup_path' => '/tmp/backups/'
    ],
    
    'oauth' => [
        'client_id' => getenv('OSU_CLIENT_ID'),
        'client_secret' => getenv('OSU_CLIENT_SECRET'),
        'redirect_uri' => (getenv('APP_URL') ?: 'https://tourneymethod.com') . '/api/auth/callback'
    ],
    
    'parser' => [
        'mock_mode' => false,  // Use real osu! API in production
        'rate_limit_delay' => 5,
        'max_retries' => 3
    ],
    
    'logging' => [
        'level' => 'INFO',
        'file_path' => '/tmp/production.log'
    ],
    
    'timezone' => 'Asia/Seoul',  // Korean timezone
    
    'security' => [
        'csrf_enabled' => true,
        'https_required' => true,
        'session_secure' => true,
        'session_secret' => getenv('SESSION_SECRET'),
        'trusted_domains' => [
            'tourneymethod.com',
            'www.tourneymethod.com',
            '*.ondigitalocean.app'  // App Platform domain
        ]
    ],
    
    // Korean-specific settings
    'korean_optimization' => [
        'charset' => 'UTF-8',
        'locale' => 'ko_KR.UTF-8',
        'cache_headers' => true
    ]
];