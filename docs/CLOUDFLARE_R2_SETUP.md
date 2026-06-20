# Cloudflare R2 Setup Guide

**Last Updated:** 2026-03-09

This guide covers setting up Cloudflare R2 for persistent banner storage and database backups in the Tourney Method application.

---

## Table of Contents

1. [Why R2?](#why-r2)
2. [R2 vs Alternatives](#r2-vs-alternatives)
3. [Setup Steps](#setup-steps)
4. [Configuration](#configuration)
5. [Verification](#verification)
6. [Cost and Limits](#cost-and-limits)
7. [Troubleshooting](#troubleshooting)

---

## Why R2?

### Problem: Railway Ephemeral Filesystem

Railway's container filesystem is **ephemeral** (temporary):
- Files in `storage/` are **deleted on every deploy**
- Banner caching to local filesystem **loses all cached images** on deploy
- Not suitable for persistent storage

### Solution: Cloudflare R2

**R2 Features:**
- ✅ **S3-compatible API** - Laravel supports natively
- ✅ **Persistent storage** - Survives Railway deployments
- ✅ **Zero egress fees** - Unlike AWS S3
- ✅ **10GB free tier** - Generous free allowance
- ✅ **Fast CDN delivery** - Global edge network

**Perfect for:** Banner image caching, database backups, user uploads, static assets.

**R2 Bucket Usage:**
- `banners/` prefix - Tournament banner images
- `backups/` prefix - Automated database backups (~600 KB each, 7-day retention)

---

## R2 vs Alternatives

| Feature | Cloudflare R2 | AWS S3 | DigitalOcean Spaces | Railway Local |
|---------|--------------|--------|-------------------|---------------|
| **Cost** | $0 (10GB free) | ~$23/10GB | ~$5/10GB | Free (ephemeral) |
| **Egress** | **FREE** | $90/TB | $0.01/GB | N/A |
| **S3 Compatible** | ✅ Yes | ✅ Yes | ✅ Yes | N/A |
| **Persistence** | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No |
| **Setup Time** | 10 min | 15 min | 15 min | 0 min |
| **CDN** | ✅ Built-in | ✅ CloudFront | ✅ Built-in | ❌ No |

**Winner:** R2 for free personal projects! 🎉

---

## Setup Steps

### Step 1: Create Cloudflare Account (5 minutes)

1. **Go to** https://cloudflare.com
2. **Sign up** for free account
3. **Verify** email address

### Step 2: Create R2 Bucket (5 minutes)

1. **Navigate to:** R2 → Overview (in Cloudflare dashboard)
2. **Click:** "Create Bucket"
3. **Configure:**
   - **Bucket name:** `tourney-method-banners`
   - **Location:** Auto (closest to your users)
4. **Click:** "Create Bucket"

**Note:** Bucket names must be globally unique across all R2 users.

### Step 3: Create API Token (5 minutes)

1. **Navigate to:** R2 → Overview → "Manage R2 API Tokens"
2. **Click:** "Create API Token"
3. **Configure Permissions:**
   - **Read:** ✅ Enabled
   - **Write:** ✅ Enabled
   - **List:** ✅ Enabled
4. **TTL:** Leave as default (or set to "Never expire" for convenience)
5. **Click:** "Create Token"

**⚠️ IMPORTANT:** Save these credentials immediately (you won't see the secret key again!)

```
Access Key ID: xxxxxxxxxxxxxxxxxxxx
Secret Access Key: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Account ID: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Step 4: Get R2 Endpoint (2 minutes)

1. **Go to:** R2 → tourney-method-banners bucket → Settings
2. **Find:** "S3 Compatibility endpoint"
3. **Copy** the endpoint URL

**Format:** `https://<account-id>.r2.cloudflarestorage.com`

**Example:**
```
https://abc123def4567890.r2.cloudflarestorage.com
```

### Step 5: Configure Laravel (5 minutes)

**No code changes needed!** Laravel's S3 filesystem driver works with R2.

**Add to `.env` or Railway environment variables:**

```bash
# Filesystem Configuration
FILESYSTEM_DISK=s3

# AWS S3 Configuration (R2 is S3-compatible)
AWS_ACCESS_KEY_ID=your-access-key-id-here
AWS_SECRET_ACCESS_KEY=your-secret-access-key-here
AWS_DEFAULT_REGION=auto
AWS_BUCKET=tourney-method-banners
AWS_ENDPOINT=https://abc123def456.r2.cloudflarestorage.com
AWS_URL=https://pub-abc123def4567890.r2.dev
```

**Note:** `AWS_URL` is the public URL for accessing files (optional but recommended).

---

## Configuration

### Laravel Filesystem Config

**File:** `config/filesystems.php`

Laravel already has S3 disk configured (R2-compatible):

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),
    'url' => env('AWS_URL'),
],
```

**No changes needed!** Just set environment variables.

### Banner Caching Service

**File:** `app/Services/BannerCacheService.php`

Current implementation uses local filesystem:

```php
Storage::disk('public')->put("banners/{$tournament->id}.jpg", $imageData);
```

**Update to use R2:**

```php
Storage::disk('s3')->put("banners/{$tournament->id}.jpg", $imageData);
```

Or set default filesystem disk:

```bash
FILESYSTEM_DISK=s3
```

---

## Verification

### Test 1: Verify R2 Connection

```bash
# Via Railway console or local tinker
php artisan tinker

>>> Storage::disk('s3')->put('test.txt', 'Hello R2!');
=> true

>>> Storage::disk('s3')->exists('test.txt');
=> true

>>> Storage::disk('s3')->get('test.txt');
=> "Hello R2!"

>>> Storage::disk('s3')->delete('test.txt');
=> true
```

### Test 2: Verify Banner Caching

```bash
php artisan tinker

>>> $tournament = Tournament::first();
=> App\Models\Tournament {...}

>>> app(BannerCacheService::class)->cacheBanner($tournament);
=> true

>>> Storage::disk('s3')->exists("banners/{$tournament->id}.jpg");
=> true
```

### Test 3: Verify Public Access (Optional)

If you set up public access on R2 bucket:

```bash
curl https://pub-abc123def456.r2.dev/banners/1.jpg
# Should return the banner image
```

---

## Cost and Limits

### R2 Free Tier

**What you get for free:**
- ✅ **10GB storage** (~2,000 tournament banners at 5MB each)
- ✅ **10 million read requests/month**
- ✅ **1 million write requests/month**
- ✅ **Zero egress fees** (unlike AWS S3)

### Your Estimated Usage

**For 1,000 tournaments:**
```
1000 tournaments × 500KB per banner = 500MB (5% of free tier)
1000 reads/day × 30 days = 30,000 reads (0.3% of free tier)
10 writes/day × 30 days = 300 writes (0.03% of free tier)
```

**Result:** Well within free tier! 🎉

### When to Upgrade

**Upgrade triggers:**
- Storage > 10GB (~20,000 tournaments)
- Reads > 10M/month (~333k/day)
- Writes > 1M/month (~33k/day)

**Cost after free tier:**
- Storage: $0.015/GB/month
- Class A operations (write): $4.50/million
- Class B operations (read): $0.36/million
- **Egress: FREE!** (huge savings vs AWS S3)

---

## Troubleshooting

### "Access Denied" Error

**Problem:** `AccessDenied` when trying to upload to R2

**Solutions:**
1. Verify R2 API token has Read + Write permissions
2. Check credentials are correct (no extra spaces)
3. Regenerate API token if needed

### "No such bucket" Error

**Problem:** `NoSuchBucket` when accessing R2

**Solutions:**
1. Verify bucket name is correct
2. Check bucket exists in R2 dashboard
3. Verify `AWS_BUCKET` environment variable

### "Invalid endpoint" Error

**Problem:** Can't connect to R2 endpoint

**Solutions:**
1. Verify `AWS_ENDPOINT` format: `https://<account-id>.r2.cloudflarestorage.com`
2. Check for typos in endpoint URL
3. Verify Account ID is correct

### "Signature does not match" Error

**Problem:** Authentication failing

**Solutions:**
1. Regenerate R2 API token
2. Update `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
3. Check for extra whitespace in credentials

### Public Access Not Working

**Problem:** Can't access files via public URL

**Solutions:**
1. Enable public access on R2 bucket (Settings → Public Access)
2. Verify `AWS_URL` is set correctly
3. Check bucket CORS settings if accessing from web app

---

## Advanced Configuration

### CORS Configuration

If accessing files from web application, configure CORS:

1. **Go to:** R2 → tourney-method-banners → Settings
2. **Add CORS rule:**
   ```
   Allowed Origins: https://your-app-name.railway.app
   Allowed Methods: GET, HEAD
   Allowed Headers: *
   Max Age Seconds: 86400
   ```

### Custom Domain (Optional)

If you want your own domain for R2:

1. **Add custom domain:** R2 → tourney-method-banners → Settings → Custom Domain
2. **Configure DNS:** CNAME record pointing to R2
3. **Update `AWS_URL`:** `https://banners.yourdomain.com`

### Lifecycle Rules (Auto-Delete Old Files)

Set up automatic deletion of old banner files:

1. **Go to:** R2 → tourney-method-banners → Settings → Lifecycle Rules
2. **Create rule:**
   - **Name:** Delete old banners
   - **Prefix:** banners/
   - **Age:** 90 days
   - **Action:** Delete

---

## Migration from Local Storage

### Export Existing Banners

```bash
# From local storage
php artisan tinker

>>> $banners = Storage::disk('public')->files('banners');
=> ["banners/1.jpg", "banners/2.jpg", ...]

>>> foreach ($banners as $banner) {
>>>     $content = Storage::disk('public')->get($banner);
>>>     Storage::disk('s3')->put($banner, $content);
>>> }
=> true
```

### Verify Migration

```bash
>>> Storage::disk('s3')->files('banners');
=> ["banners/1.jpg", "banners/2.jpg", ...]
```

### Update Application Code

**Change `BannerCacheService`:**

```php
// From:
Storage::disk('public')->put("banners/{$tournament->id}.jpg", $imageData);

// To:
Storage::disk('s3')->put("banners/{$tournament->id}.jpg", $imageData);
```

Or set default disk:

```bash
FILESYSTEM_DISK=s3
```

---

## Best Practices

1. **Always use R2 for persistent storage**
   - Banner images
   - User uploads
   - Static assets

2. **Use local storage for temporary files**
   - Cache files (auto-generated)
   - Session files (use Redis instead)
   - Logs (use Railway logs)

3. **Monitor R2 usage**
   - Check storage usage monthly
   - Monitor request counts
   - Set up billing alerts

4. **Implement retry logic**
   - Network failures can occur
   - Use exponential backoff
   - Log all R2 operations

5. **Use versioning for critical files**
   - Enable R2 object versioning
   - Prevent accidental overwrites
   - Maintain history

---

**Need more help?** See [DEPLOYMENT_RAILWAY.md](DEPLOYMENT_RAILWAY.md) or [COMMANDS.md](COMMANDS.md).
