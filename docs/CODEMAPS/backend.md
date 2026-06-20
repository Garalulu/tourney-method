# Backend Codemap

<!-- Generated: 2026-03-31 | Files scanned: 95+ | Token estimate: ~1,500 -->

**Last Updated:** 2026-03-31
**PHP Version:** 8.5.3
**Laravel Version:** 11.47.0

## Controllers (app/Http/Controllers)

### Admin Controllers (app/Http/Controllers/Admin)
- `AdminDashboardController` - Admin panel landing
- `AuditLogController` - View/filter admin action logs
- `ImportController` - Manage import jobs (manual import, job status)
- `MatchController` - Approve/reject pending matches
- `TournamentController` - Tournament management
  - `parseHistory()` - Show parse timeline
  - `showParseHistory()` - Single parse with diff viewer
  - `deleteParseHistory()` - Delete parse record
  - `reparse()` - Trigger staff re-parse
  - `reparseWithResolution()` - Re-parse with conflict resolution
  - `conflicts()` / `previewConflicts()` - View staff conflicts
  - `update()` - Update tournament (protected fields from reparse)
  - `approve()` / `reject()` - Approve/reject tournament
  - `approve()` - Enhanced with `skip_discord_webhook` parameter
  - `refreshBanner()` - Fetch fresh banner from Discord
  - `restore()` - Restore soft-deleted tournament
  - `destroy()` - Soft-delete tournament
  - **Podium Management:**
    - `storePodium()` - Add podium winner
    - `updatePodium()` - Update winner placement
    - `destroyPodium()` - Remove podium winner
  - **Badge Management (placement-based):**
    - `addBadgeUrl()` - POST /admin/tournaments/{id}/badges
    - `removeBadgeUrl()` - DELETE /admin/tournaments/{id}/badges
  - **Staff Management:**
    - `fetchUserRoles()` - GET /admin/tournaments/{id}/staff
    - `getStaffComponent()` - AJAX component refresh
    - `addStaff()` - POST /admin/tournaments/{id}/staff
    - `updateStaff()` - PATCH /admin/tournaments/{id}/staff/{staff}
    - `removeStaff()` - DELETE /admin/tournaments/{id}/staff/{user}
- `UserController` - User management and role changes

### Public Controllers
- `AuthController` - osu! OAuth2 (login/logout/callback/sync/me)
  - Soft-delete handling: Restores soft-deleted users on OAuth login
- `DashboardController` - User dashboard with recent activity
  - **NEW:** Enhanced with last week's results component
- `HealthCheckController` - Application health check endpoint
- `HomeController` - Landing page
- Match submission controller retired; participation MP links are saved on participation records.
- `SearchController` - **NEW** Global search functionality
  - `search()` - GET /api/search - Search users and tournaments
  - Returns ranked results with fuzzy matching
  - Supports previous username search
  - Rate limited: 60 requests/minute
- `TournamentController` - Tournaments list and details
- `TournamentWatchController` - Watch/unwatch tournaments
- `UserProfileController` - User profiles and stats
- `UserSettingsController` - User preferences and notifications
- `UserSetupController` - Initial setup (username selection)
- `YearRecapController` - Yearly recap generation (PDF)

### **NEW API Controllers**
- `SipController` - Google Sheets integration endpoints
  - `getQueue()` - GET /api/sip/queue - Return pending SIP fetch items
    - Security: X-SIP-API-Token header validation
    - Limit: 50 users per batch
    - Status update: pending → processing
  - `updateSip()` - POST /api/sip/update - Accept SIP results from Google Sheets
    - Security: X-SIP-API-Token header validation
    - Updates user.sip and user.sip_updated_at
    - Batch processing with success/failure counting

## Models (app/Models)

### Core Models
- `User` - Application users (osu! authentication)
  - **NEW FIELDS:** `sip`, `sip_updated_at`, `main_mode_source`, `previous_usernames`
  - Relationships: matches, tournaments (staff), badges, participations
  - **Search features:** Previous username tracking for global search
