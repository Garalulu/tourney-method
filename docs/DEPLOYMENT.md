# Tourney Method - DigitalOcean App Platform Deployment Guide

## Overview
Deploy Tourney Method to DigitalOcean App Platform for cost-effective Korean market launch with automatic scaling and management.

**Monthly Cost:** $5 (Basic Web Service) vs $12 (Droplet) - **60% savings**  
**Region:** Singapore (SGP1) for optimal Korean user latency  
**Features:** Auto-deployment from Git, built-in SSL, CDN included, automatic scaling

## App Platform Benefits for Korean Launch

### Cost Efficiency
- **$5/month** Basic Web Service (vs $12 droplet)
- **$84/year savings** - Critical for MVP budget
- Built-in SSL (no Let's Encrypt setup needed)
- Built-in CDN (better Korean performance than droplet alone)

### Developer Experience
- **Git-based deployment** - Push to deploy automatically
- **Zero server maintenance** - No SSH, no manual updates
- **Environment variable management** - Secure OAuth credential storage
- **Automatic scaling** - Handles Korean traffic spikes during tournaments

### Korean Market Optimization
- **Singapore region (SGP1)** - 30-60ms latency to Seoul/Busan
- **Global CDN** - Enhanced asset delivery to Korea
- **99.99% uptime SLA** - Reliable for Korean tournament discovery
- **Automatic HTTPS** - Security compliance for Korean users

## Prerequisites

### Required Tools
- **Git repository** with Tourney Method code
- **DigitalOcean account** with App Platform access
- **doctl CLI** for command-line management (optional)
- **osu! OAuth application** registered for production

### Required Files
- `.do/app.yaml` - App Platform configuration
- `composer.json` - PHP dependencies
- Database initialization scripts

## App Platform Configuration

### 1. Create App Platform Configuration File

**File: `.do/app.yaml`**
```yaml
# DigitalOcean App Platform Configuration for Tourney Method
name: tourney-method
region: sgp1  # Singapore for Korean optimization

services:
  - name: web
    source_dir: /
    github:
      repo: your-username/tourney-method  # Replace with your repo
      branch: main
      deploy_on_push: true
    
    # PHP Runtime Configuration
    run_command: php -S 0.0.0.0:$PORT -t public/
    environment_slug: php
    instance_count: 1
    instance_size_slug: basic-xxs  # $5/month tier
    
    # Korean Optimization Settings
    http_port: 80
    routes:
      - path: /
    
    # Environment Variables (secrets set via dashboard)
    envs:
      - key: OSU_CLIENT_ID
        scope: RUN_TIME
        type: SECRET
      - key: OSU_CLIENT_SECRET
        scope: RUN_TIME
        type: SECRET
      - key: ENVIRONMENT
        scope: RUN_TIME
        value: "production"
      - key: TZ
        scope: RUN_TIME
        value: "Asia/Seoul"  # Korean timezone
      - key: APP_URL
        scope: RUN_TIME
        value: "https://tourney-method-xxxxx.ondigitalocean.app"  # Will be updated with real URL

# Scheduled Jobs for Tournament Parser
jobs:
  - name: tournament-parser
    source_dir: /
    run_command: php scripts/parser/daily_parser.php
    schedule: "0 2 * * *"  # Daily at 2 AM KST (6 PM UTC)
    instance_count: 1
    instance_size_slug: basic-xxs

# Static Assets (if needed for future enhancement)
static_sites: []

# Database - Start with SQLite, upgrade to managed DB later if needed
databases: []  # SQLite uses persistent storage instead
```

### 2. Update PHP Configuration for App Platform

**File: `config/production.php`**
```php
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
        'path' => '/tmp/tournaments.db',
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
```

### 3. App Platform Deployment Script

**File: `scripts/setup/app_platform_deploy.php`**
```php
<?php
/**
 * App Platform deployment initialization script
 * Runs automatically on each deployment
 */

echo "🚀 Initializing Tourney Method for App Platform deployment...\n";

// Create necessary directories
$dirs = ['/tmp/backups', '/tmp/logs'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Created directory: {$dir}\n";
    }
}

// Initialize SQLite database if not exists
$dbPath = '/tmp/tournaments.db';
if (!file_exists($dbPath)) {
    echo "🔧 Initializing SQLite database...\n";
    
    // Create database with schema
    $schema = file_get_contents(__DIR__ . '/../../data/database/schema.sql');
    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->exec($schema);
    
    // Set proper permissions
    chmod($dbPath, 0600);
    
    echo "✅ Database initialized at {$dbPath}\n";
} else {
    echo "✅ Database already exists\n";
}

// Run any pending migrations
echo "🔧 Running database migrations...\n";
include __DIR__ . '/migrate.php';

// Korean-specific setup
echo "🇰🇷 Applying Korean market optimizations...\n";

// Set timezone
date_default_timezone_set('Asia/Seoul');

// Verify UTF-8 support
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    echo "✅ UTF-8 encoding configured for Korean support\n";
}

// Create initial admin user if not exists (ID: 757783)
$pdo = new PDO("sqlite:{$dbPath}");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE osu_user_id = ?");
$stmt->execute([757783]);

if ($stmt->fetchColumn() == 0) {
    $stmt = $pdo->prepare("INSERT INTO admin_users (osu_user_id, role, created_at) VALUES (?, ?, ?)");
    $stmt->execute([757783, 'super_admin', date('Y-m-d H:i:s')]);
    echo "✅ Admin user 757783 configured\n";
}

echo "🎉 App Platform deployment complete!\n";
echo "🇰🇷 Ready to serve Korean osu! tournament community\n";
```

## Deployment Process

### Method 1: DigitalOcean Dashboard (Recommended)

#### 1. Create New App
1. Log in to DigitalOcean dashboard
2. Navigate to **Apps** section
3. Click **"Create App"**
4. Choose **"GitHub"** as source
5. Select **tourney-method** repository
6. Choose **main** branch
7. Select **Singapore (SGP1)** region

#### 2. Configure Build Settings
- **Build Command:** `composer install --no-dev --optimize-autoloader`
- **Run Command:** `php scripts/setup/app_platform_deploy.php && php -S 0.0.0.0:$PORT -t public/`
- **Output Directory:** `/` (root directory)

#### 3. Environment Variables Setup
Navigate to **App Settings > Environment Variables**:
```
OSU_CLIENT_ID = your_osu_client_id_here
OSU_CLIENT_SECRET = your_osu_client_secret_here  
ENVIRONMENT = production
TZ = Asia/Seoul
```

#### 4. Add Custom Domain (Optional)
- **Domain:** tourneymethod.com
- **Subdomain:** www.tourneymethod.com  
- **SSL:** Automatic (Let's Encrypt managed by App Platform)

#### 5. Deploy Application
- Click **"Create Resources"**
- Wait for initial deployment (5-10 minutes)
- Verify deployment at provided *.ondigitalocean.app URL

### Method 2: doctl CLI Deployment

#### 1. Install doctl CLI
```bash
# Ubuntu/Debian
curl -sL https://github.com/digitalocean/doctl/releases/download/v1.94.0/doctl-1.94.0-linux-amd64.tar.gz | tar -xzv
sudo mv doctl /usr/local/bin

# macOS
brew install doctl

# Windows
choco install doctl
```

#### 2. Authenticate and Deploy
```bash
# Authenticate with DigitalOcean
doctl auth init

# Create app from configuration file
doctl apps create --spec .do/app.yaml

# Get app ID and monitor deployment
doctl apps list
doctl apps get $APP_ID

# Set environment variables
doctl apps update $APP_ID --spec .do/app.yaml
```

#### 3. Monitor Deployment
```bash
# View deployment logs
doctl apps logs $APP_ID --component web --follow

# Check app status
doctl apps get $APP_ID

# View runtime logs
doctl apps logs $APP_ID --component web --type runtime
```

## Post-Deployment Configuration

### 1. Verify Korean Market Optimization

**Test from Korea (or Korean VPN):**
```bash
# Test latency to Singapore
curl -w "@curl-format.txt" -o /dev/null -s https://your-app.ondigitalocean.app

# Test tournament list loading
curl -H "Accept: application/json" https://your-app.ondigitalocean.app/api/tournaments

# Test UTF-8 Korean character support
curl -d "title=한국어 토너먼트 테스트" https://your-app.ondigitalocean.app/api/tournaments
```

### 2. Configure osu! OAuth Application

**osu! OAuth Settings:**
- **Application Name:** Tourney Method
- **Application URL:** https://your-app.ondigitalocean.app
- **Callback URL:** https://your-app.ondigitalocean.app/api/auth/callback

### 3. Set Up Custom Domain (Production)

**DNS Configuration:**
```
Type: CNAME
Name: @
Value: your-app.ondigitalocean.app

Type: CNAME  
Name: www
Value: your-app.ondigitalocean.app
```

**SSL Certificate:**
- Automatically managed by App Platform
- Let's Encrypt certificates auto-renewed
- Force HTTPS enabled by default

## Monitoring and Maintenance

### 1. App Platform Built-in Monitoring

**Available Metrics:**
- **Response Time** - Monitor Korean user experience
- **Request Volume** - Track tournament discovery usage  
- **Error Rate** - Monitor application health
- **CPU/Memory Usage** - App Platform provides automatic scaling

**Dashboard Access:**
- DigitalOcean Apps dashboard
- Real-time logs and metrics
- Automatic alerts for downtime

### 2. Tournament Parser Monitoring

**Scheduled Job Monitoring:**
```bash
# View parser job logs
doctl apps logs $APP_ID --component tournament-parser

# Check job execution history
doctl apps get $APP_ID --format ID,Name,UpdatedAt,Phase
```

**Parser Health Check:**
```php
// scripts/monitoring/parser_health_check.php
<?php
$lastRun = file_get_contents('/tmp/last_parser_run.timestamp');
$timeSinceRun = time() - (int)$lastRun;

if ($timeSinceRun > 86400) { // 24 hours
    // Send alert - parser may be failing
    error_log("ALERT: Tournament parser hasn't run in {$timeSinceRun} seconds");
}
```

### 3. Korean Performance Monitoring

**Performance Testing Script:**
```bash
#!/bin/bash
# Test performance from Korean perspective

echo "=== Korean Market Performance Test ==="
echo "Testing from: $(curl -s ipinfo.io/region)"

# Test homepage
HOMEPAGE_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://tourneymethod.com/)
echo "Homepage load time: ${HOMEPAGE_TIME}s (Target: <2s)"

# Test API
API_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://tourneymethod.com/api/tournaments)  
echo "API response time: ${API_TIME}s (Target: <500ms)"

# Test from Seoul specifically (if using Korean testing service)
SEOUL_TIME=$(curl -w "%{time_total}" -o /dev/null -s --connect-to tourneymethod.com:443:seoul-test-endpoint.com:443 https://tourneymethod.com/)
echo "Seoul connection time: ${SEOUL_TIME}s"
```

## Scaling and Cost Management

### 1. Automatic Scaling Configuration

**Traffic-Based Scaling:**
```yaml
# Update .do/app.yaml for scaling
services:
  - name: web
    instance_count: 1
    instance_size_slug: basic-xxs  # Start small
    autoscaling:
      min_instance_count: 1
      max_instance_count: 3  # Scale up during tournament seasons
      metrics:
        cpu_threshold_percent: 70
        memory_threshold_percent: 70
```

### 2. Cost Optimization

**Monthly Cost Breakdown:**
```
Basic Web Service: $5/month
Scheduled Job (Parser): $0 (included)
SSL Certificate: $0 (automatic)
CDN: $0 (included)
Monitoring: $0 (included)
---
Total: $5/month ($60/year)
Savings vs Droplet: $84/year (60% reduction)
```

**Future Scaling Costs:**
- **Professional ($12/month):** If traffic increases significantly
- **Managed Database ($15/month):** When SQLite becomes insufficient  
- **Additional Workers ($5/month each):** For background job scaling

### 3. Database Scaling Strategy

**Current Phase:** SQLite on persistent storage (included)
**Phase 2:** Upgrade to managed PostgreSQL when needed:
```yaml
# Future database configuration
databases:
  - engine: PG
    name: tournaments-db
    node_count: 1
    size: db-s-1vcpu-1gb  # $15/month managed PostgreSQL
    version: "13"
```

## Backup and Recovery

### 1. Automatic Backups

**App Platform Backups:**
- **Application Code:** Backed up via Git repository
- **Database:** SQLite file on persistent storage
- **Configuration:** Environment variables backed up in dashboard

**Manual Backup Script:**
```php
// scripts/maintenance/backup_to_spaces.php
<?php
/**
 * Backup SQLite database to DigitalOcean Spaces
 * Run weekly via scheduled job
 */
$dbBackup = '/tmp/tournaments_backup_' . date('Y-m-d_H-i-s') . '.db';
copy('/tmp/tournaments.db', $dbBackup);

// Upload to Spaces (if configured)
// $spaces->uploadFile($dbBackup, 'backups/');

echo "Backup created: {$dbBackup}\n";
```

### 2. Rollback Procedures

**Code Rollback:**
```bash
# Rollback to previous deployment
doctl apps create-deployment $APP_ID --wait

# Or rollback to specific commit
git revert HEAD~1
git push origin main  # Triggers auto-deployment
```

**Database Rollback:**
- Download backup from persistent storage
- Deploy rollback script via temporary deployment
- Restore database file and restart application

## Security Considerations

### 1. App Platform Security Features

**Built-in Security:**
- **Automatic HTTPS:** All traffic encrypted
- **DDoS Protection:** Built-in protection against attacks
- **Network Isolation:** Apps run in isolated environments
- **Automatic Security Updates:** Platform handles OS updates

**Additional Security Headers:**
```php
// src/utils/SecurityHelper.php - Enhanced for App Platform
public static function applySecurityHeaders(): void {
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
```

### 2. Environment Variable Security

**Secure Secret Management:**
- OAuth secrets encrypted at rest
- Secrets not visible in logs or deployment history
- Automatic secret rotation support
- Access control via DigitalOcean team permissions

## Troubleshooting

### Common App Platform Issues

**1. Deployment Failures**
```bash
# Check build logs
doctl apps logs $APP_ID --component web --type build

# Check for composer issues
doctl apps logs $APP_ID --component web | grep composer
```

**2. Runtime Errors**
```bash
# Check runtime logs
doctl apps logs $APP_ID --component web --type runtime

# Check PHP errors
doctl apps logs $APP_ID --component web | grep "PHP Error"
```

**3. Database Connection Issues**
```bash
# Verify database file exists
doctl apps logs $APP_ID --component web | grep "tournaments.db"

# Check permissions
doctl apps logs $APP_ID --component web | grep "Permission denied"
```

**4. Korean Performance Issues**
```bash
# Test CDN performance
curl -H "CF-IPCountry: KR" -w "%{time_total}" https://tourneymethod.com/

# Check Singapore region deployment
doctl apps get $APP_ID | grep region
```

### Performance Optimization

**1. Korean User Experience**
```php
// Korean-specific optimizations in public/.htaccess
<IfModule mod_headers.c>
    # Cache static assets for Korean users
    Header set Cache-Control "public, max-age=31536000" "expr=%{REQUEST_URI} =~ m#\.(js|css|png|jpg|jpeg|gif|ico|woff|woff2)$#"
    
    # Optimize for Korean mobile networks
    Header set Connection "keep-alive"
    Header set Keep-Alive "timeout=5, max=1000"
</IfModule>
```

**2. Database Query Optimization**
```php
// Optimize for Korean timezone queries
CREATE INDEX idx_tournaments_korean_time ON tournaments(created_at, updated_at) 
WHERE datetime(created_at) >= datetime('now', '-7 days', '+9 hours');
```

This App Platform deployment provides a cost-effective, scalable solution optimized for Korean osu! tournament discovery while reducing operational complexity and costs by 60% compared to traditional server deployment.