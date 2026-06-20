# Changelog

All notable changes to Tourney Method will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added - March 11, 2026 (Latest)
- **Tournament Winner and Badge Import System** - Complete automated import pipeline
  - **osu! API badge metadata import** - Fetches badge images, descriptions, awarded dates
  - **User data synchronization** - Batch sync of usernames, avatars, country codes from osu! API
  - **Gamemode-based badge filtering** - Filter badges by game mode (osu!, taiko, catch, mania)
  - **User-to-winner linking** - Automatic linking between `users` and `tournament_winners` tables
  - **Batch API processing** - Efficient handling of 562 users in ~12 API calls (50 per request)

- **New Import and Sync Commands**
  - `badges:import-tournament-winners` - Import badge metadata from osu! API for existing winner records
  - `winners:sync-user-data` - Sync username, avatar, country_code from osu! API (batch processing)

- **Enhanced Tournament Winners Schema** - New columns for badge and user data
  - `badge_image_url`, `badge_image_2x_url` - High-resolution badge images
  - `badge_description` - Badge name and description text
  - `badge_awarded_at` - Date badge was awarded to user
  - `badge_url` - Link to badge on osu! website
  - `gamemode` - Game mode filter (osu!, taiko, catch, mania)
  - `user_id` - Foreign key linking to `users` table
  - `metadata` - JSON field for cover_url and additional data

- **User Profile Enhancements**
  - Gamemode filter dropdown on badge display
  - Separate badge sections by game mode
  - "Badges" heading and improved layout
  - Active filter state management with Alpine.js

- **Tournament Model Relations**
  - `hasStaff()` method - Check if tournament has any staff records
  - Used in templates to conditionally display staff sections

- **BWS Calculator Updates**
  - Updated to use `tournament_winners.placement` instead of removed `rank` field
  - Maintains backward compatibility with existing data

### Technical Improvements
- **OsuApiService batch optimization** - `getUsers()` method chunks requests into groups of 50
- **Database relationship fixes** - Proper `user_id` foreign key linking
- **API response handling** - Robust parsing of osu! API user data
- **Rate limiting awareness** - Conservative 0.5s delay between API batch calls
- **Error handling** - Graceful handling of deleted accounts and API failures

### Data Import Summary
- **520 tournament winners** imported from tcomm API (one-time import, command removed)
- **192 badges** imported with metadata from osu! API
- **561 users** synced with current usernames and avatars
- **516 winners** linked to `users` table
- **753 tournament winner records** updated with latest data

### Performance Impact
- **Batch API calls** - Reduced from 562 individual requests to ~12 batch requests
- **Fast execution** - Complete sync in ~40 seconds (vs. 4.7 minutes with individual calls)
- **Database efficiency** - Single query bulk updates for user linking
- **Queue timeout** - Extended job timeout to 300 seconds for batch processing

### Breaking Changes

None - All changes are backward compatible.

### Upgrade Notes

**To sync existing tournament winners with osu! API:**
```bash
# Import badge metadata for existing winners
php artisan badges:import-tournament-winners

# Sync user data (username, avatar, country)
php artisan winners:sync-user-data
```

**Database migration required:**
```bash
# Run new migrations for tournament_winners schema
php artisan migrate
```

### Documentation Updates
- Updated `docs/COMMANDS.md` with import and sync commands
- Updated `docs/CODEMAPS/data.md` with tournament_winners schema changes
- Updated `docs/CODEMAPS/backend.md` with new jobs and services

## [Unreleased]

### Added - March 9, 2026
- **Automated Database Backup System** - Complete R2-based backup solution
  - **Scheduled backups** - Daily at 08:55 UTC (5 min before tournament parsing)
  - **Pre-parsing backups** - Automatic backup before `tournaments:parse` command
  - **Manual backup commands** - `db:backup`, `db:list`, `db:restore`, `db:clean`
  - **Cloudflare R2 storage** - Compressed backups (~600 KB) with automatic cleanup
  - **7-day retention policy** - Configurable via `BACKUP_RETENTION_DAYS`
  - **Discord notifications** - Success/failure alerts via webhook
  - **Upload verification** - Ensures backups uploaded successfully to R2
  - **Rollback support** - Automatic restore if parsing fails

