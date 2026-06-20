# Environment Variables Reference

**Last Updated:** 2026-03-26
**Source:** `.env.example`

<!-- AUTO-GENERATED: Environment Variables - Generated from .env.example -->
<!-- DO NOT EDIT MANUALLY - This section is auto-generated from source files -->

## Core Application Settings

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_NAME` | No | `"Tourney Method"` | Application name (used in notifications, page titles) |
| `APP_ENV` | No | `local` | Environment mode (`local`, `production`, `testing`) |
| `APP_KEY` | **Yes** | *(generated)* | Laravel encryption key (32-character base64 string) |
| `APP_DEBUG` | No | `true` | Debug mode (`true` for local, `false` for production) |
| `APP_TIMEZONE` | No | `Asia/Seoul` | Application timezone for timestamps display |
| `APP_URL` | **Yes** | `http://localhost` | Application base URL |
| `APP_LOCALE` | No | `en` | Application language locale |
| `APP_FALLBACK_LOCALE` | No | `en` | Fallback locale when translation not available |
| `APP_FAKER_LOCALE` | No | `en_US` | Locale for generating fake data in tests/seeders |
| `APP_MAINTENANCE_DRIVER` | No | `file` | Maintenance mode storage driver (`file`, `database`) |
| `PHP_CLI_SERVER_WORKERS` | No | `4` | Number of PHP built-in server workers |
| `BCRYPT_ROUNDS` | No | `12` | Password hashing rounds (higher = more secure but slower) |

## Laravel Sail / Docker Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WWWUSER` | No | `1000` | UID of the `sail` user inside containers |
| `WWWGROUP` | No | `1000` | GID of the `sail` group inside containers |
| `SAIL_XDEBUG_MODE` | No | `develop,debug,coverage` | Xdebug modes for debugging (development only) |
| `SAIL_SKIP_CHECKS` | No | `true` | Skip Sail container health checks on startup |