- `Tournament` - Tournament records
  - Relationships: staff, parseHistory, watches, winners
  - Methods: hasStaff() - Check if tournament has any staff records
  - Badge system: placement-based badge storage in badge_urls JSON
  - **NEW:** Enhanced podium display with smart sorting
- `OsuMatch` - Legacy osu! multiplayer match records retained for historical imports only
- `MatchGame` / `MatchScore` - Retired match-stat tables

### Tournament Models
- `TournamentStaff` - Pivot with ID (Tournament ↔ User)
  - Roles: organizer, mapper, mappooler, referee, playtester, gfx, sheeter, streamer, commentator, other
  - Fields: status, submitted_at, reviewed_at, reviewed_by
- `TournamentParseHistory` - Parse attempt tracking
  - Fields: changes (JSON diff), parsed_data, parse_source, parse_notes, parsed_at
- `TournamentWatch` - User watchlist with notification flags (Tournament ↔ User)
  - Methods: hasRegistrationReminders(), hasStreamNotifications()
  - Supports different watch types: interested, watching, stream

### User Data Models
- `UserBadge` - User's osu! badges
  - Fields: user_id, image_url, image_2x_url, awarded_at, badge_url, tournament_id
  - Relationships: belongsTo User, belongsTo Tournament
  - Gamemode comes from linked `TournamentWinner`
- `TournamentWinner` - Tournament placement records
  - Fields: tournament_id, user_id, osu_id, username, placement, gamemode
  - Badge fields: badge_image_url, badge_image_2x_url, badge_description, badge_url, badge_awarded_at
  - JSON metadata: cover_url, imported_from_tcomm flag
  - Relationships: belongsTo Tournament, belongsTo User
  - Used for: BWS calculation, badge display on user profiles
  - **NEW:** Supports tri-badge tournaments (1st, 2nd, 3rd place)
- `ParticipationRecordMatch` - Participation MP links and score summaries
- `UserRankHistory` - Historical rank tracking
- `YearRecapCache` - Pre-generated yearly recaps

### **NEW Models**
- `SipFetchQueue` - SIP fetch request queue
  - Fields: user_id, osu_id, username, status, sip, error_message
  - Status: pending, processing, completed, failed
  - Used by: SipService, SipController, Google Apps Script integration

### System Models
- `AdminAuditLog` - Admin action audit trail
- `ImportJob` - Import job tracking
- `NotificationQueue` - Pending notifications
- `DiscordChannel` - Discord webhook configs
- BWS exclusion patterns retired; unlinked badges are not BWS eligible

## Services (app/Services)

### API Integration Services
- `OsuApiService` - osu! API v2 client
  - Methods: getUser(), getUsers(array $ids), getForumTopic()
  - Features: OAuth token management, rate limiting, batch user fetching
  - Batch processing: getUsers() chunks requests into groups of 50 (API limit)
  - Response handling: Unwraps nested 'users' key, handles multiple response formats
  - Caching: 24-hour cache for user data
  - **NEW:** Previous username tracking support
- `TcommApiService` - tcomm.hivie.tn API client
  - Methods: getTournaments(), getTournamentsPaginated(), getAllTournaments(), getTournament()
  - Features: Rate limiting (100 req/10min), type filtering (tournaments only), badge status mapping
- `OtrApiService` - o!TR API client
  - Methods: getMatches(), getMatchData()
- `TwitchService` - Twitch API client
  - Methods: checkStreams(), getStreamStatus()
- `DiscordService` - Discord webhook sender (enhanced)
  - Methods: sendMessage(), sendEmbed()
  - Features: Host avatar support, embed formatting with star ratings
  - Environment variable-based webhook URL configuration

### Business Logic Services
- `BatchTransactionService` - Transaction ID management
  - Methods: generateTransactionId(), storeTournamentIds(), getTournamentIds()
  - Multi-stage job coordination with Postgres-backed handoff state
- `ForumParser` - Parse osu! forum topics
  - Methods: parseForumTopic(), parseStaffFromBBcode()
  - Multilingual support (Chinese, Korean, Turkish)
  - Dual regex patterns for role extraction
