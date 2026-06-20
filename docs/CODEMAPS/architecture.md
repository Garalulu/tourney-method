# Architecture Codemap

<!-- Generated: 2026-03-31 | Files scanned: 165+ | Token estimate: ~1,250 -->

**Last Updated:** 2026-03-31
**Framework:** Laravel 11.47.0 (PHP 8.5.3)
**Database:** PostgreSQL 18
**Primary Purpose:** osu! tournament management and match tracking platform

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Frontend Layer                        │
│  (Blade + Livewire 3.7 + Alpine.js 3.15 + Tailwind 3.4)    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                         HTTP Layer                            │
│  (Routes → Controllers → Middleware → Form Requests)         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                       Business Logic Layer                    │
│  (Services → Jobs → Events → Policies)                       │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                         Data Layer                            │
│  (Eloquent Models ← Query Builder ← PostgreSQL 18)           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Infrastructure Layer                       │
│  (Redis Cache/Queue + Horizon 5.42 + External APIs)         │
└─────────────────────────────────────────────────────────────┘
```

## Request Lifecycle

```
User Request (Browser)
  ↓
Route Definition (routes/web.php, routes/api.php, 96 routes total)
  ↓
Middleware (Auth, CSRF, RoleMiddleware, Rate Limiting)
  ↓
Controller (app/Http/Controllers)
  ├─→ Form Request Validation
  ├─→ Business Logic (app/Services)
  │    ├─→ Queue Jobs (app/Jobs)
  │    └─→ External API Calls
  ↓
Response (HTML/JSON/Redirect)
```

## Queue Architecture

### 5-Stage Tournament Processing Pipeline

```
Stage 1: ParseForumTopicJob (Parallel)
├─→ Fetch forum topics from osu! API
├─→ Parse BBcode for tournament metadata
├─→ Extract staff roles
├─→ Generate transaction ID (BatchTransactionService)
└─→ Store staff payload in parse batch tables

Stage 2: AggregateStaffJob (Bridge)
├─→ Collect unique osu! user IDs
├─→ Retrieve tournament IDs from transaction
└─→ Pass to BatchFetchUsersJob

Stage 3: BatchFetchUsersJob (Aggregation)
├─→ Batch fetch from osu! API (50 users/request)
├─→ Create/update User records
└─→ Persist user ID mapping on parse batch

Stage 4: BatchMergeStaffJob (Attachment)
├─→ Attach staff to tournaments (with pivot.id)
├─→ Update tournament host from organizer
└─→ Create TournamentParseHistory records

Stage 5: Podium Sync Pipeline
├─→ SyncPodiumUsers: Fetch user data from osu! API
├─→ SyncPodiumGamemodes: Set correct main_mode for podium users
└─→ SipService: Queue SIP fetch for osu!standard players
```

### Other Job Types

**Import:**
- ImportTournamentsFromTcommJob (tcomm.hivie.tn API)
- Match-stat import jobs retired

**Notification:**
- SendNotificationJob (Discord/webhook) - Enhanced queue processing
- PostTournamentToDiscordJob (tournament announcements) - Enhanced with host avatars, star ratings, BWS rank formatting
- Registration reminder notifications based on watch preferences

**User:**
- SyncUserFromOsu (profile sync)
- SyncTournamentWinnersUserDataJob (batch winner data sync)
- ImportTournamentWinnerBadgesJob (badge metadata from osu! API)
- SyncUserProfilesJob (batch user profile sync with tracking)
- Podium user sync jobs (weekly scheduled)

**System:**
- CacheTournamentBannerJob (banner image caching)

## Authentication & Authorization

### OAuth2 Flow (osu!)
```
1. Login → osu.ppy.sh/oauth/authorize
2. Approval → Redirect with code
3. Exchange code → Access token
4. Fetch user profile → Create/update User
5. Issue Laravel session
```

### Role-Based Access Control
- **Master User** (MASTER_OSU_ID env var)
- **Admin Users** (users.role in ['admin', 'moderator'])
- **Regular Users**

## Data Flow Patterns

### Tournament Parsing Pipeline
```
osu! Forum → ParseForumTopicJob → Postgres parse batch
                                      ↓
                        AggregateStaffJob → Deduplicate osu_ids
                                      ↓
                        BatchFetchUsersJob → Postgres parse batch (user_id mapping)
                                      ↓
                        BatchMergeStaffJob → Database (tournament_staff)
