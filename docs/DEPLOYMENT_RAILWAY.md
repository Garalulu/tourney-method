# Railway Deployment Guide

**Last Updated:** 2026-03-09

This guide covers deploying the Tourney Method application to Railway (free PaaS platform).

---

## Table of Contents

1. [Why Railway?](#why-railway)
2. [Prerequisites](#prerequisites)
3. [Deployment Steps](#deployment-steps)
4. [Environment Configuration](#environment-configuration)
5. [Cloudflare R2 Setup](#cloudflare-r2-setup)
6. [Post-Deployment Verification](#post-deployment-verification)
7. [Monitoring and Maintenance](#monitoring-and-maintenance)
8. [Troubleshooting](#troubleshooting)

---

## Why Railway?

| Feature | Railway + R2 | DigitalOcean |
|---------|--------------|--------------|
| **Cost** | $0-60/year | $264-1,032/year |
| **Setup Time** | 5 minutes | 2 hours |
| **PostgreSQL** | 1GB free | 80GB ($60/month) |
| **Redis** | 30MB free | 5GB ($15/month) |
| **Object Storage** | 10GB free (R2) | 10GB free (Spaces) |
| **SLA** | Best effort | 99.99% |

**Savings: $1,000+ per year!** 🎉

---

## Prerequisites

1. **Railway Account** - Sign up at https://railway.app (free tier)
2. **GitHub Repository** - Push your code to GitHub
3. **Cloudflare Account** - For R2 banner storage (free tier)
4. **osu! OAuth Application** - Register at https://osu.ppy.sh/home/account/edit

---

## Deployment Steps

### Step 1: Deploy to Railway (5 minutes)

1. **Go to** https://railway.app
2. **Click** "New Project" → "Deploy from GitHub repo"
3. **Select** your tourney-method repository
4. **Railway auto-detects** Laravel and creates:
   - ✅ Web service
   - ✅ PostgreSQL database
   - ✅ Redis instance

**Note:** Your `railway.toml` file configures the 3-service architecture (web + scheduler + horizon).

### Step 2: Verify Service Architecture

Railway should create **3 services**:

1. **Web Service** - Handles HTTP requests
2. **Scheduler Service** - Runs Laravel scheduled tasks
3. **Horizon Service** - Processes queue jobs

If Railway only creates one service, manually add the other two:
- Click "New Service" → "Worker" → Select your repo → Set start command

---

## Environment Configuration

### Step 3: Set Environment Variables

In Railway dashboard, go to your project → "Variables" and add these:

#### Required Variables

```bash
# Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate with: php artisan key:generate>
APP_URL=https://your-app-name.railway.app

# Database (Railway sets DATABASE_URL automatically)
DB_CONNECTION=pgsql

# PostgreSQL client tools for scheduled backups/restores
RAILPACK_DEPLOY_APT_PACKAGES="postgresql-client-18 libpq-dev"

# Cache & Queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# osu! OAuth (Required)
OSU_CLIENT_ID=your-client-id
OSU_CLIENT_SECRET=your-client-secret
OSU_REDIRECT_URI=https://your-app-name.railway.app/auth/callback

# Master User (Required)
MASTER_OSU_ID=your-osu-user-id

# Cloudflare R2 (Required for banner caching)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-r2-access-key
AWS_SECRET_ACCESS_KEY=your-r2-secret-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=tourney-method-banners
AWS_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
```

Set `RAILPACK_DEPLOY_APT_PACKAGES` in Railway's service Variables tab for each app/cron service. The cron runner also has a runtime fallback that installs PostgreSQL 18 client tools if the final image does not already include them.

#### Optional Variables

```bash
# Twitch API
TWITCH_CLIENT_ID=your-twitch-client-id
TWITCH_CLIENT_SECRET=your-twitch-client-secret

# tcomm API
TCOMM_API_KEY=your-tcomm-api-key

# o!TR API
OTR_API_KEY=your-otr-api-key

# Discord Webhooks
DISCORD_STD_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_STD_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...

# Database Backup Configuration (Recommended)
BACKUP_ENABLED=true
BACKUP_DISK=s3
BACKUP_DIRECTORY=backups
BACKUP_RETENTION_DAYS=7
BACKUP_VERIFY_UPLOAD=true
BACKUP_DISCORD_WEBHOOK=https://discord.com/api/webhooks/...
```

---

## Cloudflare R2 Setup

**Why R2?** Railway's filesystem is ephemeral (wiped on every deploy). R2 provides persistent storage for banner caching.

### Step 4: Create Cloudflare R2 Bucket (10 minutes)

1. **Go to** https://cloudflare.com → Sign up (free account)
2. **Navigate to:** R2 → Overview → "Create Bucket"
3. **Bucket name:** `tourney-method-banners`
4. **Location:** Auto (closest to users)
5. **Click:** "Create bucket"

### Step 5: Create R2 API Token

1. **Navigate to:** R2 → Overview → "Manage R2 API Tokens"
2. **Click:** "Create API Token"
3. **Permissions:** Read & Write (Admin access)
4. **TTL:** Leave as default (or set to Never expire)
5. **Click:** "Create Token"

**Save these credentials:**
```
Access Key ID: xxxxxxxxxxxxxxxxxxxx
Secret Access Key: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Account ID: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Step 6: Get R2 Endpoint

1. **Go to:** R2 → tourney-method-banners → Settings
2. **View:** S3 Compatibility endpoint
3. **Format:** `https://<account-id>.r2.cloudflarestorage.com`

Example:
```
https://abc123def456.r2.cloudflarestorage.com
```

### Step 7: Update Railway Environment Variables

Add R2 credentials to Railway environment:

```bash
AWS_ACCESS_KEY_ID=your-access-key-id-here
AWS_SECRET_ACCESS_KEY=your-secret-access-key-here
AWS_DEFAULT_REGION=auto
AWS_BUCKET=tourney-method-banners
AWS_ENDPOINT=https://abc123def456.r2.cloudflarestorage.com
```

**Note:** `config/filesystems.php` already supports S3 (R2 is S3-compatible), so no code changes needed!

---

## Post-Deployment Verification

### Step 8: Run Database Migrations

Railway automatically runs migrations on first deploy. To run manually:

```bash
# Via Railway console
php artisan migrate --force
php artisan db:seed --force
```

### Step 9: Verify Health Endpoint

```bash
curl https://your-app-name.railway.app/health
```

**Expected Response:**
```json
{
  "status": "healthy",
  "timestamp": "2026-03-06T13:45:00Z",
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

### Step 10: Verify All Services

Check Railway dashboard:
- ✅ Web service is running (green indicator)
- ✅ Scheduler service is running
- ✅ Horizon service is running
- ✅ PostgreSQL database is connected
- ✅ Redis instance is connected

### Step 11: Access Your Application

- **Application:** https://your-app-name.railway.app
- **Horizon Dashboard:** https://your-app-name.railway.app/horizon
- **Health Check:** https://your-app-name.railway.app/health

---

## Monitoring and Maintenance

### Railway Dashboard

- **URL:** https://railway.app/project/your-project-id
- **Features:** Service status, metrics, logs, deployments

### Horizon Dashboard

- **URL:** https://your-app-name.railway.app/horizon
- **Features:** Job metrics, failed jobs, worker status, job retries

### Application Logs

**View via Railway:**
1. Go to your project → Select service → "Logs" tab
2. Real-time log streaming

**View via SSH:**
```bash
# Connect to Railway service
railway open

# View logs
tail -f storage/logs/laravel.log
```

### Scheduled Tasks

Verify scheduled tasks are running:
```bash
php artisan schedule:list
```

**Expected Schedule:**
- `tournaments:parse` - Daily at 9 AM
- `reminders:registration` - Every hour
- `streams:check` - Every 3 hours

---

## Troubleshooting

### App Sleeps (Cold Starts)

**Problem:** First request takes 10-30 seconds after inactivity

**Solution:** This is expected on Railway free tier. Upgrade to $5/month for "Always-on" tier if needed.

### Ephemeral Filesystem Issues

**Problem:** Uploaded files disappear after deployment

**Solution:** Use Cloudflare R2 for all persistent storage. Banner caching is already configured to use R2.

### Horizon Not Processing Jobs

**Problem:** Jobs stuck in pending state

**Solutions:**
1. Check Horizon service is running
2. Check Redis connection
3. Restart Horizon service: `php artisan horizon:terminate`
4. Check Horizon dashboard for errors

### Database Connection Errors

**Problem:** Can't connect to PostgreSQL

**Solutions:**
1. Verify DATABASE_URL is set correctly
2. Check PostgreSQL service is running
3. Check database migrations ran successfully

### R2 Banner Caching Not Working

**Problem:** Banners not caching to R2

**Solutions:**
1. Verify R2 credentials are correct
2. Check FILESYSTEM_DISK=s3
3. Check R2 bucket exists
4. Test banner caching via tinker:
   ```php
   app(BannerCacheService::class)->cacheBanner(Tournament::first());
   Storage::disk('s3')->exists('banners/1.jpg'); // Should return true
   ```

### Out of Memory Errors

**Problem:** Worker processes crashing

**Solutions:**
1. Reduce `maxProcesses` in `config/horizon.php`
2. Lower `memory` limit per process
3. Check for memory leaks

---

## Scaling Strategy

### When to Upgrade

**Triggers:**
- Database storage > 1GB (~8,000 tournaments)
- App speed issues (cold starts)
- Concurrent users > 10-20
- Queue backlog growing

**Upgrade Options:**

| Trigger | Current Limit | Upgrade To | Cost |
|---------|--------------|------------|------|
| Database storage | 1GB | 10GB | ~$5/month |
| App speed | Cold starts (10-30s) | Always-on | ~$5-10/month |
| Concurrent users | ~10-20 | Dedicated CPU | ~$10-20/month |

### Scaling Horizontally

Railway doesn't support horizontal scaling on free tier. To scale:
1. Upgrade to paid plan
2. Deploy multiple Railway workers
3. Use load balancer

---

## Backup and Recovery

### Automated Database Backups

The application includes a comprehensive automated backup system using Cloudflare R2 storage:

**Storage:** Cloudflare R2 (same bucket as banners: `tourney-method-banners`)

**Schedule:**
- **Daily backup:** 08:55 UTC (5 minutes before tournament parsing at 09:00 UTC)
- **Pre-parsing backup:** Automatic backup before `tournaments:parse` command
- **Retention:** 7 days (configurable via `BACKUP_RETENTION_DAYS`)

**Backup Format:**
- **Location:** `backups/` prefix in R2 bucket
- **Format:** `tourney_method_{YYYY-MM-DD_HH-MM-SS}.sql.gz`
- **Size:** ~600 KB (compressed)
- **Method:** PostgreSQL `pg_dump` with gzip compression

**Manual Backup Commands:**
```bash
# Create on-demand backup
php artisan db:backup --description "Before migration"

# List all backups
php artisan db:list --all

# Restore from backup (⚠️ DESTRUCTIVE)
php artisan db:restore backups/tourney_method_2026-03-09_08-55-00.sql.gz

# Clean old backups
php artisan db:clean --days=7 --dry-run
```

**Environment Configuration:**
```bash
BACKUP_ENABLED=true          # Enable/disable automatic backups
BACKUP_DISK=s3               # Storage disk (s3 for R2)
BACKUP_DIRECTORY=backups     # R2 directory prefix
BACKUP_RETENTION_DAYS=7      # Days to retain backups
BACKUP_VERIFY_UPLOAD=true    # Verify R2 upload integrity
BACKUP_DISCORD_WEBHOOK=...   # Discord notifications
```

**Features:**
- ✅ Automatic R2 upload with verification
- ✅ Discord webhook notifications (success/failure)
- ✅ Automatic cleanup of old backups
- ✅ Rollback support (auto-restore if parsing fails)
- ✅ 7-day retention policy (prevents unlimited storage growth)

**For more details:** See [RUNBOOK.md - Backup Strategy](RUNBOOK.md#backup-strategy)

### R2 Banner Storage

R2 is also used for persistent banner caching (ephemeral filesystem workaround):

**Setup:**
1. Go to R2 → tourney-method-banners → Settings
2. Enable "Object Versioning" (optional, for banner history)

**Note:** Banners stored in `banners/` prefix, backups stored in `backups/` prefix

---

## Security Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set
- [ ] Database password is strong (Railway auto-generates)
- [ ] Redis password is set (Railway auto-generates)
- [ ] SSL/TLS enabled (Railway automatic)
- [ ] osu! OAuth configured
- [ ] R2 credentials stored securely
- [ ] `.env.railway` NOT committed to git

---

## Cost Estimate

**Free Tier (Current):**
- PostgreSQL: $0/month
- Redis: $0/month
- Web/Scheduler/Horizon: $0/month
- R2 storage: $0/month
- **Total: $0/year**

**After 1,000 Tournaments:**
- PostgreSQL: ~$5/month (60GB)
- Redis: ~$5/month (1GB)
- Workers: $0/month
- R2: $0/month (500MB)
- **Total: ~$120/year**

**After 10,000 Tournaments:**
- PostgreSQL: ~$50/month (600GB)
- Redis: ~$15/month (5GB)
- Workers: $10/month (always-on)
- R2: $0/month (5GB)
- **Total: ~$900/year**

**Recommendation:** Start with free tier, upgrade as needed.

---

**Need more help?** See [CLOUDFLARE_R2_SETUP.md](CLOUDFLARE_R2_SETUP.md) or [COMMANDS.md](COMMANDS.md).