- `ImportService` - Import orchestration
  - Methods: importTournamentFromTcomm(), importTournamentFromOtr()
  - tcomm import: Filters by state='archived', creates host as tournament staff
  - Features: Dry-run support, fuzzy matching, deduplication, validation
- Match statistics service retired; user-facing results use participation records
- `BwsCalculator` - Bayesian Weighted Score
  - Methods: calculate(), normalize()
- `YearRecapService` - Yearly recap generation
  - Methods: generate(), createPdf()
- `AuditLogger` - Admin action logging
  - Methods: log(), getRecentLogs()
- `BannerCacheService` - Discord banner caching to R2
  - Methods: cacheBanner(), getCachedUrl()
  - Uploads banners to R2, returns public URL

### **NEW Services**
- `SearchService` - **NEW** Global search functionality
  - Methods: search(), searchUsers(), searchTournaments()
  - Features: Fuzzy matching, ranked results, previous username search
  - Performance: Uses composite indexes, result caching (5 min TTL)
- `SipService` - Manages SIP fetch queue for Google Apps Script integration
  - Methods:
    - `queueUserForSipFetch()` - Queue single user (osu!standard only)
    - `queuePodiumUsers()` / `queuePodiumUsersRange()` - Queue podium users by year range
    - `queueUserByOsuId()` - Queue specific user by osu_id
    - `getPendingCount()` - Get pending queue count
    - `getFailedItems()` - Get failed queue items for debugging
    - `retryFailed()` - Retry failed items (up to 50)
- `SyncUserProfilesBatchHandler` - **NEW** Batch processing for user profile sync
  - Methods: handle(), trackProgress()
  - Features: Progress tracking, error handling, batch size management

### Support Services
- `BbcodeParser` - BBcode to HTML conversion
- `OsuSocialiteProvider` - osu! OAuth2 Socialite provider

## Jobs (app/Jobs)

### Tournament Parsing (4-Stage Chain)
- `ParseForumTopicJob` - Stage 1: Parse forum (parallel)
  - Cooldown check (24 hours between parses)
  - Transaction ID generation
- `AggregateStaffJob` - Stage 2: Aggregate staff (bridge)
  - Collect unique osu! user IDs
- `BatchFetchUsersJob` - Stage 3: Batch fetch users
  - Smart API response unwrapping (handles nested 'users' key)
  - Duplicate detection with logging
- `BatchMergeStaffJob` - Stage 4: Attach staff
  - Update tournament host from organizer
  - Create TournamentParseHistory records

### Import Jobs
- `ImportTournamentsFromTcommJob` - Batch tournament import
  - Pagination, deduplication
- `ImportTournamentWinnerBadgesJob` - Badge metadata import from osu! API
  - Fetches badges for tournament winners via `/users/{id}/badges` endpoint
  - **NEW:** Supports tri-badge tournaments (1st, 2nd, 3rd place)
  - Matches badges by forum topic ID extraction from badge URL
- `ImportSingleTournamentBadgesJob` - Import badges for one tournament
  - **NEW:** Enhanced tri-badge tournament support
  - Focused badge import for specific tournament
- `SyncTournamentBadgesToUsersJob` - Sync badges to user_badges table
  - Copies tournament winner badges to user profile
  - Enables BWS calculation with badge data
- `SyncTournamentWinnersUserDataJob` - User data synchronization
  - Batch syncs username and country_code using `getUsers()` (50 users per request); avatar URLs are derived from osu_id
  - Updates both `tournament_winners` and `users` tables
  - Automatic user account linking and soft-delete restoration
  - Stores profile cover_url in metadata JSON field
- `TournamentBadgeBatchHandler` - Batch processing for badge imports
  - Handles large batches of badge imports
  - Chunking for performance optimization
- `ImportMatchJob` - Single match import
  - Duplicate detection, scoring
- `ImportMatchesFromOtrJob` - Historical match import
  - Bulk processing

### Notification Jobs
- `SendNotificationJob` - Send notifications (enhanced)
  - Channels: Discord webhook, email
  - Retry logic, rate limiting
  - Enhanced queue processing
