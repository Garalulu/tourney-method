# Tourney Method Tech Stack

## Overview
The Tourney Method uses a constrained, vanilla technology stack optimized for Korean deployment on **DigitalOcean App Platform**. This document defines the **planned technology stack** for this **greenfield project** that will be implemented following the user stories.

## Planned Technology Stack

### Backend - Vanilla PHP
| Component | Technology | Version | Purpose | Platform Integration |
|-----------|------------|---------|---------|---------------------|
| **Runtime** | PHP | 8.1+ | Server-side logic | App Platform managed runtime |
| **Web Server** | Nginx | Managed | HTTP request handling | Platform-managed, no config needed |
| **Database** | SQLite | 3.35+ | Data persistence | Persistent storage (/tmp) |
| **Session Storage** | PHP Sessions | Built-in | Admin authentication | File-based in container |
| **Logging** | PHP error_log | Built-in | Error tracking | Platform log aggregation |

**Key Characteristics:**
- **No PHP Framework**: Vanilla PHP with custom MVC-like structure
- **PSR-4 Autoloading**: Manual implementation for class loading
- **Repository Pattern**: Data access abstraction without ORM
- **Service Layer**: Business logic separation
- **App Platform Integration**: Git-based deployment with auto-scaling

### Frontend - Progressive Enhancement
| Component | Technology | Version | Purpose | CDN/Local |
|-----------|------------|---------|---------|-----------|
| **CSS Framework** | Pico.css | 1.5+ | Base styling and responsive design | CDN + local fallback |
| **JavaScript Library** | jQuery | 3.6+ | DOM manipulation and AJAX | CDN + local fallback |
| **Icons** | None | - | Using Unicode and CSS shapes | Local only |
| **Fonts** | System fonts | - | Korean: Malgun Gothic, Apple SD Gothic | System default |

**Frontend Patterns:**
- **Progressive Enhancement**: Works without JavaScript
- **Component-Based CSS**: Modular styling approach
- **Namespace JavaScript**: Avoid global pollution
- **Responsive Design**: Mobile-first with Pico.css grid

### Database Schema - SQLite (App Platform Storage)
```sql
-- Planned database structure (to be created in /tmp/tournaments.db)
CREATE TABLE tournaments (
    tournament_id INTEGER PRIMARY KEY AUTOINCREMENT,
    topic_id INTEGER UNIQUE NOT NULL,
    title TEXT NOT NULL,
    host_name TEXT,
    game_mode TEXT DEFAULT 'osu',
    banner_url TEXT,
    sheet_id TEXT,
    discord_id TEXT,
    forum_link TEXT,
    stream_url TEXT,
    registration_url TEXT,
    has_badge BOOLEAN DEFAULT 0,
    rank_range TEXT,
    tournament_dates TEXT,
    tournament_status TEXT DEFAULT 'pending_review',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_users (
    user_id INTEGER PRIMARY KEY,
    osu_id INTEGER UNIQUE NOT NULL,
    username TEXT NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE system_logs (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    log_level TEXT NOT NULL,
    message TEXT NOT NULL,
    context TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### External APIs & Services
| Service | Purpose | Authentication | Rate Limits | Notes |
|---------|---------|----------------|-------------|--------|
| **osu! API v2** | Tournament data, user auth | OAuth 2.0 | 1000/min | Primary data source |
| **osu! Forum API** | Tournament post parsing | Client credentials | 300/min | Forum topic fetching |
| **External Images** | Tournament banners | None | Varies | No local image storage |

### Development Tools (Planned)
| Tool | Purpose | Configuration | App Platform Integration |
|------|---------|---------------|-------------------------|
| **Composer** | Minimal dependencies | `composer.json` | Auto-installed on deployment |
| **PHPUnit** | Unit testing | `phpunit.xml` | Basic setup |
| **Git** | Version control | Standard workflow | Auto-deployment trigger |
| **VS Code** | Development environment | `.vscode/` config | Optional |

**App Platform Features:**
- **Git Integration**: Push to deploy automatically
- **Build Commands**: `composer install --no-dev --optimize-autoloader`
- **Environment Variables**: Encrypted secret management
- **Monitoring**: Built-in performance and error monitoring

## Infrastructure - Korean Deployment Focus (App Platform)

### Production Environment (DigitalOcean App Platform - Singapore)
```
Platform: DigitalOcean App Platform
Region: Singapore (SGP1) - optimal Korean latency
Cost: $5/month (60% savings vs $12 Droplet)
Runtime: PHP 8.1+ (managed runtime)
Web Server: Nginx (platform managed)
Database: SQLite in persistent storage (/tmp)
CDN: Built-in global CDN
SSL: Automatic Let's Encrypt (managed)
Timezone: Asia/Seoul (KST)
Auto-scaling: Available (basic tier)
```

### App Platform Configuration (.do/app.yaml)
```yaml
name: tourney-method
region: sgp1  # Singapore for Korean optimization

