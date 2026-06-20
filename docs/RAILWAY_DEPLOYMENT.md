# Railway Deployment Guide

**Last Updated:** 2026-03-31

## Overview

This guide covers deploying Tourney Method to Railway.app with proper configuration for scheduled jobs, queue workers, and monitoring.

## Architecture

The application runs as **3 separate services** on Railway:

1. **Web Service** - Handles HTTP requests (port from `$PORT`)
2. **Railway Cron Jobs** - Run scheduled Laravel commands only when due
3. **Horizon Service** - Processes queue jobs

---

## Prerequisites

### Required Accounts & Services

- **Railway Account** - https://railway.app/
- **osu! OAuth Application** - https://osu.ppy.sh/home/account/oauth
- **Cloudflare R2** (optional) - For banner storage and database backups

### Required Secrets

Gather these credentials before deploying:

**Required:**
- `OSU_CLIENT_ID` - From osu! OAuth application
- `OSU_CLIENT_SECRET` - From osu! OAuth application
- `MASTER_OSU_ID` - Your osu! user ID for admin access

**Optional (but recommended):**
- `AWS_ACCESS_KEY_ID` - Cloudflare R2 access key
- `AWS_SECRET_ACCESS_KEY` - Cloudflare R2 secret key
- `AWS_BUCKET` - R2 bucket name
- `AWS_ENDPOINT` - R2 endpoint URL
- `AWS_URL` - R2 public URL
- `BACKUP_DISCORD_WEBHOOK` - Discord webhook for backup notifications

---

## Step-by-Step Deployment

### 1. Install Railway CLI

```bash
npm install -g @railway/cli
```

### 2. Login to Railway

```bash
railway login
```

This will open a browser for authentication.

### 3. Create New Project

```bash
# Navigate to project directory
cd tourney-method

# Initialize Railway project
railway init

# Or link to existing project
railway link
```

### 4. Add Required Plugins

```bash
# Add PostgreSQL database
railway add postgresql

# Add Redis for cache/queue
railway add redis
```

Railway will automatically provide these environment variables:
- `DATABASE_URL` - PostgreSQL connection string
- `REDIS_URL` - Redis connection string
- `PORT` - Web service port (typically 3000)

### 5. Configure Environment Variables

Access Railway's variable dashboard:
```bash
railway variables
```

Or set via CLI:
```bash
railway variables set APP_NAME="Tourney Method"
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false
```

#### Required Variables

```bash
# Laravel Configuration
APP_NAME="Tourney Method"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:generate-with-php-artisan-key-generate
APP_URL=https://your-app-name.railway.app
APP_TIMEZONE=Asia/Seoul

# Database (Railway provides DATABASE_URL automatically)
DB_CONNECTION=pgsql

# PostgreSQL client tools for scheduled backups/restores
RAILPACK_DEPLOY_APT_PACKAGES="postgresql-client-18 libpq-dev"

# Cache & Queue (Railway provides REDIS_URL automatically)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Laravel Horizon
HORIZON_DARK_MODE=true
HORIZON_PREFIX=horizon:
HORIZON_USER_MAX_PROCESSES=1
HORIZON_ADMIN_MAX_PROCESSES=1
HORIZON_WORKER_MEMORY=128
HORIZON_TRIM_RECENT=15
HORIZON_TRIM_PENDING=15
HORIZON_TRIM_COMPLETED=15
HORIZON_TRIM_RECENT_FAILED=1440
HORIZON_TRIM_FAILED=1440
HORIZON_TRIM_MONITORED=1440
HORIZON_METRIC_SNAPSHOTS=12

# osu! OAuth (REQUIRED)
OSU_CLIENT_ID=your-osu-client-id
OSU_CLIENT_SECRET=your-osu-client-secret
OSU_REDIRECT_URI=https://your-app-name.railway.app/auth/callback

# Master User (REQUIRED)
MASTER_OSU_ID=757783

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

#### Optional Variables (Cloudflare R2)

```bash
# Filesystem (for banner storage)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-r2-access-key-id
AWS_SECRET_ACCESS_KEY=your-r2-secret-access-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=tourney-method-banners
AWS_ENDPOINT=https://your-r2-endpoint.r2.cloudflarestorage.com
AWS_URL=https://your-r2-url.r2.dev