```

### Match Submission Pipeline
```
MP link submission → Validation → Fetch from osu! API
                                        ↓
                          Create OsuMatch + MatchGame + MatchScore
                                        ↓
                          Notify admins → Approve/reject
                                        ↓
                          User notifications
```

### Tournament Badge Sync Pipeline
```
Tournament approval → ImportSingleTournamentBadgesJob
                                   ↓
                     SyncTournamentBadgesToUsersJob
                                   ↓
                     Update user profiles with badges
                                   ↓
                     Enable BWS calculations
```

### Discord Notification Flow
```
Tournament approval → Check skip_discord_webhook flag
                               ↓
                     PostTournamentToDiscordJob
                               ↓
                     Format embed with avatars, star ratings, BWS ranks
                               ↓
                     Send to Discord webhook (environment variable URLs)
```

### SIP Integration Flow
```
Laravel → SyncPodiumUsers → SipService → SipFetchQueue → Google Apps Script
                                      ↓
                        skillissue.app API → Update user.sip field
```

### Global Search Flow (NEW)
```
User query → SearchController → SearchService
                                  ↓
                    Query multiple indexes (users, tournaments)
                                  ↓
                    Return ranked results with metadata
```

## External Dependencies

### Data Sources
- **osu! API v2** - OAuth2 auth, user data, forum topics, avatars
- **tcomm.hivie.tn API** - Tournament data
- **o!TR API** - Historical match data (updated endpoint)
- **Twitch API** - Stream detection
- **Discord API** - Webhook notifications with environment variable support
- **skillissue.app API** - SIP data for osu!standard players (via Google Sheets)

### Infrastructure
- **PostgreSQL 18** - Primary database
- **Redis** - Cache, queue backend, rate limiting
- **Laravel Horizon 5.42** - Queue monitoring
- **Cloudflare R2** - Persistent object storage (banners, backups)
- **Google Sheets** - SIP data processing middleware
- **Mailpit** - Email testing (Docker)
- **Railway** - Production deployment platform

## Caching Strategy (Redis)

- `osu_user:{osu_id}` - User profile (24h TTL)
- Parse staff payloads and user mappings live in `tournament_parse_batches` / `tournament_parse_staff_payloads`
- `tournament_rankings` - Computed rankings (1h TTL)
- `tournament_banner:{tournament_id}` - Cached banner URL (7 days TTL)
- `search_results:{query_hash}` - Global search results (5 min TTL)

### Banner Caching System

**Purpose:** Prevent cookie warnings by caching Discord banners to R2

**Pipeline:**
```
1. Tournament created/updated with Discord URL
   ├─→ CacheTournamentBannerJob dispatched
   ├─→ Download banner from Discord CDN
   ├─→ Upload to R2 (tourney-method-banners)
   ├─→ Store cached URL in tournament.banner_cached_url
   └─→ Set 7-day cache expiry

2. Tournament display
   ├─→ Check tournament.banner_cached_url
   ├─→ Use cached URL if available
   └─→ Fallback to direct Discord URL if not cached
```

**Benefits:**
- Eliminates third-party cookie warnings
- Faster page load (R2 CDN)
- Reduced Discord API dependencies
- Persistent banner storage (survives Discord URL changes)

## Backup Strategy

### Automated R2 Backup System

**Storage:** Cloudflare R2 (same bucket as banners: `tourney-method-banners`)

**Schedule:**
- **Daily backup:** 08:55 UTC (5 minutes before tournament parsing)
- **Pre-parsing backup:** Automatic backup before `tournaments:parse`
- **Retention:** 7 days (auto-cleanup)

**Backup Format:**
- **Location:** `backups/` prefix in R2 bucket
- **Format:** `tourney_method_{YYYY-MM-DD_HH-MM-SS}.sql.gz`
- **Size:** ~600 KB (compressed)
- **Method:** PostgreSQL `pg_dump` with gzip compression

**Pipeline:**
```
1. CreateScheduledBackup (08:55 UTC daily)
   ├─→ CreatesBackup.createBackup()
   ├─→ performBackup() - pg_dump to /tmp
   ├─→ uploadBackupToR2() - Upload to R2
   ├─→ verifyR2Upload() - Verify integrity
   ├─→ sendBackupNotification() - Discord webhook
   └─→ cleanupOldBackups() - Delete >7 days