services:
  - name: web
    source_dir: /
    github:
      repo: your-username/tourney-method
      branch: main
      deploy_on_push: true
    
    run_command: php -S 0.0.0.0:$PORT -t public/
    environment_slug: php
    instance_count: 1
    instance_size_slug: basic-xxs  # $5/month tier
    
    envs:
      - key: OSU_CLIENT_ID
        scope: RUN_TIME
        type: SECRET
      - key: OSU_CLIENT_SECRET
        scope: RUN_TIME
        type: SECRET
      - key: TZ
        scope: RUN_TIME
        value: "Asia/Seoul"
      - key: APP_URL
        scope: RUN_TIME
        value: "https://tourney-method-xxxxx.ondigitalocean.app"

# Scheduled Jobs for Tournament Parser
jobs:
  - name: tournament-parser
    source_dir: /
    run_command: php scripts/parser/daily_parser.php
    schedule: "0 2 * * *"  # Daily at 2 AM KST
    instance_count: 1
    instance_size_slug: basic-xxs
```

### File Structure for App Platform
```
tourney-method/                    # Git repository root
├── .do/
│   └── app.yaml                  # App Platform configuration
├── public/                        # Web root files (served by platform)
│   ├── index.php                 # Application entry point
│   └── assets/                   # Static files (auto-CDN)
├── src/                          # PHP application code
├── data/                         # SQLite schema and migrations
├── config/                       # Environment configuration
├── scripts/                      # CLI scripts and setup
├── composer.json                 # PHP dependencies
└── .gitignore                    # Deployment exclusions
```

### Container Storage Configuration
```php
// App Platform persistent storage paths
$config = [
    'database' => [
        'path' => '/tmp/tournaments.db',  // Persistent across deployments
        'backup_path' => '/tmp/backups/'
    ],
    'logging' => [
        'file_path' => '/tmp/production.log'
    ]
];