# Database Backup
BACKUP_ENABLED=true
BACKUP_DISK=s3
BACKUP_DIRECTORY=backups
BACKUP_RETENTION_DAYS=7
BACKUP_VERIFY_UPLOAD=true
BACKUP_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR_WEBHOOK
```

#### Optional Variables (External APIs)

```bash
# Twitch API (for stream checks)
TWITCH_CLIENT_ID=your-twitch-client-id
TWITCH_CLIENT_SECRET=your-twitch-client-secret

# TCOMM API (tournament data)
TCOMM_API_KEY=your-tcomm-api-key

# O!TR API (tournament results)
OTR_API_KEY=your-otr-api-key

# Discord Webhooks (for notifications)
DISCORD_STD_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_STD_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_TAIKO_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_TAIKO_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_CATCH_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_CATCH_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_MANIA_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_MANIA_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...
```

### 6. Deploy to Railway

```bash
# Deploy all services
railway up

# Monitor deployment logs
railway logs --follow

# Check service status
railway status
```

Railway will:
1. Detect `railway.toml` configuration
2. Install runtime packages from `RAILPACK_DEPLOY_APT_PACKAGES`
3. Run build command: `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
4. Start the configured service

Set `RAILPACK_DEPLOY_APT_PACKAGES` in Railway's service Variables tab for every service that builds this repo. The scheduler service must use an image where `pg_dump` and `psql` are available at runtime. Do not install PostgreSQL client tools in `preDeployCommand`; Railway runs pre-deploy in a separate container and discards filesystem changes before the application starts.

The cron runner also includes a runtime fallback that installs PostgreSQL 18 client tools before running scheduled commands if the image does not already contain them. If that fallback reports that the container is not running as root, set the Railpack variable above in Railway or switch to a custom Dockerfile.

### 7. Generate APP_KEY

After deployment is running:

```bash
# Open Railway console
railway open console

# Or use shell
railway shell

# Generate APP_KEY
php artisan key:generate

# Copy the generated key and set it in Railway variables
railway variables set APP_KEY=base64:copied-key-here
```

### 8. Run Database Migrations

```bash
railway shell
php artisan migrate --force
exit
```

### 9. Verify Deployment

```bash
# Check health endpoint
curl https://your-app-name.railway.app/health

# Expected response:
# {
#   "status": "healthy",
#   "checks": {
#     "database": {"status": "connected"},
#     "redis": {"status": "connected"},
#     "horizon": {"status": "running"}
#   }
# }
```

Verify PostgreSQL 18 client tools are present in the scheduler image:

```bash
railway shell --service scheduler
pg_dump --version
psql --version
php artisan schedule:list
exit
```

Both PostgreSQL client commands should report version `18.x`.

---

## Scheduled Jobs

Use one of these scheduler modes:

- Always-on Scheduler service: set the Railway service start command to `bash ./railway/run-scheduler.sh`. The service stays online and Laravel runs due tasks through `php artisan schedule:work`.
- Railway Cron: configure a Railway Cron interval and use `bash ./railway/run-cron.sh`. The service runs due tasks once with `php artisan schedule:run`, exits, and shows as completed.

Do not use `sh ./railway/run-cron.sh` as a normal always-on service start command; it runs once and exits.

| Command | Schedule | Description |
|---------|----------|-------------|
| `reminders:registration` | Every hour | Check for tournaments with registration closing within 24h |
| `notifications:eligible-tournaments` | Every hour | Queue in-app tournament notifications |
| `streams:check` | Every 3 minutes | Poll for newly live streams |
| `tournaments:parse --confirm` | Daily at 09:00 KST | Parse tournaments from osu! forums |
| `maintenance:weekly-essential` | Weekly on Mondays 02:00 KST | Sync podium users, sync tournament badges, clean orphaned users |

Example Railway Cron start commands:

```bash
./railway/run-cron.sh php artisan streams:check
./railway/run-cron.sh php artisan reminders:registration
./railway/run-cron.sh php artisan notifications:eligible-tournaments
./railway/run-cron.sh php artisan tournaments:parse --confirm
./railway/run-cron.sh php artisan maintenance:weekly-essential
```

### Verify Scheduled Tasks

```bash
# Check cron logs
railway logs --service streams-check --follow

# Should see output like:
# Running cron command from arguments: php artisan tournaments:parse --confirm
# [2026-03-31 09:00:15] Command completed successfully
```

