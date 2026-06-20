# Tourney Method Runbook

**Last Updated:** 2026-03-26
**PHP Version:** 8.5.1
**Laravel Version:** 11.47.0
**Pest Version:** 4.3.1
**Livewire Version:** 3.7.3

## Deployment Procedures

### Initial Deployment

#### 1. Server Setup

**Requirements:**
- Ubuntu 22.04 LTS (or similar)
- 2GB RAM minimum (4GB recommended)
- 20GB disk space
- Docker & Docker Compose installed
- PostgreSQL 18+
- Redis 7+

**Install Docker:**
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

**Install Docker Compose:**
```bash
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

#### 2. Application Deployment

```bash
# Clone repository
git clone https://github.com/your-username/tourney-method.git
cd tourney-method

# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

**Critical Environment Variables:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:generated_key_here

# Database
DB_CONNECTION=pgsql
DB_HOST=your_db_host
DB_DATABASE=tourney_method
DB_USERNAME=your_db_user
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=your_redis_host
REDIS_PASSWORD=your_redis_password

# osu! OAuth
OSU_CLIENT_ID=your_client_id
OSU_CLIENT_SECRET=your_client_secret
OSU_REDIRECT_URI=https://yourdomain.com/auth/callback

# Master User
MASTER_OSU_ID=your_osu_user_id
```

#### 3. Build & Start Containers

```bash
# Build containers
docker compose build

# Start services
docker compose up -d

# Run migrations
docker compose exec -u sail laravel.test php artisan migrate --force

# Cache configuration
docker compose exec -u sail laravel.test php artisan config:cache
docker compose exec -u sail laravel.test php artisan route:cache
docker compose exec -u sail laravel.test php artisan view:cache

# Create admin user
docker compose exec -u sail laravel.test php artisan tinker
>>> User::create(['osu_id' => 123456, 'username' => 'admin', 'role' => 'admin']);
```

### Updating Application

#### Standard Update

```bash
# SSH into server
ssh user@server

# Navigate to project
cd /var/www/tourney-method

# Pull latest code
git pull origin main

# Install dependencies
docker compose exec -u sail laravel.test composer install --no-dev
docker compose exec -u sail laravel.test npm run build

# Run migrations
docker compose exec -u sail laravel.test php artisan migrate --force

# Clear and cache
docker compose exec -u sail laravel.test php artisan cache:clear
docker compose exec -u sail laravel.test php artisan config:cache
docker compose exec -u sail laravel.test php artisan route:cache

# Restart Horizon
docker compose exec -u sail laravel.test php artisan horizon:terminate
```

#### Zero-Downtime Deployment

```bash
# Build new containers before stopping old ones
docker compose up -d --build --no-deps laravel.test

# Wait for health check
sleep 30

# Remove old containers
docker compose up -d --remove-orphans
```

## Monitoring & Maintenance

### Railway Monthly Cost Review

Check these once per billing cycle, and again 24-48 hours after changing Railway resource limits:

```bash
# Horizon should keep user sync separate from admin parsing/imports
docker compose exec -u sail laravel.test php artisan horizon:status

# Redis memory should stay small because parse handoff data lives in Postgres
docker compose exec redis redis-cli INFO memory

# Review Laravel's current scheduled commands before changing Railway Cron jobs
docker compose exec -u sail laravel.test php artisan schedule:list
```

- In Railway metrics, compare App, Horizon, Redis, and Postgres memory trends.
- In Horizon, check that `osu-user` queue wait stays low during admin parser runs.
- Confirm failed jobs did not increase after `tournaments:parse --confirm`.
- Keep Horizon at two total workers unless there is clear evidence user sync is unaffected by admin workloads.

### Laravel Horizon (Queue Monitoring)

**Access:** https://yourdomain.com/horizon

**Key Metrics:**
- Jobs per minute
- Failed jobs
- Queue depth
- Worker status

**Common Tasks:**

```bash
# Check Horizon status
docker compose exec -u sail laravel.test php artisan horizon:status

# Pause Horizon
docker compose exec -u sail laravel.test php artisan horizon:pause

# Resume Horizon
docker compose exec -u sail laravel.test php artisan horizon:continue

# Restart Horizon
docker compose exec -u sail laravel.test php artisan horizon:terminate

# Clear failed jobs
docker compose exec -u sail laravel.test php artisan horizon:clear
```

### Application Logs

**Log Location:** `storage/logs/laravel.log`

**View Logs:**
```bash
# Follow logs in real-time
docker compose exec -u sail laravel.test tail -f storage/logs/laravel.log

# View last 100 lines
docker compose exec -u sail laravel.test tail -n 100 storage/logs/laravel.log