- **New Backup Commands**
  - `backups:create` - Scheduled daily backup (runs automatically)
  - `db:backup` - Manual on-demand backup with optional description
  - `db:list` - List available backups with filtering by age
  - `db:restore` - Restore database from R2 backup (with confirmation)
  - `db:clean` - Clean up old backups with dry-run support

- **Enhanced CreatesBackup Trait** - R2 upload and Discord notification support
  - Automatic R2 upload with verification
  - Discord webhook notifications for all backup operations
  - Cleanup of old backups based on retention policy
  - Support for both scheduled and manual backups

- **Database Backup Configuration** - New environment variables
  - `BACKUP_ENABLED` - Enable/disable automatic backups
  - `BACKUP_DISK` - Storage disk (s3 for R2)
  - `BACKUP_DIRECTORY` - R2 directory prefix
  - `BACKUP_RETENTION_DAYS` - Retention period (default: 7 days)
  - `BACKUP_VERIFY_UPLOAD` - Upload verification toggle
  - `BACKUP_DISCORD_WEBHOOK` - Notification webhook URL

- **Comprehensive Testing**
  - 24 tests passing (8 unit + 16 integration)
  - 52 assertions covering all backup functionality
  - Test files: `BackupServiceTest.php`, `BackupCommandTest.php`
  - R2 upload verification, restore operations, cleanup procedures

- **Dependency Updates**
  - `league/flysystem-aws-s3-v3` (v3.32.0) - AWS S3 filesystem adapter
  - `aws/aws-sdk-php` (v3.372.1) - AWS SDK for PHP
  - `aws/aws-crt-php` (v1.2.7) - AWS CRT for PHP

### Technical Improvements
- Improved error handling for backup failures
- Automatic retry logic for R2 uploads
- File size tracking and reporting
- Upload duration monitoring
- Comprehensive logging for debugging backup issues

### Performance Impact
- **Minimal overhead** - Backups run outside peak hours (08:55 UTC)
- **Fast uploads** - ~0.2 seconds to R2 (598 KB compressed)
- **Automatic cleanup** - Prevents unlimited storage growth
- **No parsing delays** - Backup completes 5 minutes before parsing

### Breaking Changes

None - All changes are backward compatible.

### Upgrade Notes

**Required for Railway deployment:**
1. Add backup environment variables to Railway dashboard
2. Verify R2 credentials are configured
3. Backup system is production-ready

**Optional for local development:**
- Add R2 credentials to local `.env` for testing
- Use `BACKUP_DISK=local` for testing without R2

```bash
# Install new dependencies
docker compose exec -u sail laravel.test composer install

# Clear config cache
docker compose exec -u sail laravel.test php artisan config:clear
```

### Documentation Updates
- Updated `docs/COMMANDS.md` with backup command reference
- Updated `docs/ENV.md` with backup configuration variables
- Updated `docs/RUNBOOK.md` with backup procedures and disaster recovery
- Added comprehensive usage examples for all backup commands

## [Unreleased]

### Added - February 9, 2026
- **GitHub-style Diff Viewer** - Word-level diff highlighting for parse history changes
  - Green/red color coding for additions/deletions
  - Staff role comparison across parse versions
  - Integrated into admin tournament review page

- **Role-Sorted Staff Display** - Staff displayed in priority order with color coding
  - organizer (red) > mapper (orange) > mappooler (amber) > referee (yellow)
  - Role-based color badges throughout admin UI
  - Consistent sorting across tournament pages

- **Staff Versioning System** - Complete parse history tracking
  - `tournament_parse_histories` table with JSON diff snapshots
  - Parse count tracking on tournaments
  - User attribution for each parse (`last_parsed_by`)
  - Parse source tracking (forum, manual, reparse)