## Logging Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `LOG_CHANNEL` | No | `stack` | Default log channel (`stack`, `single`, `daily`) |
| `LOG_STACK` | No | `single` | Log channels used by stack driver |
| `LOG_DEPRECATIONS_CHANNEL` | No | `null` | Channel for deprecation warnings (`null` to disable) |
| `LOG_LEVEL` | No | `debug` | Minimum log level (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`) |

## Database Configuration (PostgreSQL)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_CONNECTION` | No | `pgsql` | Database driver (`pgsql`, `mysql`, `sqlite`) |
| `DB_HOST` | **Yes** | `pgsql` | Database host (Docker service name for local dev) |
| `DB_PORT` | No | `5432` | Database port |
| `DB_DATABASE` | **Yes** | `tourney_method` | Database name |
| `DB_USERNAME` | **Yes** | `sail` | Database user |
| `DB_PASSWORD` | **Yes** | `password` | Database password |

## Database Backup Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `BACKUP_ENABLED` | No | `true` | Enable automatic database backups (`true`, `false`) |
| `BACKUP_DISK` | No | `s3` | Storage disk for backups (`s3` for R2, `local` for testing) |
| `BACKUP_DIRECTORY` | No | `backups` | R2 directory prefix for backup files |
| `BACKUP_RETENTION_DAYS` | No | `7` | Number of days to retain backups before automatic cleanup |
| `BACKUP_VERIFY_UPLOAD` | No | `true` | Verify R2 upload integrity after backup (`true`, `false`) |
| `BACKUP_DISCORD_WEBHOOK` | No | `null` | Discord webhook URL for backup notifications (success/failure) |

**Backup Schedule:**
- **Scheduled backup:** Daily at 08:55 UTC (5 minutes before tournament parsing)
- **Pre-parsing backup:** Automatic backup before `tournaments:parse` command
- **Retention:** Backups older than `BACKUP_RETENTION_DAYS` are automatically deleted

**Storage:**
- Backups are stored in Cloudflare R2 using AWS S3-compatible API
- Format: `{BACKUP_DIRECTORY}/{database}_{YYYY-MM-DD_HH-MM-SS}.sql.gz`
- Example: `backups/tourney_method_2026-03-09_08-55-00.sql.gz`

**Commands:**
- `php artisan db:backup` - Create manual backup
- `php artisan db:list` - List available backups
- `php artisan db:restore <file>` - Restore from backup
- `php artisan db:clean` - Clean old backups

## Session Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SESSION_DRIVER` | No | `redis` | Session storage driver (`file`, `redis`, `database`, `cookie`) |
| `SESSION_LIFETIME` | No | `120` | Session lifetime in minutes |
| `SESSION_ENCRYPT` | No | `false` | Encrypt session data (`true`, `false`) |
| `SESSION_PATH` | No | `/` | Session cookie path |
| `SESSION_DOMAIN` | No | `null` | Session cookie domain (null for current domain) |

## Cache & Queue Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `CACHE_STORE` | No | `redis` | Cache driver (`redis`, `file`, `database`, `memcached`) |
| `CACHE_PREFIX` | No | *(empty)* | Cache key prefix (for shared cache instances) |
| `QUEUE_CONNECTION` | No | `redis` | Queue driver (`redis`, `database`, `sync`) |
| `BROADCAST_CONNECTION` | No | `log` | Broadcast driver (`log`, `pusher`, `redis`) |
| `FILESYSTEM_DISK` | No | `local` | Default filesystem disk (`local`, `s3`, `public`) |

## Redis Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `REDIS_CLIENT` | No | `phpredis` | Redis PHP client (`phpredis`, `predis`) |
| `REDIS_HOST` | **Yes** | `redis` | Redis host (Docker service name for local dev) |
| `REDIS_PASSWORD` | No | `null` | Redis password (null for no password) |
| `REDIS_PORT` | No | `6379` | Redis port |

## Mail Configuration (Mailpit for Local Dev)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MAIL_MAILER` | No | `smtp` | Mail transport (`smtp`, `sendmail`, `mailgun`, `ses`) |
| `MAIL_SCHEME` | No | `null` | Mail scheme (null for smtp) |
| `MAIL_HOST` | **Yes** | `mailpit` | SMTP host (Mailpit Docker service for local dev) |
| `MAIL_PORT` | No | `1025` | SMTP port (1025 for Mailpit) |
| `MAIL_USERNAME` | No | `null` | SMTP username (null for no auth) |
| `MAIL_PASSWORD` | No | `null` | SMTP password (null for no auth) |
| `MAIL_FROM_ADDRESS` | No | `"hello@example.com"` | Default from email address |
| `MAIL_FROM_NAME` | No | `${APP_NAME}` | Default from name |

## AWS S3 Configuration (Optional)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `AWS_ACCESS_KEY_ID` | No | *(empty)* | AWS access key for S3 |
| `AWS_SECRET_ACCESS_KEY` | No | *(empty)* | AWS secret key for S3 |
| `AWS_DEFAULT_REGION` | No | `us-east-1` | AWS region |
| `AWS_BUCKET` | No | *(empty)* | S3 bucket name |
| `AWS_USE_PATH_STYLE_ENDPOINT` | No | `false` | Use path-style S3 endpoints |

## osu! OAuth Configuration (Required)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `OSU_CLIENT_ID` | **Yes** | `your_client_id` | osu! OAuth application client ID |
| `OSU_CLIENT_SECRET` | **Yes** | `your_client_secret` | osu! OAuth application client secret |
| `OSU_REDIRECT_URI` | **Yes** | `http://localhost:8000/auth/callback` | OAuth callback URL (must match osu! app settings) |

**How to obtain:**
1. Visit https://osu.ppy.sh/home/account/edit
2. Scroll to "OAuth" section
3. Register new OAuth application
4. Set callback URL to `http://localhost/auth/callback` (local) or `https://yourdomain.com/auth/callback` (production)

## Twitch API Configuration (Optional)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TWITCH_CLIENT_ID` | No | `your_client_id` | Twitch application client ID |
| `TWITCH_CLIENT_SECRET` | No | `your_client_secret` | Twitch application client secret |

**How to obtain:**
1. Visit https://dev.twitch.tv/console
2. Create new application
3. Get Client ID and Client Secret

## tcomm.hivie.tn API Configuration (Optional)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TCOMM_API_KEY` | No | `your_api_key` | tcomm API key for tournament data import |

**How to obtain:**
- Contact tcomm administrators for API key access

## o!TR API Configuration (Optional)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `OTR_API_KEY` | No | `your_api_key` | o!TR API key for historical match data |

**How to obtain:**
- Visit https://otr.stagec.net for API documentation and key request

## Master User Configuration (Required)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MASTER_OSU_ID` | **Yes** | `your_osu_id` | osu! user ID with master privileges (full admin access) |

**How to obtain:**
1. Visit your osu! profile
2. Copy numeric user ID from URL (e.g., `https://osu.ppy.sh/users/12345` → ID is `12345`)

## Discord Webhook Configuration (Optional)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DISCORD_STD_BADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for standard mode badge tournaments |
| `DISCORD_STD_NONBADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for standard mode non-badge tournaments |
| `DISCORD_TAIKO_BADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for taiko mode badge tournaments |
| `DISCORD_TAIKO_NONBADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for taiko mode non-badge tournaments |
| `DISCORD_CATCH_BADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for catch mode badge tournaments |
| `DISCORD_CATCH_NONBADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for catch mode non-badge tournaments |
| `DISCORD_MANIA_BADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for mania mode badge tournaments |
| `DISCORD_MANIA_NONBADGE_WEBHOOK` | No | *(empty)* | Discord webhook URL for mania mode non-badge tournaments |

## SIP API Configuration (Required for Google Sheets Integration)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SIP_API_TOKEN` | **Yes** | *(empty)* | API token for securing Laravel SIP endpoints from Google Sheets |

**How to generate:**
```bash
# Generate a random 32-character token
php -r "echo bin2hex(random_bytes(32));"
```

**Purpose:**
- **Secures YOUR Laravel API endpoints** (`/api/sip/queue`, `/api/sip/update`)
- Authenticates requests from Google Sheets Apps Script
- Prevents unauthorized access to user queue and SIP submission endpoints

**How it works:**
1. Google Sheets Apps Script includes this token in `X-SIP-API-Token` header
2. Laravel verifies the token matches before returning queue data or accepting SIP results
3. The skillissue.app API does NOT require this token (it only accepts requests from Google Apps Script context)

**Setup:**
1. Generate token using command above
2. Add to `.env`: `SIP_API_TOKEN=your_generated_token_here`
3. Add same token to Google Sheets Apps Script: `const API_TOKEN = 'your_generated_token_here'`
4. Apps Script sends token in headers when calling `/api/sip/queue` and `/api/sip/update`

**Security Notes:**
- Keep token secure (do not commit to version control)
- Use a strong, random token (minimum 32 characters)
- Rotate token if compromised or periodically
- Token is only for YOUR API, not for skillissue.app external service

## Vite Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `VITE_APP_NAME` | No | `${APP_NAME}` | Application name for frontend (inherited from APP_NAME) |

<!-- END AUTO-GENERATED SECTION -->

## Production Checklist

Before deploying to production, ensure these are set correctly:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set (run `php artisan key:generate`)
- [ ] `APP_URL` points to production domain
- [ ] `DB_PASSWORD` is strong and unique
- [ ] `REDIS_PASSWORD` is set (if using Redis with auth)
- [ ] `OSU_CLIENT_ID` and `OSU_CLIENT_SECRET` are set
- [ ] `OSU_REDIRECT_URI` matches production domain
- [ ] `MASTER_OSU_ID` is set to a trusted user ID
- [ ] `LOG_LEVEL` is set to `warning` or `error` (reduce log verbosity)

## Security Notes

- **Never commit `.env` file** to version control
- **Use strong, unique passwords** for all credentials
- **Rotate secrets regularly** (especially OAuth tokens)
- **Use different credentials** for development and production
- **Limit API access** with appropriate scopes and rate limits
- **Monitor logs** for unauthorized access attempts

## Troubleshooting

### OAuth Callback Errors

**Problem:** `redirect_uri_mismatch` error from osu! OAuth

**Solution:** Ensure `OSU_REDIRECT_URI` exactly matches the URL registered in osu! OAuth application settings (including `http://` vs `https://` and trailing slashes).

### Database Connection Errors

**Problem:** `SQLSTATE[08006] [7] could not connect to server`

**Solution:**
1. Verify PostgreSQL is running: `docker compose ps`
2. Check `DB_HOST` matches Docker service name (`pgsql` for Sail)
3. Verify `DB_PORT` is `5432`
4. Check database exists: `docker compose exec pgsql psql -U sail -c "\l"`

### Queue Jobs Not Processing

**Problem:** Jobs remain in pending state

**Solution:**
1. Verify Horizon is running: `docker compose exec -u sail laravel.test php artisan horizon:status`
2. Check Redis connection: `docker compose exec redis redis-cli PING`
3. Restart Horizon: `docker compose exec -u sail laravel.test php artisan horizon:terminate`

---

**Need help?** See [CONTRIBUTING.md](CONTRIBUTING.md) or [RUNBOOK.md](RUNBOOK.md)