# Search for errors
docker compose exec -u sail laravel.test grep -i "error" storage/logs/laravel.log
```

**Log Levels:**
- `emergency` - System is unusable
- `alert` - Immediate action required
- `critical` - Critical conditions
- `error` - Runtime errors
- `warning` - Warning messages
- `notice` - Normal but significant
- `info` - Informational messages
- `debug` - Debug-level messages

### Database Maintenance

**Backup Database:**
```bash
# Backup to file
docker compose exec pgsql pg_dump -U sail tourney_method > backup_$(date +%Y%m%d).sql

# Compress backup
gzip backup_$(date +%Y%m%d).sql

# Upload to S3 (optional)
aws s3 cp backup_$(date +%Y%m%d).sql.gz s3://backups/tourney-method/
```

**Restore Database:**
```bash
# Decompress backup
gunzip backup_20260205.sql.gz

# Restore from file
docker compose exec -T pgsql psql -U sail tourney_method < backup_20260205.sql
```

**Database Optimization:**
```bash
# Analyze tables
docker compose exec -u sail laravel.test php artisan db:table tournaments --analyze

# Vacuum PostgreSQL
docker compose exec pgsql psql -U sail tourney_method -c "VACUUM ANALYZE;"
```

### Redis Maintenance

**Cache Management:**
```bash
# Clear all cache
docker compose exec -u sail laravel.test php artisan cache:clear

# Clear specific cache keys
docker compose exec redis redis-cli KEYS "osu_user:*" | xargs docker compose exec redis redis-cli DEL

# Monitor Redis memory
docker compose exec redis redis-cli INFO memory
```

**Redis Persistence:**
```bash
# Save current state
docker compose exec redis redis-cli SAVE

# Check persistence
docker compose exec redis redis-cli LASTSAVE
```

## Common Issues & Fixes

### High Queue Depth

**Symptoms:** Jobs not processing fast enough

**Diagnosis:**
```bash
# Check queue depth
docker compose exec -u sail laravel.test php artisan queue:monitor

# Check Horizon metrics
# Visit /horizon -> Metrics
```

**Solutions:**
1. **Scale Workers** - Edit `config/horizon.php`
   ```php
   'environments' => [
       'production' => [
           'supervisor-1' => [
               'connection' => 'redis',
               'queue' => ['default'],
               'balance' => 'simple',
               'processes' => 10, // Increase this
               'tries' => 3,
           ],
       ],
   ],
   ```

2. **Optimize Jobs** - Batch operations, reduce API calls

3. **Add More Supervisors** - Separate queues by priority

### External API Failures

**Symptoms:** Jobs failing with API errors

**Diagnosis:**
```bash
# Check failed jobs
docker compose exec -u sail laravel.test php artisan queue:failed

# View specific error
docker compose exec -u sail laravel.test php artisan queue:failed table
```

**Solutions:**
1. **Check API Keys** - Verify `.env` credentials
2. **Check Rate Limits** - osu! API: 60 req/min
3. **Retry Failed Jobs** - `docker compose exec -u sail laravel.test php artisan queue:retry all`

### Memory Issues

**Symptoms:** OOM errors, slow performance

**Diagnosis:**
```bash
# Check container memory usage
docker stats

# Check PHP memory limit
docker compose exec -u sail laravel.test php -i | grep memory_limit
```

**Solutions:**
1. **Increase PHP Memory** - Edit `php.ini`
   ```ini
   memory_limit = 256M
   ```

2. **Optimize Queries** - Add indexes, use eager loading

3. **Clear Cache** - `docker compose exec -u sail laravel.test php artisan cache:clear`

### Horizon Not Processing Jobs

**Symptoms:** Jobs stuck in pending

**Diagnosis:**
```bash
# Check Horizon status
docker compose exec -u sail laravel.test php artisan horizon:status

# Check worker logs
docker compose logs laravel.test -f
```

**Solutions:**
1. **Restart Horizon** - `docker compose exec -u sail laravel.test php artisan horizon:terminate`

2. **Check Redis** - Ensure Redis is running
   ```bash
   docker compose exec redis redis-cli PING
   ```

3. **Clear Queue Locks**
   ```bash
   docker compose exec redis redis-cli DEL horizon:lock:supervisor
   ```

## Rollback Procedures

### Application Rollback

```bash
# View recent commits
git log --oneline -10

# Rollback to specific commit
git checkout <commit-hash>

# Rebuild containers
docker compose up -d --build

# Run migrations (if needed)
docker compose exec -u sail laravel.test php artisan migrate:rollback --step=1

# Clear cache
docker compose exec -u sail laravel.test php artisan cache:clear
```

### Database Rollback

```bash
# Rollback last migration
docker compose exec -u sail laravel.test php artisan migrate:rollback

# Rollback multiple migrations
docker compose exec -u sail laravel.test php artisan migrate:rollback --step=5