// Ensure directories exist on deployment
$dirs = ['/tmp/backups', '/tmp/logs'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
```

## Korean Localization Stack

### Character Support
| Component | Configuration | Purpose |
|-----------|---------------|---------|
| **Database Collation** | `utf8_unicode_ci` | Korean character storage |
| **PHP mbstring** | `UTF-8` default | String processing |
| **HTTP Headers** | `charset=UTF-8` | Browser encoding |
| **HTML Meta** | `<meta charset="UTF-8">` | Document encoding |

### Timezone Handling (App Platform)
```php
// Container timezone configuration
putenv('TZ=Asia/Seoul');
date_default_timezone_set('Asia/Seoul');

// App Platform environment variable
// TZ=Asia/Seoul (set in .do/app.yaml)

// Database UTC storage with KST display
$utc = new DateTime('UTC');
$kst = $utc->setTimezone(new DateTimeZone('Asia/Seoul'));
```

### Korean Font Stack
```css
/* Korean text optimization */
.korean-text {
    font-family: 
        'Malgun Gothic',           /* Windows Korean */
        'Apple SD Gothic Neo',     /* macOS Korean */
        '맑은 고딕',                /* Korean name fallback */
        sans-serif;                /* Generic fallback */
    word-break: keep-all;          /* Korean line breaking */
    overflow-wrap: break-word;     /* Long word handling */
}
```

## Implementation Constraints & Considerations

### Known Limitations (Greenfield Constraints)
1. **No Framework Structure**: Custom MVC implementation instead of established framework
2. **Container-Based Database**: SQLite in ephemeral-persistent storage (backup strategy needed)
3. **Platform Deployment Constraints**: Limited web server configuration options
4. **Limited Error Handling**: Basic PHP error logging with platform log aggregation
5. **No Caching Layer**: Direct database queries for all requests
6. **Minimal Dependency Management**: Basic Composer usage

### App Platform Implementation Patterns
```php
// Database persistence across container restarts
$dbPath = '/tmp/tournaments.db';
$backupPath = '/tmp/backup/tournaments_backup.db';

// Initialize database if not exists (deployment script)
if (!file_exists($dbPath) && file_exists($backupPath)) {
    copy($backupPath, $dbPath);
    chmod($dbPath, 0600);
}

// Health check endpoint for App Platform monitoring
// public/health.php
<?php
$status = [
    'status' => 'healthy',
    'timestamp' => time(),
    'database' => file_exists('/tmp/tournaments.db') ? 'connected' : 'missing'
];
header('Content-Type: application/json');
echo json_encode($status);
```

### Performance Optimizations (App Platform)
```php
// SQLite optimization for container environment
PRAGMA journal_mode = WAL;           // Better concurrency
PRAGMA synchronous = NORMAL;         // Balanced safety/speed
PRAGMA cache_size = 10000;           // 40MB cache
PRAGMA temp_store = memory;          // In-memory temp tables

// Container resource optimization
ini_set('memory_limit', '128M');     // App Platform basic-xxs limits
ini_set('max_execution_time', 30);   // Platform timeout limits
opcache_enable = 1;                  // Bytecode caching enabled
```

## Evolution Path (App Platform)

### Phase 1: MVP Implementation (Target)
- **Vanilla PHP + jQuery + SQLite** on App Platform
- **$5/month cost** (60% savings vs Droplet)
- **Git-based auto-deployment** from GitHub
- **Managed SSL, CDN, monitoring**
- **Singapore region** for Korean optimization

### Phase 2: Enhanced Services (Q2 2025)
```
App Platform Additions:
├── Managed Database → PostgreSQL add-on ($15/month)
├── Redis Add-on → Caching and session storage
├── Worker Services → Background job processing
├── Custom Domain → tourneymethod.com
└── Advanced Monitoring → Performance insights
```

### Phase 3: Database Migration (Q3 2025)
```
Database Evolution:
├── SQLite → Managed PostgreSQL
├── Container Storage → Dedicated database service
├── Backup Strategy → Automated managed backups
└── Migration Scripts → Schema versioning
```

### Phase 4: Microservices (2026+)
```
App Platform Services:
├── tournament-parser → Separate worker service
├── user-management → Authentication service  
├── notification-service → Real-time features
└── analytics-service → Usage tracking service
```

## Development Environment Setup

### Local Development Stack
```bash
# Required software
PHP 8.1+                    # Core runtime
SQLite 3.35+               # Database engine
Nginx                      # Local web server (matching App Platform)
Composer                   # Dependency management
Git                        # Version control
doctl CLI                  # DigitalOcean CLI for deployment

# Optional tools
PHPUnit                    # Testing framework
VS Code                    # Development environment
Docker                     # Local containerization to match App Platform
```

### Environment Configuration (App Platform Compatible)
```php
// config/development.php
<?php
return [
    'database' => [
        'path' => __DIR__ . '/../data/tournaments_dev.db'
    ],
    'oauth' => [
        'client_id' => getenv('OSU_CLIENT_ID'),
        'client_secret' => getenv('OSU_CLIENT_SECRET'),
        'redirect_uri' => 'http://localhost:8000/api/auth/callback'
    ],
    'debug' => true,
    'timezone' => 'Asia/Seoul',
    'app_platform' => false  // Local development flag
];

// config/production.php (App Platform)
<?php
return [
    'database' => [
        'path' => '/tmp/tournaments.db'  // App Platform persistent storage
    ],
    'oauth' => [
        'client_id' => getenv('OSU_CLIENT_ID'),
        'client_secret' => getenv('OSU_CLIENT_SECRET'),
        'redirect_uri' => getenv('APP_URL') . '/api/auth/callback'
    ],
    'debug' => false,
    'timezone' => 'Asia/Seoul',
    'app_platform' => true,   // Production platform flag
    'app_url' => getenv('APP_URL')
];
```

## Monitoring & Observability (App Platform)

### Platform-Managed Monitoring ✅
- **Application Logs**: Centralized log aggregation via dashboard
- **Performance Metrics**: CPU, memory, request response times
- **Health Checks**: Automated endpoint monitoring (/health)
- **SSL Monitoring**: Certificate expiration alerts (auto-renewed)
- **Uptime Monitoring**: 99.99% SLA with automatic failover
- **Cost Monitoring**: Usage tracking and cost alerts

### Application Logging (Platform Integration)
```php
// Platform-integrated logging with structured format
function logError($message, $context = []) {
    $logEntry = [
        'timestamp' => date('c'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'app_platform' => true
    ];
    error_log(json_encode($logEntry));
}

// Tournament parser logging
logError("Tournament parser failed", [
    'topic_id' => $topicId,
    'error' => $e->getMessage(),
    'korean_content' => $containsKorean
]);
```

## Security Stack (App Platform)

### Platform Security Features (App Platform Managed)
| Component | Implementation | Status |
|-----------|----------------|--------|
| **SSL/TLS** | Managed certificates (auto-renewal) | Platform managed |
| **DDoS Protection** | Platform-level protection | Platform managed |
| **Network Security** | Private networking between services | Platform managed |
| **Environment Variables** | Encrypted secret management | Platform managed |
| **Container Isolation** | Platform-managed sandboxing | Platform managed |
| **Automatic Updates** | OS and runtime security patches | Platform managed |

### Application Security (To Be Implemented)
| Component | Implementation | Status |
|-----------|----------------|--------|
| **SQL Injection Protection** | PDO prepared statements | To implement |
| **XSS Prevention** | `htmlspecialchars()` output escaping | To implement |
| **CSRF Protection** | Session-based tokens | To implement |
| **Authentication** | osu! OAuth 2.0 | To implement |
| **Input Validation** | `filter_var()` functions | To implement |

### Security Configuration (App Platform)
```php
// Enhanced security headers for App Platform
function applyAppPlatformSecurity() {
    $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self' https://*.ondigitalocean.app; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
    ];
    
    foreach ($headers as $header => $value) {
        header("{$header}: {$value}");
    }
}

// Container-specific security
if (getenv('APP_ENV') === 'production') {
    ini_set('expose_php', 'Off');
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
}
```

## Cost Analysis (App Platform vs Traditional)

### Current App Platform Cost Structure
```
Monthly Costs:
├── Basic Web Service (basic-xxs): $5.00
├── Scheduled Jobs (parser): $0.00 (included)
├── SSL Certificate: $0.00 (automatic)
├── CDN: $0.00 (included)
├── Monitoring: $0.00 (included)
├── Container Storage: $0.00 (included in basic tier)
└── Total: $5.00/month ($60/year)

Previous Droplet Cost: $12.00/month ($144/year)
Annual Savings: $84 (58.3% reduction)
```

### Scaling Cost Projections
```
Phase 2 (Enhanced):
├── Professional Web Service: $12.00/month
├── Managed PostgreSQL DB: $15.00/month
├── Redis Cache: $15.00/month
└── Total: $42.00/month

Phase 3 (High Traffic):
├── Professional Web Service x2: $24.00/month
├── Managed PostgreSQL DB: $15.00/month
├── Redis Cache: $15.00/month
├── Worker Services x2: $10.00/month
└── Total: $64.00/month
```

## Korean Performance Optimization

### App Platform Korean Features
- **Singapore Region**: 30-60ms latency to Seoul/Busan
- **Global CDN**: Optimized asset delivery to Korea
- **Auto-scaling**: Handles Korean tournament traffic spikes
- **Built-in Monitoring**: Korean user experience tracking

### Performance Testing
```bash
# Korean performance testing script
#!/bin/bash
echo "=== Korean Market Performance Test (App Platform) ==="

# Test from Korean perspective
HOMEPAGE_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://tourneymethod.com/)
echo "Homepage load time: ${HOMEPAGE_TIME}s (Target: <2s)"

API_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://tourneymethod.com/api/tournaments)  
echo "API response time: ${API_TIME}s (Target: <500ms)"

# Test CDN performance
CDN_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://tourneymethod.com/assets/css/pico.min.css)
echo "CDN asset time: ${CDN_TIME}s (Target: <200ms)"
```

---

**Note**: This tech stack reflects the **App Platform deployment model** with managed services, 60% cost savings, automated SSL/CDN, and container-based architecture optimized for Korean users. The vanilla technology constraints and Korean market focus remain unchanged while gaining significant operational benefits.