2. ParseTournaments (09:00 UTC daily)
   ├─→ CreatesBackup.createBackup()
   ├─→ Run parsing pipeline
   └─→ CreatesBackup.restoreBackup() on failure
```

**Commands:**
- `backups:create` - Scheduled daily backup
- `db:backup` - Manual on-demand backup
- `db:list` - List available backups
- `db:restore` - Restore from backup (DESTRUCTIVE)
- `db:clean` - Clean old backups

**Features:**
- R2 upload verification (file existence + size match)
- Discord webhook notifications (success/failure)
- Automatic rollback if parsing fails
- Uses same R2 credentials as banner storage
- Separate `backups/` prefix prevents conflicts

## Search Architecture (NEW)

### Global Search System

**Implementation:** SearchService with optimized database indexes

**Search Entities:**
- Users (username, previous_usernames, osu_id)
- Tournaments (title, description)

**Features:**
- Fuzzy matching with typo tolerance
- Ranked results by relevance
- Previous username search support
- Performance-optimized with composite indexes

**API Endpoint:**
```
GET /api/search?q={query}
Response: {
  users: [{ id, username, avatar_url (computed from osu_id), rank, country }],
  tournaments: [{ id, title, status, mode }]
}
```

## Security

- **CSRF Protection** - All POST routes
- **XSS Prevention** - Blade auto-escaping
- **SQL Injection** - Eloquent parameterized queries
- **Mass Assignment** - Explicit $fillable on models
- **Secrets** - Environment variables (.env only)
- **SIP API Security** - Token-based authentication for Google Sheets integration
- **Search Rate Limiting** - 60 requests/minute per user

## Performance Optimizations

### Database
- Eager loading (prevent N+1 queries)
- Query scopes (reusable builders)
- Database indexes (foreign keys, frequent queries)
- Composite indexes for search performance
- Connection pooling (PostgreSQL persistent)

### Queue
- Batch processing (deduplicate API calls)
- Chunking (large datasets)
- Rate limiting (protect external APIs)
- Worker concurrency (Horizon: isolated `osu-user` and admin/import lanes)

### Cache
- Query result caching (expensive computations)
- HTTP response caching (static assets)
- Tag-based invalidation (clear related caches)
- Search result caching (5 min TTL)

### Search Performance
- Composite indexes on users (username, previous_usernames)
- Composite indexes on tournaments (title, status)
- Full-text search with LIKE queries
- Result pagination (max 50 results per entity)

## Monitoring

### Logs
- Application: `storage/logs/laravel.log`
- Queue: Horizon dashboard + logs
- Browser: Browser developer console

### Metrics (Horizon)
- Job throughput/minute
- Failed job count
- Queue depth
- Worker status
- Retry statistics

### Admin Audit Trail
- All admin actions → `admin_audit_logs` table
- Track: who, what, when, IP address
- Viewable in Admin Panel (/admin/audit-log)

### SIP Queue Monitoring
- Pending queue count via `SipService::getPendingCount()`
- Failed items retrieval for debugging
- Retry functionality for failed fetches

## Development vs Production

**Development:**
- Debug mode enabled (APP_DEBUG=true)
- Xdebug coverage
- Mailpit for email testing
- Detailed error messages
- Slow query logging

**Production:**
- Debug mode disabled (APP_DEBUG=false)
- Optimized config cache
- Route cache
- View cache
- Queue workers with monitoring
- Scheduled tasks (Railway Cron commands in production; `schedule:run` remains available)
- Weekly podium user sync (Sundays 2 AM KST)

## Scheduled Tasks

### Daily
- **08:55 UTC** - Database backup to R2
- **09:00 UTC** - Tournament parsing

### Weekly
- **Sundays 02:00 KST** - Podium user sync
  - Sync user data from osu! API (pp, rank, badges)
  - Queue SIP fetches for osu!standard players
  - Set correct main_mode for podium users

### On-Demand
- User profile sync (batch tracking)
- Orphaned users cleanup
- Search index rebuilding (automatic via migrations)