# Restore from backup (if needed)
docker compose exec -T pgsql psql -U sail tourney_method < backup_file.sql
```

### Emergency Rollback (Full)

```bash
# Stop all services
docker compose down

# Restore database backup
docker compose exec -T pgsql psql -U sail tourney_method < backup_$(date +%Y%m%d).sql

# Checkout previous commit
git checkout previous-stable-commit

# Start services
docker compose up -d

# Verify application
curl https://yourdomain.com
```

## Scheduled Tasks

### Laravel Scheduler

**Setup Crontab:**
```bash
crontab -e

# Add this line:
* * * * * cd /var/www/tourney-method && docker compose exec -u sail laravel.test php artisan schedule:run
```

**Scheduled Tasks:**
- `backups:create` - **Database backup** (daily at 08:55 UTC)
- `tournaments:parse` - Parse tournaments from osu! forum (daily at 09:00 UTC)
- `reminders:registration` - Send registration deadline reminders (hourly)
- `streams:check` - Check for live Twitch streams (every 3 minutes)
- `queue:prune-failed` - Clean up failed jobs (daily)
- `queue:prune-batches` - Clean up old batches (weekly)

### Custom Scheduled Tasks

**Adding New Task:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        // Your task logic
    })->daily();
}
```

## Security Hardening

### SSL/HTTPS

**Using Let's Encrypt:**
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal (configured automatically)
sudo certbot renew --dry-run
```

### Firewall Configuration

```bash
# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
```

### Environment Security

**Never Commit:**
- `.env` file
- API keys
- Database passwords
- OAuth secrets

**Rotate Secrets Regularly:**
```bash
# Generate new APP_KEY
docker compose exec -u sail laravel.test php artisan key:generate

# Update OAuth tokens
# Visit osu! developer portal, regenerate credentials
```

## Performance Tuning

### Database Optimization

**Add Indexes:**
```php
// In migration file
$table->index(['status', 'created_at']);
$table->foreignId('user_id')->index();
```

**Query Optimization:**
```bash
# Analyze slow queries
docker compose exec -u sail laravel.test php artisan db:table tournaments --explain
```

### PHP Optimization

**OPcache Settings (php.ini):**
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### HTTP Caching

**Enable Browser Caching:**
```php
// In controller
return response()
    ->json($data)
    ->header('Cache-Control', 'public, max-age=3600');
```

## Backup Strategy

### Automated Database Backups

**Storage:** Cloudflare R2 (S3-compatible object storage)

**Schedule:**
- **Daily scheduled backup:** 08:55 UTC (5 minutes before tournament parsing at 09:00 UTC)
- **Pre-parsing backup:** Automatic backup before `tournaments:parse` command
- **Retention:** 7 days (configurable via `BACKUP_RETENTION_DAYS`)

**Backup Format:**
- **Location:** `backups/tourney_method_{YYYY-MM-DD_HH-MM-SS}.sql.gz`
- **Size:** ~600 KB (compressed)
- **Method:** PostgreSQL `pg_dump` with gzip compression

**Manual Backup Commands:**
```bash
# Create immediate backup
docker compose exec -u sail laravel.test php artisan db:backup --description "Before migration"

# List available backups
docker compose exec -u sail laravel.test php artisan db:list --all

# Restore from backup
docker compose exec -u sail laravel.test php artisan db:restore backups/tourney_method_2026-03-09_08-55-00.sql.gz

# Clean old backups
docker compose exec -u sail laravel.test php artisan db:clean --days=7 --dry-run
```

**Environment Configuration:**
```bash
BACKUP_ENABLED=true
BACKUP_DISK=s3
BACKUP_DIRECTORY=backups
BACKUP_RETENTION_DAYS=7
BACKUP_VERIFY_UPLOAD=true
BACKUP_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR_WEBHOOK_URL
```

**R2 Storage Requirements:**
- Cloudflare R2 bucket configured
- AWS credentials in environment (from Railway dashboard)
- S3-compatible endpoint configured in `config/filesystems.php`

**Verification:**
- Automatic upload verification after backup
- Discord webhook notifications for success/failure
- File size and duration tracking

### SIP Integration Setup

**Purpose:** Sync Skill Issue Percentage (SIP) stats for osu!standard podium winners via Google Sheets → skillissue.app API bridge

**Architecture:**
```
Laravel (SipService) → SipFetchQueue → Google Sheets Apps Script
                                          ↓
                            skillissue.app API (SIP data)
                                          ↓
                            Laravel (/api/sip/update) → users.sip