- `PostTournamentToDiscordJob` - Tournament announcements (enhanced)
  - Embed formatting with host avatars
  - Star rating range display
  - BWS rank formatting with commas
  - Skip webhook option via `skip_discord_webhook` parameter

### User Jobs
- `SyncUserFromOsu` - Sync user profile
  - Triggers: Login, scheduled sync
- `SyncUserProfilesJob` - **NEW** Batch user profile sync with tracking
  - Features: Batch processing, progress tracking, error handling
  - Used by: SyncUserProfiles command, SyncPodiumUsers command
- `ImportTournamentWinnerBadgesJob` - Badge metadata import
  - **NEW:** Supports tri-badge tournaments
  - Fetches badges via osu! API /users/{id}/badges
  - Matches badges by forum topic ID extraction

## Console Commands (app/Console/Commands)

### Backup Commands
- `CreateScheduledBackup` - Automated daily backup (08:55 UTC)
  - CreatesBackup trait for R2 upload
  - Discord webhook notifications
  - Automatic cleanup of old backups (>7 days)
- `BackupDatabase` - Manual on-demand backup
  - Options: --description, --disk
  - Same R2 upload pipeline as scheduled backup
- `ListBackups` - List available backups in R2
  - Options: --days, --all
  - Displays: size, date, age
- `RestoreDatabase` - Restore from R2 backup (⚠️ DESTRUCTIVE)
  - Requires confirmation (unless --force)
  - Downloads from R2 to temp, restores via psql
- `CleanupBackups` - Clean old backups from R2
  - Options: --days, --force, --dry-run
  - Shows preview before deletion

### Tournament Commands
- `ParseTournaments` - Daily tournament parsing (09:00 UTC)
  - Uses CreatesBackup trait for automatic pre-parsing backup
  - Automatic restore if parsing fails
  - **Only updates pending tournaments** (performance optimization)
- `Tournaments/BatchStatus` - Check batch job status for parsing operations
- `Tournaments/ReparseStaff` - Re-parse specific tournament staff
  - Options: --id, --dry-run, --force
- `TestForumParser` - Test forum parsing in isolation
  - Useful for debugging BBcode parsing issues
- `DiagnoseStaffExtraction` - Debug staff extraction from forum topics
  - **NEW:** Enhanced with consolidated user sync logic
  - Shows raw BBcode, regex matches, extracted roles

### Badge Commands
- `ImportTournamentBadges` - Import badge metadata for tournament winners
  - Fetches badges from osu! API for all tournament winners
  - **NEW:** Supports tri-badge tournaments
  - Updates badge_image_url, badge_description, badge_awarded_at
  - Links winners to user accounts
- `SyncTournamentBadges` - Sync tournament badges from API
  - Batch syncs badge data for multiple tournaments
  - Updates tournament_winners table with latest badge info

### Notification Commands
- `SendRegistrationReminders` - Send registration reminder notifications
  - Queries tournaments with upcoming registration deadlines
  - Sends notifications to users watching those tournaments

### User Commands
- `SyncUserProfiles` - **NEW** Consolidated user profile sync command
  - Replaces: SyncTournamentWinnersUserData
  - Features: Batch tracking, progress reporting, error handling
  - Options: --batch-size, --delay, --force, --dry-run
- `SyncTournamentWinnersUserData` - Batch sync winner usernames/avatars
  - **DEPRECATED:** Use SyncUserProfiles instead
  - Updates tournament_winners and users tables
  - 50 users per batch (API limit)
  - Automatic account linking

### **NEW Podium Commands**
- `SyncPodiumUsers` - Sync podium user data from osu! API
  - Options: --year, --start-year, --end-year, --force, --dry-run, --skip-sip, --batch-size, --delay
  - Syncs basic profile info, badges, rank history for ALL modes
  - Queues SIP fetch for osu!standard players (unless --skip-sip)
  - Sets main_mode_source to 'podium_sync'
- `SyncPodiumGamemodes` - Set correct main_mode for podium users
  - Options: --dry-run, --rollback, --start-year, --end-year
  - Automatic for single-mode tournaments
  - Interactive for multi-mode tournaments