- **Enhanced Admin Commands**
  - `tournaments:reparse-staff --id=N` - Re-parse specific tournament
  - Role-sorted staff loading in admin and reparse command
  - Parse history accessible via admin UI

### Fixed - February 9, 2026
- **host_username population** - Auto-populate from users table when NULL
  - Runs on tournament view if host_username is missing
  - Uses cached user data from osu_id lookup

- **Admin review page** - Fixed staff relationship errors
  - Removed invalid `->with('user')` call on tournaments
  - Added avatar fallbacks for staff without avatar_url
  - Corrected property access on staff models

### Added - February 6, 2026
- **AggregateStaffJob** - New bridge job for multi-stage pipeline coordination
  - Aggregates unique osu! user IDs across all tournaments in transaction
  - Prepares data for BatchFetchUsersJob
  - Implements proper PHPStan type annotations

- **BatchTransactionService** - Transaction ID management service
  - Generate unique transaction IDs for parsing runs (format: `txn_{uniqid}_{microtime}`)
  - Store/retrieve tournament IDs per transaction
  - Add individual tournaments to transactions
  - Cache-based storage with 5-minute TTL

- **ParseForumTopicJob enhancements**
  - Transaction ID integration
  - Cooldown check (24 hours between parses)
  - Improved error handling and logging
  - Support for `--dry-run` and `--force` flags

- **OsuApiService improvements**
  - Smart API response unwrapping (handles `['users' => [...]` format)
  - Duplicate detection in `getUsers()` with logging
  - Detailed chunk processing logs
  - Better error messages for empty arrays

### Fixed - February 6, 2026
- **OsuApiService::getUsers()** - Fixed to correctly unwrap API response from 'users' key
  - API returns `['users' => [['id' => 123, ...], ...]]` format
  - Now properly extracts user array before indexing by osu_id
  - Added logging for response type detection

- **Admin TournamentController** - Removed invalid `->with('user')` call
  - Tournament model doesn't have a 'user' relationship
  - Fixed undefined relationship error on admin review page

- **Tournament staff relationship** - Added pivot 'id' to relationship
  - Enables proper staff management operations (delete/update)
  - Staff records now include pivot.id for database operations

- **Admin review blade** - Fixed staff property access
  - Added avatar fallback for staff without avatar_url
  - Corrected property access on staff models
  - Fixed undefined array key warnings

- **PHPStan type annotations** - Fixed job backoff property types
  - Changed `public $backoff = [60, 120, 240]`
  - To: `public int|array $backoff = [60, 120, 240]`
  - Fixed template type resolution in BatchFetchUsersJob

### Removed - February 6, 2026
- **BatchStatus command** - Removed unused `tournaments:batch-status` command
  - Functionality replaced by Horizon dashboard monitoring
  - 156 lines of obsolete code removed

### Technical Improvements
- Enhanced logging throughout parsing pipeline
- Better transaction tracking for debugging
- Improved error messages with context
- PHPStan level 6 compliance maintained
- GrumPHP hooks pass on all commits

## Database Changes

### tournament_staff pivot table
- Added `id` column as auto-increment primary key
- Enables direct staff record operations via pivot ID

### No migration files added
- All changes are non-breaking to existing schema
- Pivot ID addition is backward compatible

## Documentation Updates

- Updated README.md with 4-stage pipeline description
- Added transaction ID documentation
- Updated CONTRIBUTING.md with service examples
- Added multi-stage job chain patterns
- Documented new BatchTransactionService usage

## Performance Impact

- **60% reduction in osu! API calls** via deduplication
- **Faster parsing** with parallel topic processing
- **Better retry logic** prevents permanent job failures
- **Transaction tracking** enables better debugging

## Breaking Changes

None - All changes are backward compatible.

## Upgrade Notes

No manual intervention required. Deploy as usual:

```bash
docker compose exec laravel.test composer install
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan horizon:terminate
```

## Testing

All existing tests pass. Added new test coverage for:
- AggregateStaffJob
- BatchTransactionService
- OsuApiService response unwrapping
- Transaction ID generation

Test coverage maintained at 80%+ minimum.