```

**Environment Configuration:**
```bash
# Generate token with: php -r "echo bin2hex(random_bytes(32));"
SIP_API_TOKEN=your_generated_token_here
```

**SIP Endpoints (secured by X-SIP-API-Token header):**
- `GET /api/sip/queue` - Returns pending users (limit 50), marks as processing
- `POST /api/sip/update` - Accepts SIP results from Google Sheets

**Setup Google Sheets Apps Script:**
1. Create new Google Sheet
2. Open Extensions → Apps Script
3. Add code (see SIP_INTEGRATION_SUMMARY.md for full code)
4. Run `onOpen()` to create "SIP Sync" menu
5. Click "SIP Sync → Sync All Users" to process queue

**Authentication Flow:**
1. Laravel generates SIP_API_TOKEN (stored in .env)
2. Google Sheets sends token in `X-SIP-API-Token` header
3. Laravel verifies token matches before returning queue/accepting results
4. The skillissue.app API does NOT require this token (Google Apps Script context only)

**Manual Commands:**
```bash
# Queue podium users for SIP fetch (2025-2026)
docker compose exec -u sail laravel.test php artisan sync:podium-users --start-year=2025 --end-year=2026

# Check pending queue count
docker compose exec -u sail laravel.test php artisan tinker --execute="echo (new \App\Services\SipService())->getPendingCount();"

# View failed SIP fetches
docker compose exec -u sail laravel.test php artisan tinker --execute="dd((new \App\Services\SipService())->getFailedItems());"
```

**Monitoring:**
- Check `sip_fetch_queue` table for status
- Horizon dashboard shows queue depth
- Laravel logs: `storage/logs/laravel.log`


## Disaster Recovery

### Database Restoration

**⚠️ WARNING:** Database restoration is **destructive** and will **REPLACE** all existing data.

**Step 1: List Available Backups**
```bash
docker compose exec -u sail laravel.test php artisan db:list --all
```

**Step 2: Choose Backup to Restore**
```bash
# Interactive restore (requires confirmation)
docker compose exec -u sail laravel.test php artisan db:restore backups/tourney_method_2026-03-09_08-55-00.sql.gz

# Force restore (skips confirmation - use with caution!)
docker compose exec -u sail laravel.test php artisan db:restore backups/tourney_method_2026-03-09_08-55-00.sql.gz --force
```

**Step 3: Verify Restoration**
```bash
# Check record counts
docker compose exec -u sail laravel.test php artisan tinker --execute="echo 'Users: ' . \App\Models\User::count() . ', Tournaments: ' . \App\Models\Tournament::count();"

# Verify recent activity
docker compose exec -u sail laravel.test php artisan tinker --execute="echo \App\Models\Tournament::latest()->first()->name;"
```

### Recovery Scenarios

**Scenario 1: Tournament Parsing Corruption**
1. Automatic backup created before parsing
2. If parsing fails, automatic restore triggered
3. Manual restore if needed:
   ```bash
   php artisan db:restore backups/tourney_method_pre_parse_YYYY-MM-DD_HH-MM-SS.sql.gz
   ```

**Scenario 2: Accidental Data Deletion**
1. Create emergency backup immediately:
   ```bash
   php artisan db:backup --description "Before restore attempt"
   ```
2. Restore from last known good backup:
   ```bash
   php artisan db:restore backups/tourney_method_YYYY-MM-DD_HH-MM-SS.sql.gz
   ```
3. Verify data integrity
4. Test application functionality

**Scenario 3: Complete Database Loss**
1. Verify R2 storage is accessible
2. List all available backups:
   ```bash
   php artisan db:list --all
   ```
3. Restore from most recent backup:
   ```bash
   php artisan db:restore backups/tourney_method_latest.sql.gz --force
   ```
4. Run data integrity checks
5. Monitor for errors in logs:
   ```bash
   docker compose logs -f laravel.test
   ```

### Backup Verification

**Check Backup Existence:**
```bash
# Verify backup exists in R2
docker compose exec -u sail laravel.test php artisan db:list --days=1
```

**Verify Backup Integrity:**
```bash
# List backups with size (abnormally small = corrupt)
docker compose exec -u sail laravel.test php artisan db:list --all
```

**Test Backup Restoration (Staging):**
1. Create test backup
2. Restore to test environment
3. Verify application works
4. Document successful restore process

4. **Verify Application**
   ```bash
   curl https://yourdomain.com/health
   ```

### Health Checks

**Create Health Check Endpoint:**
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'up' : 'down',
        'redis' => Redis::ping() ? 'up' : 'down',
    ]);
});
```

**Monitoring:**
```bash
# Add to uptime monitoring service
# https://uptimerobot.com or similar
```

## Support & Escalation

### Contact Information
- **Primary Admin:** admin@example.com
- **Emergency Contact:** +1-555-0100
- **GitHub Issues:** https://github.com/your-username/tourney-method/issues

### Escalation Levels
1. **Level 1** - Application restart, cache clear
2. **Level 2** - Database restore, rollback
3. **Level 3** - Server migration, full recovery

---

**Document Version:** 1.0.0
**Last Updated:** 2026-02-05
**Maintained By:** DevOps Team