### **NEW Maintenance Commands**
- `CleanupOrphanedUsers` - Clean up orphaned user records
  - Removes users with no relationships (staff, matches, badges, etc.)
  - Options: --dry-run, --force
  - Useful for maintaining data hygiene

### System Commands
- `CheckStreams` - Verify Twitch stream status
  - Checks all tournament Discord URLs for active streams
  - Updates tournament stream status
- `VerifyTestDatabase` - Verify test database isolation
  - Ensures tests use separate database
  - Prevents production data access during tests

### Import Commands
- `ImportTournamentsFromTcomm` - Batch import from tcomm API
- `ImportMatchesFromOtr` - Historical match import
- `ImportHistoricalData` - Import historical tournament data
  - Combines data from multiple sources
  - Deduplicates existing records

## Middleware (app/Http/Middleware)

- `ApiAuthenticate` - API authentication
  - **NEW:** Token validation for SIP API endpoints
- `EnsureUserSetupComplete` - Require user setup completion
- `RoleMiddleware` - Role-based access control
- `SecureHeaders` - Security headers
- `SetLocale` - Locale configuration
- `VerifyCsrfToken` - CSRF validation
- `ThrottleRequests` - Rate limiting (60 req/min for search API)

## Form Requests (app/Http/Requests)

### Admin Requests
- `ManualImportRequest` - Manual import validation
- `RejectTournamentRequest` - Tournament rejection validation
- `TournamentUpdateRequest` - Tournament update validation

### Public Requests
- `CompleteSetupRequest` - User setup completion
- `SubmitMatchRequest` - Match submission validation
- `UpdateSettingsRequest` - User settings validation
- **NEW:** `SearchRequest` - Search query validation
  - Validates query parameter (min 2 chars, max 50 chars)
  - Applies rate limiting (60 req/min)

## Key Routes (96 total)

### Admin Routes
```
GET  /admin - Admin dashboard
GET  /admin/audit-log - Audit log viewer
GET  /admin/imports - Import jobs list
POST /admin/imports/manual - Manual import
GET  /admin/matches/pending - Pending matches
GET  /admin/tournaments/pending - Pending tournaments
```

### Public Routes
```
GET  / - Home page
GET  /auth/login - OAuth2 login
GET  /auth/callback - OAuth2 callback
POST /auth/logout - Logout
GET  /dashboard - User dashboard
GET  /tournaments - Tournaments list
GET  /tournaments/{tournament} - Tournament details
POST /tournaments/{tournament}/watch - Watch tournament
DELETE /tournaments/{tournament}/watch - Unwatch
GET  /users/{user} - User profile
GET  /users/me/settings - User settings
PATCH /users/me/settings - Update settings
POST /matches - Submit match link
GET  /users/{user}/stats - User stats
POST /users/me/recap/{year} - Generate year recap
GET /users/me/recap/{year}/download - Download recap PDF
```

### **NEW API Routes**
```
GET  /api/search - Global search (users, tournaments)
GET  /api/sip/queue - Get pending SIP fetch items
POST /api/sip/update - Update SIP from Google Sheets results
```

## Request Lifecycle

```
1. Route (routes/web.php, routes/api.php)
2. Middleware (Auth, CSRF, Role check, Rate limiting)
3. Controller
   ├─→ Form Request Validation
   ├─→ Service (business logic)
   └─→ Response (View/Json/Redirect)
4. View (resources/views/)
```

## Design Patterns

### Service Layer Pattern
- Business logic in services, not controllers
- Controllers orchestrate services
- Dependency injection

### Job Pattern
- Heavy tasks in queue jobs
- Batch processing for efficiency
- Retry with exponential backoff (3 attempts: 60s, 120s, 240s)

### Event/Listener Pattern
- Decoupled notification system
- Async event processing
- Multiple listeners per event

### SIP Integration Pattern (NEW)
- Laravel → Queue → Google Apps Script → skillissue.app API
- Asynchronous processing with retry logic
- Token-based authentication for external systems

### Search Pattern (NEW)
- Controller → Service → Model (with scopes)
- Composite indexes for performance
- Result caching with TTL
- Rate limiting for API protection