---

## Queue Workers (Horizon)

The Horizon service processes background jobs:

- **Tournament parsing jobs** (4-stage pipeline)
- **User profile sync jobs**
- **SIP fetch jobs**
- **Notification jobs**
- **Badge import jobs**

### Monitoring Horizon

Access Horizon dashboard at:
```
https://your-app-name.railway.app/horizon
```

**What to monitor:**
- Job throughput (jobs/minute)
- Failed jobs (with retry options)
- Worker status (active/paused)
- Batch job status
- Recent job history
- Metrics and trends

### Horizon Configuration

Production uses two one-process lanes so user-facing osu! sync cannot be stuck behind admin parse/import work:

- **user-supervisor:** `osu-user`
- **admin-supervisor:** `osu-admin-priority`, `osu-admin`, `default`, `imports`
- **Memory per Worker:** 128MB by default
- **Timeout:** 60 seconds
- **Tries:** 1
- **Trim Recent/Pending/Completed:** 15 minutes
- **Trim Failed/Monitored:** 1 day

All osu! API callers still share the Redis-backed `osu_api_request_slot` lock.

---

## Health Checks

The web service includes a health check endpoint at `/health`.

### Health Check Response (200 OK)

```json
{
  "status": "healthy",
  "timestamp": "2026-03-31T12:00:00Z",
  "app": "Tourney Method",
  "environment": "production",
  "checks": {
    "database": {
      "status": "connected",
      "connection": "pgsql"
    },
    "redis": {
      "status": "connected",
      "connection": "redis"
    },
    "horizon": {
      "status": "running"
    }
  }
}
```

### Health Check Response (503 Unhealthy)

```json
{
  "status": "unhealthy",
  "checks": {
    "database": {
      "status": "error",
      "error": "Connection refused"
    }
  }
}
```

Railway automatically restarts services that fail health checks.

---

## Monitoring & Logging

### Application Logs

View logs via Railway CLI:

```bash
# Follow all service logs
railway logs --follow

# View specific service logs
railway logs --service web --follow
railway logs --service scheduler --follow
railway logs --service horizon --follow

# View last 100 lines
railway logs --lines 100
```

### Log Levels

- **Development:** `debug` level (all logs)
- **Production:** `warning` level (errors and warnings only)

Configure via `LOG_LEVEL` environment variable.

---

## Database Backups

### Automatic Backups

Backups are automatically created daily at 08:55 UTC and uploaded to Cloudflare R2.

**Retention:** 7 days (configurable via `BACKUP_RETENTION_DAYS`)

### Manual Backup

```bash
railway shell
php artisan backups:create --description "Before migration"
exit
```

### List Backups

```bash
railway shell
php artisan backups:list
exit
```

### Restore Backup

```bash
railway shell
php artisan backups:restore backups/tourney_method_2026-03-31_09-00-00.sql.gz
exit
```

**⚠️ WARNING:** Restoring will replace the entire database!

---

## Troubleshooting

### Cron Jobs Not Running

**Symptoms:** Scheduled tasks not executing

**Solutions:**
```bash
# Check the relevant cron service logs
railway logs --service streams-check --follow

# Verify Laravel can list schedules
railway shell --service web
php artisan schedule:list
```

### Horizon Not Processing Jobs

**Symptoms:** Jobs stuck in pending status

**Solutions:**
```bash
# Check Horizon service logs
railway logs --service horizon --follow

# Restart Horizon
railway shell --service horizon
php artisan horizon:terminate

# Verify Redis connection
railway shell
php artisan tinker --execute="Redis::ping();"
```

### Database Connection Issues

**Symptoms:** Health check returns database errors

**Solutions:**
```bash
# Verify DATABASE_URL is set
railway variables

# Test connection
railway shell
php artisan db:show

# Check PostgreSQL plugin is running
railway status
```

### Out of Memory Errors

**Symptoms:** Services crashing with OOM

**Solutions:**
1. Increase service memory limit in Railway dashboard
2. Reduce Horizon maxProcesses in `config/horizon.php`
3. Check for memory leaks in jobs

### High CPU Usage

**Symptoms:** Horizon using too much CPU

**Solutions:**
1. Reduce `maxProcesses` in Horizon configuration
2. Check for jobs stuck in infinite loops
3. Optimize job queries and API calls

---

## Performance Optimization

### Recommended Service Configuration

**Web Service:**
- **RAM:** 512MB - 1GB
- **CPU:** 0.5 - 1 vCPU
- **Instances:** 1 (scale based on traffic)

**Cron Services:**
- **RAM:** 256MB
- **CPU:** 0.1 - 0.25 vCPU
- **Instances:** 0 when idle; each cron run exits after command completion

**Horizon Service:**
- **RAM:** 512MB
- **CPU:** 0.25 - 0.5 vCPU
- **Instances:** 1

### Caching Strategy

Enable config caching for production:

```bash
railway shell
php artisan config:cache
php artisan route:cache
php artisan view:cache
exit
```

Clear caches when needed:

```bash
railway shell
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
exit
```

---

## Updating the Application

### Deploy Updates

```bash
# Merge latest code
git pull origin main

# Deploy to Railway
railway up

# Monitor deployment
railway logs --follow
```

### Horizon Deployment

After deploying code changes, terminate Horizon to pick up changes:

```bash
railway shell --service horizon
php artisan horizon:terminate
exit
```

Horizon will automatically restart and load the new code.

---

## Cost Estimation

**Monthly Costs (USD):**

| Service | Plan | Estimated Cost |
|---------|------|----------------|
| Web Service | Eco ($5/month) | ~$5 |
| Cron Jobs | Usage-based | Low; only billed while running |
| Horizon Service | Usage-based | Keep at two one-process lanes |
| PostgreSQL | Free Tier | $0 |
| Redis | Free Tier | $0 |
| **Total** | | **~$15/month** |

*Note: Costs vary based on usage and location. See https://railway.app/pricing for details.*

---

## Security Best Practices

1. **Never commit secrets** - Always use Railway environment variables
2. **Enable HTTPS** - Railway provides automatic SSL certificates
3. **Set APP_DEBUG=false** - Prevent sensitive error information exposure
4. **Limit Horizon access** - Add authentication in production (currently open)
5. **Rotate secrets regularly** - Update API keys and passwords periodically
6. **Monitor logs** - Set up alerts for suspicious activity
7. **Use strong APP_KEY** - Generate with `php artisan key:generate`
8. **Keep dependencies updated** - Run `composer update` regularly

---

## Post-Deployment Checklist

- [ ] All 3 services running (web, scheduler, horizon)
- [ ] Health check returns 200 OK
- [ ] Database migrations completed successfully
- [ ] Environment variables configured correctly
- [ ] osu! OAuth working (test login flow)
- [ ] Horizon dashboard accessible
- [ ] Scheduled jobs running (check logs)
- [ ] Queue worker processing jobs
- [ ] Backups uploading to R2 (if configured)
- [ ] Logs show no critical errors
- [ ] SSL certificate active (HTTPS)
- [ ] Timezone correctly set (Asia/Seoul)
- [ ] APP_KEY is set and secure
- [ ] APP_DEBUG=false in production

---

## Rollback Procedure

If deployment causes issues:

### Option 1: Revert Code

```bash
# Revert to previous commit
git revert HEAD

# Redeploy
railway up
```

### Option 2: Use Railway's Rollback

```bash
# View deployment history
railway logs

# Rollback to previous deployment
railway rollback
```

### Option 3: Restore Database

```bash
railway shell
php artisan backups:restore backups/tourney_method_2026-03-31_09-00-00.sql.gz
exit
```

---

## Additional Resources

- **Railway Documentation:** https://docs.railway.app/
- **Laravel Deployment:** https://laravel.com/docs/deployment
- **Laravel Horizon:** https://laravel.com/docs/horizon
- **Laravel Scheduler:** https://laravel.com/docs/scheduling

---

## Support

For issues specific to this deployment:

1. Check Railway logs: `railway logs --follow`
2. Check Horizon dashboard for job failures
3. Review `docs/RAILWAY_ISSUES.md` for known issues
4. Check Laravel logs: `railway shell` → `tail storage/logs/laravel.log`

For Railway platform issues:
- Railway Discord: https://discord.gg/railway
- Railway GitHub: https://github.com/railwayapp

---

**Deployment Status:** ✅ Ready for Railway deployment

**Last Review:** 2026-03-31
**Issues Fixed:** All critical issues resolved
