# Artisan Commands Reference

**Last Updated:** 2026-06-15

**Recent Changes:**
- **2026-06-15:** Added `maintenance:database-prune` for parse history pruning/compaction, completed batch pruning, and optional PostgreSQL physical reclaim
- **2026-05-18:** Refined `sync:user-profiles --essential-only` to sync missing podium badge metadata for all podium placements on ended non-rejected badged tournaments; scheduler now runs essential profile sync before orphan cleanup
- **2026-05-10:** Added `--essential-only` to `sync:tournament` and `sync:user-profiles` for targeted API-saving syncs
- ✅ **2026-05-07:** Replaced `badges:sync-tournament` with `sync:tournament` and removed duplicate `badges:import-tournament-winners`
- ✅ **2026-05-08:** Refined `sync:user-profiles` winner flow to sync only the current `main_mode` rank and show queued runtime estimates
- ✅ **2026-05-07:** Refined `sync:user-profiles` winner flow to save raw user badge data before tournament badge linking
- ✅ **2026-04-11:** Added `otr:import-dump` command for importing tournaments from OTR public dump files
- ✅ **2026-03-30:** Added batch tracking to `sync:user-profiles --queue` (chunked jobs with Horizon visibility)
- ✅ **2026-03-27:** Added dynamic year defaults to `sync:user-profiles` (auto-calculates last year to current year)
- ✅ **2026-03-27:** Fixed mode name mismatch ("fruits" → "catch") in OsuApiService
- ✅ **2026-03-27:** Implemented `main_mode_source` tracking to preserve user's manual mode selection
- ✅ **2026-03-27:** Removed deprecated commands: `sync:podium-users`, `sync:podium-gamemodes`, `tournaments:diagnose-staff`
- ✅ **2026-03-27:** Fixed stale model display bug in sync commands (now shows updated main_mode correctly)

This document provides a comprehensive reference for all custom Artisan commands in the Tourney Method application.

---

## Table of Contents

1. [Backup Commands](#backup-commands)
2. [Monitoring Commands](#monitoring-commands)
3. [Tournament Processing Commands](#tournament-processing-commands)
4. [Import Commands](#import-commands)
5. [Utility Commands](#utility-commands)
6. [Queue Configuration](#queue-configuration)
7. [Scheduled Tasks](#scheduled-tasks)
8. [Production Setup](#production-setup)
9. [Troubleshooting](#troubleshooting)

---

## Backup Commands

### `backups:create`

Create scheduled database backup (runs automatically before tournament parsing).

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan backups:create [options]
```

**Description:**
- Creates PostgreSQL database backup using `pg_dump`
- Compresses backup with gzip
- Uploads to Cloudflare R2 storage
- Cleans up old backups (retention: 7 days)
- Sends Discord notification on success/failure

**Schedule:** Daily at 08:55 UTC (5 minutes before tournament parsing)

**Production:** ✅ Ready (Railway)

**Safety:** ✅ Non-destructive

**Storage:** Cloudflare R2 (`s3` disk)

**Options:**
- `--description=...`: Optional description for this backup

**Examples:**
```bash
# Run scheduled backup manually
docker compose exec -u sail laravel.test php artisan backups:create

# With description
docker compose exec -u sail laravel.test php artisan backups:create --description "Before schema change"
```

---

### `db:backup`

Create on-demand database backup to R2.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan db:backup [options]
```

**Description:**
- Creates immediate database backup
- Uploads to R2 with verification
- Sends Discord notification with backup details
- Useful before manual data modifications

**Production:** ✅ Ready

**Safety:** ✅ Non-destructive

**Storage:** Cloudflare R2 (`s3` disk)

**Options:**
- `--description=...`: Optional description for this backup
- `--disk=s3`: Storage disk (default: s3 for R2)

**Examples:**
```bash
# Create manual backup
docker compose exec -u sail laravel.test php artisan db:backup

# With description
docker compose exec -u sail laravel.test php artisan db:backup --description "Before migration"
```

---

### `db:list`

List available database backups in R2.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan db:list [options]
```

**Description:**
- Lists all backups in R2 storage
- Shows file size, creation date, and age
- Supports filtering by age
- Displays total size and count

**Production:** ✅ Ready

**Safety:** ✅ Read-only

**Options:**
- `--disk=s3`: Storage disk (default: s3)
- `--days=7`: Only show backups from last N days
- `--all`: Show all backups regardless of age

**Examples:**
```bash
# List recent backups (last 7 days)
docker compose exec -u sail laravel.test php artisan db:list

# List all backups
docker compose exec -u sail laravel.test php artisan db:list --all

# List backups from last 30 days
docker compose exec -u sail laravel.test php artisan db:list --days=30
```

---

### `db:restore`

Restore database from R2 backup.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan db:restore <backup_file> [options]
```

**Description:**
- Downloads backup from R2
- Restores database using `psql`
- **Destructive:** Replaces entire database
- Sends Discord notification

**⚠️ WARNING:** This will **REPLACE** your current database!

**Production:** ✅ Ready (with caution)

**Safety:** ⚠️ **Destructive** - requires confirmation unless `--force`

**Arguments:**
- `backup_file`: R2 path (e.g., `backups/tourney_method_2026-03-09_12-34-56.sql.gz`)

**Options:**
- `--disk=s3`: Storage disk (default: s3)
- `--force`: Skip confirmation prompt

**Examples:**
```bash
# Interactive restore (requires confirmation)
docker compose exec -u sail laravel.test php artisan db:restore backups/tourney_method_2026-03-09_12-34-56.sql.gz

# Force restore (no confirmation)
docker compose exec -u sail laravel.test php artisan db:restore backups/tourney_method_2026-03-09_12-34-56.sql.gz --force
```

---

### `db:clean`

Clean up old backups from R2.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan db:clean [options]
```

**Description:**
- Deletes backups older than retention period
- Shows preview of files to be deleted
- Reports space freed
- Requires confirmation unless `--force`

**Production:** ✅ Ready

**Safety:** ⚠️ **Destructive** - requires confirmation unless `--force`

**Options:**
- `--days=7`: Delete backups older than N days (default: 7)
- `--force`: Skip confirmation prompt
- `--disk=s3`: Storage disk (default: s3)
- `--dry-run`: Show what would be deleted without deleting

**Examples:**
```bash
# Preview cleanup (dry run)
docker compose exec -u sail laravel.test php artisan db:clean --dry-run

# Clean backups older than 30 days (with confirmation)
docker compose exec -u sail laravel.test php artisan db:clean --days=30

# Force cleanup (no confirmation)
docker compose exec -u sail laravel.test php artisan db:clean --days=7 --force
```

---

## Monitoring Commands

### `streams:check`

Check Twitch streams for osu! tournaments and send notifications.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan streams:check
```

**Description:**
- Fetches live streams from Twitch API for tracked tournaments
- Sends Discord notifications when streams go live
- Queues `SendNotificationJob` for each active stream

**Schedule:** Every 3 hours (*/3 * * * *)

**Production:** ✅ Ready

**Safety:** ✅ Read-only

**Queue:** Yes (SendNotificationJob)

**Options:** None

**Examples:**
```bash
# Run manually (outside schedule)
docker compose exec -u sail laravel.test php artisan streams:check
```

---

### `reminders:registration`

Send registration deadline reminders to tournament organizers.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan reminders:registration
```

**Description:**
- Finds tournaments with registration closing in 24 hours
- Sends reminder notifications to organizers via Discord
- Queues `SendNotificationJob` for each tournament

**Schedule:** Every hour (0 * * * *)

**Production:** ✅ Ready

**Safety:** ✅ Read-only

**Queue:** Yes (SendNotificationJob)

**Options:** None

**Examples:**
```bash
# Run manually (outside schedule)
docker compose exec -u sail laravel.test php artisan reminders:registration
```

---

## Tournament Processing Commands

### `tournaments:parse`

Parse osu! forum for tournament posts and create/update tournament records.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan tournaments:parse [topic_id] [options]
```

**Description:**
- Fetches tournament topics from osu! forum API
- If `topic_id` is provided, parses that specific topic
- If no ID is provided, fetches 50 most recent topics from forum_id=55
- Parses BBcode for tournament metadata (name, mode, dates, staff)
- Creates 3-stage job chain for efficient processing:
  1. ParseForumTopicJob - Parse forum topics
  2. AggregateStaffJob + BatchFetchUsersJob - Aggregate and fetch user data
  3. BatchMergeStaffJob - Attach staff to tournaments

**Schedule:** Daily at 9 AM (0 9 * * *)

**Production:** ✅ Ready

**Safety:** ⚠️ Creates/updates tournaments

**Queue:** Yes (3-stage chain)

**Arguments:**
- `topic_id`: Optional specific forum topic ID to parse

**Options:**
- `--dry-run`: Parse without creating tournaments (preview mode)
- `--force`: Skip cooldown check AND force update of existing (approved/rejected) tournaments
- `--confirm`: Explicitly confirm data modification
- `--no-backup`: Skip automatic database backup

**Examples:**
```bash
# Standard parsing (last 50 topics)
docker compose exec -u sail laravel.test php artisan tournaments:parse --confirm

# Parse a specific topic ID
docker compose exec -u sail laravel.test php artisan tournaments:parse 1234567 --confirm

# Force re-parse of an existing tournament (overwrite all data)
docker compose exec -u sail laravel.test php artisan tournaments:parse 1234567 --force --confirm

# Preview without database changes
docker compose exec -u sail laravel.test php artisan tournaments:parse 1234567 --dry-run
```

**Output (dry-run mode):**
```
✅ Parsed 15 tournaments
✅ Extracted 45 staff members
✅ Identified 12 unique osu! users
⚠️  Preview mode - no database changes made
```

---

### `tournaments:test-parser`

Test forum parser algorithm against existing tournaments.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan tournaments:test-parser [options]
```

**Description:**
- Tests parser accuracy against existing tournament data
- Compares parsed results with database records
- Generates accuracy report with discrepancies

**Production:** ✅ Ready (development/testing only)

**Safety:** ✅ Read-only

**Queue:** No

**Options:**
- `--count=10`: Number of tournaments to test (default: 10)
- `--detailed`: Show detailed results for each tournament

**Examples:**
```bash
# Test 10 tournaments
docker compose exec -u sail laravel.test php artisan tournaments:test-parser

# Test 50 tournaments with detailed output
docker compose exec -u sail laravel.test php artisan tournaments:test-parser --count=50 --detailed
```

**Output:**
```
Testing parser against 10 tournaments...

✅ Tournament #62 (osu!! Score Cup)
   - Name: ✅ Match
   - Mode: ✅ Match (osu!)
   - Staff: ✅ Match (12/12)

⚠️  Tournament #145 (Tournament XYZ)
   - Name: ❌ Mismatch (parsed: "XYZ Tournament", db: "Tournament XYZ")
   - Mode: ✅ Match (taiko)
   - Staff: ⚠️  Partial (8/10 matched)

Results: 8/10 accurate (80%)
```

---

### `tournaments:reparse-staff`

Re-parse tournaments to extract staff with fixed parser.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan tournaments:reparse-staff [options]
```

**Description:**
- Re-fetches forum posts for specified tournaments
- Re-parses staff data with current parser logic
- Updates staff records with new data
- Creates staff version history for rollback

**Production:** ✅ Ready

**Safety:** ⚠️ Modifies staff data (with versioning)

**Queue:** No

**Options:**
- `--id=N`: Re-parse specific tournament ID
- `--force`: Force re-parse even if data hasn't changed
- `--dry-run`: Preview changes without saving

**Examples:**
```bash
# Re-parse all tournaments (interactive)
docker compose exec -u sail laravel.test php artisan tournaments:reparse-staff

# Re-parse specific tournament
docker compose exec -u sail laravel.test php artisan tournaments:reparse-staff --id=62

# Preview changes for tournament #62
docker compose exec -u sail laravel.test php artisan tournaments:reparse-staff --id=62 --dry-run

# Force re-parse all tournaments
docker compose exec -u sail laravel.test php artisan tournaments:reparse-staff --force
```

**Output (dry-run mode):**
```
Re-parsing tournament #62 (osu!! Score Cup)...

Current staff: 12 members
Parsed staff: 14 members

Changes:
+ John Doe (mapper)
+ Jane Smith (referee)

⚠️  Preview mode - no changes saved
```

---

### `tournaments:batch-status`

Show parsing batch status and job information.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan tournaments:batch-status [options]
```

**Description:**
- Displays status of recent parsing batches
- Shows job counts, completion status, errors
- Useful for monitoring parsing operations

**Production:** ✅ Ready

**Safety:** ✅ Read-only

**Queue:** No

**Options:**
- `--id=N`: Check specific batch by ID
- `--limit=10`: Number of recent batches to show (default: 10)

**Examples:**
```bash
# Show 10 most recent batches
docker compose exec -u sail laravel.test php artisan tournaments:batch-status

# Show specific batch
docker compose exec -u sail laravel.test php artisan tournaments:batch-status --id=123

# Show 20 recent batches
docker compose exec -u sail laravel.test php artisan tournaments:batch-status --limit=20
```

**Output:**
```
Recent parsing batches:

Batch #123 (2026-03-06 09:00:15)
  Status: ✅ Completed
  Duration: 45 seconds
  Jobs: 50 ParseForumTopicJob, 1 AggregateStaffJob, 1 BatchFetchUsersJob, 1 BatchMergeStaffJob
  Tournaments: 50 created, 0 updated
  Errors: 0

Batch #122 (2026-03-05 09:00:12)
  Status: ⚠️  Partially completed
  Duration: 38 seconds
  Jobs: 45 completed, 5 failed
  Errors: 5 (see Horizon for details)
```

---

## Import Commands

### `import:historical`

Import historical tournaments from external APIs (tcomm, o!TR).

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan import:historical [options]
```

**Description:**
- Fetches historical tournament data from external APIs
- Imports tournament metadata, matches, and scores
- Links imported data to existing user accounts
- Handles rate limiting and API errors

**Production:** ⚠️ Requires external API validation

**Safety:** ⚠️ Significant data modification

**Queue:** Yes (ImportService handles job dispatch)

**Options:**
- `--source=tcomm|otr|combined`: Data source to import from (default: combined)
- `--resume`: Resume from last checkpoint
- `--dry-run`: Preview without saving to database
- `--link-users`: Link imported users to osu! accounts after import

**Examples:**
```bash
# Import from both sources
docker compose exec -u sail laravel.test php artisan import:historical --source=combined

# Import from tcomm only
docker compose exec -u sail laravel.test php artisan import:historical --source=tcomm

# Resume interrupted import
docker compose exec -u sail laravel.test php artisan import:historical --resume

# Preview import without saving
docker compose exec -u sail laravel.test php artisan import:historical --dry-run

# Import and link users
docker compose exec -u sail laravel.test php artisan import:historical --source=combined --link-users
```

**Output (dry-run mode):**
```
Importing from combined sources...

tcomm API:
  ✅ Found 150 tournaments
  ✅ Found 2,500 matches
  ✅ Found 15,000 scores

o!TR API:
  ✅ Found 200 tournaments
  ✅ Found 3,000 matches
  ✅ Found 20,000 scores

⚠️  Preview mode - no data imported
```

---

### `otr:import-dump`

Import tournaments from OTR (osu! Tournament Repository) public dump files.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan otr:import-dump [options]
```

**Description:**
- Imports tournament metadata from OTR's weekly public PostgreSQL dumps
- **Legal replacement for API scraping** - OTR Terms of Use compliant
- Downloads ~270MB dump files from Google Cloud Storage
- Verifies SHA256 checksum and GPG signature
- Parses PostgreSQL COPY statements for tournament data
- Transforms OTR schema to application schema with deduplication
- Queues forum parsing for newly created tournaments
- Tracks import history with rollback capability

**⚠️ IMPORTANT:** This replaces the deprecated API-based import method (`import:historical --source=otr`) which violated OTR's Terms of Use.

**Production:** ✅ Ready

**Safety:** ⚠️ Creates/updates tournaments (transaction-safe)

**Queue:** Yes (ParseForumTopicJob for new tournaments)

**Options:**
- `[version]` - Import specific dump version (e.g., `2026_04_07_11_50_02`)
- `--all` - Import all pending dumps (not yet imported)
- `--force` - Reimport even if already imported
- `--dry-run` - Preview changes without saving
- `--limit=N` - Import only N tournaments per dump (testing)

**Examples:**
```bash
# List available dumps
docker compose exec -u sail laravel.test php artisan otr:import-dump

# Import all pending dumps
docker compose exec -u sail laravel.test php artisan otr:import-dump --all

# Import specific version
docker compose exec -u sail laravel.test php artisan otr:import-dump 2026_04_07_11_50_02

# Force reimport specific version
docker compose exec -u sail laravel.test php artisan otr:import-dump 2026_04_07_11_50_02 --force

# Dry run to preview changes
docker compose exec -u sail laravel.test php artisan otr:import-dump --all --dry-run

# Limit import for testing
docker compose exec -u sail laravel.test php artisan otr:import-dump --all --limit=10
```

**Output (list mode):**
```
Available OTR dumps:
  1. 2026_04_14_11_50_03 (2026-04-14 11:50:03)
  2. 2026_04_07_11_50_02 (2026-04-07 11:50:02)
  3. 2026_03_31_11_50_01 (2026-03-31 11:50:01)
```

**Output (import mode):**
```
Importing 3 pending dump(s)...

Processing dump 1/3: otr-public-replica_2026_03_31_11_50_01.gz
Downloading... [====================] 100% (270 MB / 2 seconds)
Verifying SHA256... ✅
Verifying GPG signature... ✅
Extracting... ✅
Parsing tournaments... 500 found
Validating data... ✅
Importing tournaments...
  Created: 50
  Updated: 400
  Linked: 25
  Failed: 0
✅ Dump 1/3 imported successfully
Cleaning up files... ✅ deleted

[... dumps 2 and 3 processed similarly ...]

🔒 Protecting OTR titles from forum overwrites...
  Marked 1570 titles as 'otr' source

📋 Parsing forum topics for newly created tournaments (165)...
  [====================] 100% (165 / 165)

✅ Forum parsing complete!
  Host usernames found: 150
  Banners found: 140
  Staff members added: 350
  Titles preserved: 165 (forum titles ignored)

🎉 All done! Imported 165 new tournaments from 3 dump files.
  Total duration: 3 minutes 45 seconds
```

**What it does:**
1. **Fetches available dumps** from Google Cloud Storage bucket
2. **Identifies pending dumps** (not yet in `otr_import_history` table)
3. **Downloads and verifies** each dump (SHA256 + GPG signature)
4. **Extracts and parses** PostgreSQL COPY statements
5. **Validates tournament data** (forum URLs, dates, rulesets, required fields)
6. **Imports with 3-stage deduplication:**
   - **Update** existing tournaments by `otr_id` (exact match)
   - **Link** tournaments by `forum_topic_id` where `otr_id IS NULL` (first OTR link)
   - **Create** new tournaments for completely new entries
7. **Protects OTR titles** from forum parser overwrites (`import_source = 'otr'`)
8. **Queues forum parsing** for newly created tournaments (ParseForumTopicJob)
9. **Tracks import history** with statistics (created/updated/linked/failed counts)
10. **Cleans up** temporary dump files (sequential processing saves disk space)

**Data Transformation:**
- **Ruleset conversion:** OTR integers (0-5) → application mode arrays
  - 0 → `[{'mode': 'osu', 'key_count': null}]`
  - 1 → `[{'mode': 'taiko', 'key_count': null}]`
  - 2 → `[{'mode': 'catch', 'key_count': null}]`
  - 3 → `[{'mode': 'mania', 'key_count': null}]`
  - 4 → `[{'mode': 'mania', 'key_count': 4}]`
  - 5 → `[{'mode': 'mania', 'key_count': 7}]`
- **Forum URL parsing:** Extracts `forum_topic_id` from osu! forum URLs
- **Date handling:** Preserves original start/end timestamps
- **NULL handling:** Imports tournaments even if forum_url is NULL

**Import History Tracking:**

All imports are recorded in `otr_import_history` table:
```sql
CREATE TABLE otr_import_history (
    id SERIAL PRIMARY KEY,
    dump_version VARCHAR(50) NOT NULL UNIQUE,
    dump_url TEXT NOT NULL,
    imported_at TIMESTAMP DEFAULT NOW(),
    tournaments_imported INT DEFAULT 0,
    tournaments_updated INT DEFAULT 0,
    tournaments_linked INT DEFAULT 0,
    tournaments_failed INT DEFAULT 0,
    forum_parsed_count INT DEFAULT 0,
    forum_host_found INT DEFAULT 0,
    forum_staff_added INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'completed',
    error_message TEXT,
    sha256_checksum VARCHAR(64),
    gpg_signature_verified BOOLEAN DEFAULT FALSE
);
```

**Transaction Safety:**
- All imports wrapped in database transactions
- No partial imports - either fully succeeds or rolls back
- Dump files deleted only after successful import
- Rollback capability by `dump_version`

**Deduplication Strategy:**
Multiple tournaments CAN have the same `forum_topic_id` (multi-division tournaments):
1. **Primary key:** `otr_id` (unique OTR tournament identifier)
2. **Distinguished by:** Different `otr_id`, `modes`, or timestamps
3. **Example:** "Conyoh Cup 4" appears twice (osu! and taiko divisions)

**Storage Strategy:**
- Dump files: ~270MB each (compressed)
- **Sequential processing:** download → verify → import → delete (one at a time)
- **No permanent storage** of dump files (saves disk space)
- Only imported versions tracked in database

**Performance:**
- **Typical import:** ~500 tournaments per dump
- **Duration:** 3-5 minutes (including download, verification, import)
- **Rate limits:** None (uses public dumps, not API)
- **Network:** Downloads from Google Cloud Storage (fast, reliable)

**Security & Verification:**
- ✅ SHA256 checksum verification (provided by OTR)
- ✅ GPG signature verification (provided by OTR)
- ✅ Cryptographic verification ensures data authenticity
- ✅ No API key required
- ✅ OTR Terms of Use compliant

**Migration from API Method:**

⚠️ **DEPRECATED:** `import:historical --source=otr` (violates ToS)
```bash
# OLD METHOD (DO NOT USE)
php artisan import:historical --source=otr
```

✅ **NEW METHOD (USE THIS):**
```bash
# NEW METHOD (OTR ToS compliant)
php artisan otr:import-dump --all
```

**Benefits over API method:**
- ✅ Legal and allowed by OTR
- ✅ Faster (minutes vs hours)
- ✅ No API key required
- ✅ No rate limits
- ✅ More reliable
- ✅ Cryptographic verification (SHA256 + GPG)

**Related Services:**
- `OtrDumpService` - Core import logic (GCS, parsing, validation)
- `OtrDataValidator` - Validates tournament data
- `GcsClient` - Downloads dump files from GCS
- `ParseForumTopicJob` - Parses forum topics for new tournaments

**Future Enhancements:**
- Phase 2: Match & game import
- Phase 3: Scheduled auto-import (Thursdays 3 AM)
- Phase 4: Performance optimization (bulk upsert, streaming parser)

---

### `sync:tournament`

Sync approved badged tournaments from saved user badge data.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan sync:tournament [options]
```

**Description:**
- Reads canonical badge data already saved to `user_badges` by `sync:user-profiles`
- Checks approved badged tournaments and their podium users
- Links user badges back to `tournament_winners` and marks matched `user_badges` as BWS eligible only for `osu` gamemode winners
- Matches badges by forum topic/wiki URL or matching badge image URL
- Supports `--essential-only` to check only badge tournaments with missing 1st-place badge metadata
- Resets stale badge links and winner badge metadata when the linked badge no longer matches by URL/image

**Workflow:**
1. Run `sync:user-profiles --type=winners` to fetch winner profile, ranks, SIP queue, and raw badge data from osu! API
2. Run `sync:tournament` to link saved user badges to approved badged tournaments

**Production:** ✅ Ready

**Safety:** ✅ Updates existing data (non-destructive)

**Queue:** Optional (via `--queue` flag)

**Options:**
- `--start-year`: Start year for filtering tournaments by `tournament_end` (default: last year)
- `--end-year`: End year for filtering tournaments by `tournament_end` (default: current year)
- `--queue`: Dispatch as a queued job
- `--essential-only`: Only check tournaments where the 1st-place winner is missing badge metadata; includes `badge_status = approved` and `badge_status = pending`
- `--dry-run`: Preview changes without saving

**Examples:**
```bash
# Sync approved badged tournaments using dynamic year range
docker compose exec -u sail laravel.test php artisan sync:tournament

# Sync a specific tournament-end year range
docker compose exec -u sail laravel.test php artisan sync:tournament --start-year=2021 --end-year=2026

# Queue background sync
docker compose exec -u sail laravel.test php artisan sync:tournament --queue

# Essential sync: only check tournaments with missing 1st-place badge metadata
docker compose exec -u sail laravel.test php artisan sync:tournament --essential-only

# Essential sync in the background
docker compose exec -u sail laravel.test php artisan sync:tournament --essential-only --queue

# Preview what would be linked
docker compose exec -u sail laravel.test php artisan sync:tournament --dry-run
```

**What it does:**
1. Collects tournaments where `status = approved`, `is_badge = true`, and `badge_status = approved`
2. Filters by `tournament_end` year
3. With `--essential-only`, narrows to `status = approved|pending_review`, `is_badge = true`, `badge_status = approved|pending`, and a 1st-place winner missing `badge_url` or `badge_description`
4. Checks each podium winner's saved `user_badges`
5. Matches when:
   - Tournament `forum_topic_id` matches the badge URL's forum topic ID
   - Tournament wiki/forum URL equals the badge URL
   - Tournament `badge_urls` image URL equals the user's badge image or 2x image URL
6. Updates matching `tournament_winners` badge metadata
7. Auto-approves pending-review tournaments when a matching podium badge is found
8. Sets matched `user_badges.tournament_id`; sets `is_bws_eligible = true` only when the winner gamemode is `osu`
9. Clears stale `user_badges.tournament_id` and sets `is_bws_eligible = false` when the linked tournament no longer exists, is soft-deleted, or the linked badge no longer matches by URL/image
10. Clears stale `tournament_winners.badge_*` metadata when the stored winner badge no longer matches the tournament by URL/image

---
### `sync:user-profiles`

Sync user profile data from osu! API for tournament participants.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan sync:user-profiles [options]
```

**Description:**
- Syncs user data from osu! API using optimal method for each user type:
  - **Winners:** Full profile sync (profile fields, current main_mode statistics, raw badges, SIP queue) via one mode-specific API call per user
  - **Staff/Hosts:** Basic info sync (username, avatar, country) via batch API calls
- Supports multiple user types: winners, staff, hosts, or all
- Auto-assigns gamemodes for tournament winners using intelligent strategy:
  - Single-mode winners: Auto-assigns tournament mode
  - Multi-mode winners: Selects highest PP mode (fallback: 'osu')
- Syncs `tournaments.host_username` when hosts change usernames
- **NEW:** Preserves user's manual mode selection when `main_mode_source = 'oauth_setup'`
- **NEW:** Dynamic year defaults (auto-calculates last year to current year)
- **NEW:** `--essential-only` limits syncs to users missing required country or podium badge metadata
- Queues SIP fetches for osu!standard winners
- Interactive by default, use `--queue` for background processing

**Recent Improvements (2026-05-18):**
- **Changed:** Winner essential mode no longer excludes users that already have rank history
- **Changed:** Winner essential mode checks all podium placements (`placement <= 3`), not only 1st place
- **Changed:** Winner essential mode targets ended badged tournaments with `badge_status = null`, `approved`, or `pending`
- **Changed:** Weekly schedule now runs `sync:user-profiles --type=winners --essential-only --skip-sip` directly, then `cleanup:orphaned-users --force`

**Recent Improvements (2026-05-10):**
- **Added:** `--essential-only` for direct and queued runs
- **Winners:** Essential mode syncs podium users whose winner record is missing badge metadata
- **Staff/Hosts:** Essential mode syncs only users with missing `country_code`

**Recent Improvements (2026-05-08):**
- **Changed:** Winner sync uses `GET /users/{id}/{main_mode}` when `main_mode` is known; users without `main_mode` use `GET /users/{id}` and adopt the API `playmode`
- **Added:** Queue dispatch output includes an approximate runtime estimate
- **Added:** Staff sync auto-detects `main_mode` from approved all-year tournament staff participation, unless `main_mode_source = oauth_setup`

**Recent Improvements (2026-05-07):**
- **Changed:** Winner sync now saves all raw osu! badge data to `user_badges` before tournament badge linking
- **Changed:** Tournament badge ownership is now resolved by `sync:tournament`
- **Added:** Saves source badge URL and 2x badge image URL for later tournament matching
- **Added:** Full winner sync updates current rank history, SIP queue, badges, and previous usernames from one mode-specific user response

**Recent Improvements (2026-03-27):**
- **Added:** Dynamic year defaults (last year to current year, e.g., 2025-2026)
- **Fixed:** Mode name conversion ("fruits" → "catch") in rank_history sync
- **Added:** `main_mode_source` tracking to preserve user's manual mode choice
- **Fixed:** Stale model display bug (now shows updated main_mode correctly)
- **Fixed:** main_mode detection now uses correct `modes_with_details` accessor
- **Fixed:** Multi-mode strategy uses highest PP (not rank number)
- **Added:** Host username sync - keeps tournament.host_username up-to-date
- **Optimized:** Smart API batching (50 users/call for staff, individual for winners)

**Production:** ✅ Ready

**Safety:** ✅ Updates existing data (non-destructive)

**Queue:** Optional (via `--queue` flag)

**Options:**
- `--type=all|winners|staff|hosts` (default: all)
- `--start-year` : Start year for filtering tournaments (default: last year, e.g., 2025)
- `--end-year` : End year for filtering tournaments (default: current year, e.g., 2026)
- `--queue` : Dispatch as queued job instead of interactive
- `--force` : Run even if recently synced
- `--dry-run` : Preview changes without saving
- `--essential-only` : Only sync essential missing data:
  - Winners with podium placement (`placement <= 3`) on an ended badged tournament whose `badge_status` is `null`, `approved`, or `pending`, and missing `tournament_winners` badge metadata
  - Staff/hosts with missing `country_code`
- `--skip-sip` : Skip SIP fetch queuing
- `--chunk-size=10` : Users per queued job (default: 10, only applies with `--queue`)
- `--batch-size=50` : Users per batch (for staff basic sync in interactive mode)
- `--delay=1` : Delay between individual winner syncs (seconds, for winner full sync in interactive mode)

**Queue Mode with Batch Tracking:**

When using the `--queue` flag, users are processed in batches for better monitoring and reliability:

- **Separate batches**: Winners and staff/hosts are processed in separate batches for clear monitoring
- **Chunked processing**: Each batch is split into smaller jobs (default: 10 users per job)
- **Horizon visibility**: Monitor progress in real-time at `http://localhost/horizon/batches`
- **Timeout handling**: Smaller jobs complete within timeout limits (5 minutes per job)
- **Error resilience**: Individual user failures don't stop the entire batch

**Batch behavior:**
- **Winners batch**: Full profile sync (one mode-specific user API call per winner)
- **Staff/Hosts batch**: Basic info sync (50 users per API call, username/avatar/country)
- Staff users also get `main_mode` auto-detected from approved all-year staff tournament modes, unless protected by OAuth setup
- Each job processes a chunk of users (offset/limit pattern)
- Failed jobs are logged but don't prevent other jobs from completing

**Examples:**
```bash
# Daily sync: Update winner ranks/PP (uses dynamic year range: last year to current year)
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=winners
# Output: "Syncing winners users from 2025 to 2026... (Using dynamic year range: 2025 to 2026)"

# Queue batched sync with default chunk size (100 users per job)
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=winners --start-year=2021 --end-year=2026 --queue
# Output: "Dispatching batched jobs for winners users from 2021 to 2026..."
#         "Chunk size: 10 users per job"
#         "Total users: 1358"
#         "  - Winners: 1358 (full profile sync)"
#         "Dispatching 136 jobs for winners..."
#         "Batches dispatched!"
#         "  1. Batch ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
#         "Approx time: 45m 16s"
#         "Monitor batches at: http://localhost/horizon/batches"

# Custom chunk size (fewer jobs, larger chunks)
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=all --queue --chunk-size=250

# Manual sync: Update staff avatars/names (basic info, fast) - RARE EVENT
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=staff

# Sync everything (winners=full, staff=basic) - ONE-OFF OPERATION
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=all

# Specify custom year range (override dynamic defaults)
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=winners --start-year=2024 --end-year=2025

# Queue background job for winner sync (for production)
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=winners --queue

# Essential winner sync: podium users missing badge metadata on ended non-rejected badged tournaments
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=winners --essential-only

# Essential staff sync: only staff users missing country_code
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=staff --essential-only

# Essential queued sync for all user groups
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=all --essential-only --queue

# Dry run to preview changes
docker compose exec -u sail laravel.test php artisan sync:user-profiles --type=staff --dry-run
```

**Performance characteristics:**
- **Winners sync (full profile):** ~1 second per user (one mode-specific API call)
- **Staff/Hosts sync (basic info):** ~50 users per second (batch API calls)
- **Combined (type=all):** Fast staff sync + detailed winner sync

**What it does:**
1. Fetches users by type and year range (filters by `tournament_end` date)
   - With `--essential-only`, winners must have a podium placement (`placement <= 3`) on an ended badged tournament whose `badge_status` is `null`, `approved`, or `pending`, and missing winner badge metadata
   - With `--essential-only`, staff/hosts must have missing `country_code`
2. Separates winners from staff/hosts for optimal API usage
3. **For staff/hosts:** Batch syncs basic info via `GET /users?ids[]=...` (50 users per call)
   - Staff `main_mode` is set to the most common mode from approved `tournament_staff` participation across all years
   - Uses each tournament's `modes` set for mode counting
   - Skips mode update when `main_mode_source = oauth_setup`
4. **For winners:** Individual syncs via `GET /users/{id}/{main_mode}` when `main_mode` is known, or `GET /users/{id}` when `main_mode` is missing
5. **For winners:** Saves username, country, previous usernames, `osu_data_synced_at`, current `main_mode` rank/PP, and all raw badge data
   - Missing `main_mode` is set from osu! API `playmode` (`fruits` becomes `catch`)
6. **For winner badges:** Saves all osu! badges to `user_badges` first with `tournament_id = null` and `is_bws_eligible = false`; run `sync:tournament` afterward to link tournament badges
7. **Auto-assigns gamemodes** using intelligent strategy:
   - Extracts all modes from user's tournaments via `modes_with_details` accessor
   - Single-mode winners: Auto-assigns tournament mode
   - Multi-mode winners: Selects mode with highest PP (performance points)
   - Fallback: 'osu' if no rank history found
   - **Preserves user choice:** Skips update if `main_mode_source = 'oauth_setup'`
8. **Syncs host usernames** in `tournaments` table when users change names
9. **Converts mode names:** "fruits" (API) → "catch" (application) for rank_history
10. Queues SIP fetches for osu!standard winners (unless `--skip-sip`)

**Typical output:**
```
Syncing winners users from 2025 to 2026...
Found 150 users to sync.
  - Winners: 100 (full profile sync)
  - Staff/Hosts: 50 (basic info sync)

Syncing staff/hosts basic info (batch API calls)...
  ✓ Updated: staff_user1 (basic info)
    ↳ Updated host_username in 2 tournament(s)
  ✓ Updated: staff_user2 (basic info)
Staff/hosts sync complete: 50 updated, 0 failed

Syncing winners full profiles (individual API calls)...
Synced: winner1 (Mode: osu)
Synced: winner2 (Mode: mania)
Sync complete!
  - Total users: 150
  - Failed: 0
```

**Backward compatibility (migration guide):**
- ~~`sync:podium-users`~~ → Use `--type=winners` (same behavior, unified command)
- ~~`winners:sync-user-data`~~ → Use `--type=winners --queue` (queued job version)
- ~~`sync:podium-gamemodes`~~ → Integrated into main sync (auto-runs for winners)
- ~~`tournaments:diagnose-staff`~~ → Use `tournaments:reparse-staff --dry-run` instead

**Migration Notes:**
- Removed `badges:import-tournament-winners`; use `sync:user-profiles --type=winners` followed by `sync:tournament`
- New features added: staff/host sync, multi-mode strategy, host username sync, user choice preservation, raw badge persistence
- **Dynamic year defaults:** Automatically uses last year to current year (2025-2026)
  - No need to manually update defaults each year
  - Override with `--start-year` and `--end-year` if needed
- **User choice preservation:** Users who manually select main_mode via OAuth setup will never be overridden by sync
- **Mode name conversion:** Fixed "fruits" vs "catch" mismatch in rank_history table
- Rate limiting: Keep existing defaults (`--batch-size=50`, `--delay=1`)
- API strategy: Batch for staff (fast), individual for winners (detailed)

---

## Utility Commands

### `test:verify`

Verify test database isolation and configuration.

**Usage:**
```bash
docker compose exec -u sail laravel.test php artisan test:verify [options]
```

**Description:**
- Verifies tests are using correct database (`testing`, not `tourney_method`)
- Checks environment configuration
- Validates `.env.testing` setup
- Critical for preventing accidental production database writes during tests

**Production:** ✅ Ready (development only)

**Safety:** ✅ Read-only

**Queue:** No

**Options:**
- `--fail-on-error`: Exit with error code if misconfigured

**Examples:**
```bash
# Verify test isolation
docker compose exec -u sail laravel.test php artisan test:verify

# Exit with error if misconfigured (useful for CI/CD)
docker compose exec -u sail laravel.test php artisan test:verify --fail-on-error
```

**Output:**
```
✅ All checks passed - Tests are properly isolated!

Environment: testing
Database: tourney_method_test
Connection: pgsql
```

**Error Output:**
```
❌ CRITICAL: Tests may connect to production database!

Environment: local
Database: tourney_method
Connection: pgsql

FIX IMMEDIATELY:
1. Ensure .env.testing has DB_DATABASE=tourney_method_test
2. Ensure tests/bootstrap.php exists
3. Ensure phpunit.xml has bootstrap="tests/bootstrap.php"
```

### `cleanup:orphaned-users`

Clean up orphaned users who were accidentally created and have no tournament participation.

**Usage:**

```bash
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users [options]
```

**Description:**

- Removes users with no tournament participation (winner, staff, host)
- Protects admin/master users, OAuth setup users, and whitelisted users
- Soft deletes users (can be restored)
- Safe ID-based batch processing for large datasets
- Dry-run mode for safe preview

**Production:** ✅ Ready (use with caution)

**Safety:** ⚠️ Destructive (soft delete)

**Queue:** No

**Options:**

- `--dry-run`: Preview what would be deleted without deleting
- `--force`: Skip confirmation prompt
- `--batch-size=100`: Number of users to process at once
- `--min-age=0`: Minimum days since user creation (0 = all)
- `--whitelist=`: Custom whitelist file path (JSON array)

**Examples:**

```bash
# Preview what would be deleted (RECOMMENDED FIRST)
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users --dry-run

# Actually delete orphaned users
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users

# Skip confirmation prompt
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users --force

# Only users older than 30 days
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users --min-age=30

# Custom batch size for large datasets
docker compose exec -u sail laravel.test php artisan cleanup:orphaned-users --batch-size=50
```

**Protection Rules:**

The following users are NEVER deleted:
1. **Admin/Master users** - Users with role 'admin' or 'master'
2. **OAuth setup users** - Users who completed OAuth setup (`main_mode_source = 'oauth_setup'`), even if cleanup config disables the legacy OAuth protection flag
3. **Whitelisted users** - Users listed in `config/user-cleanup.php`

**What Defines an "Orphaned" User?**

An orphaned user has:
- ❌ No tournament winner (podium) placements
- ❌ No tournament staff roles
- ❌ No hosted tournaments
- ❌ Not an admin/master
- ❌ Not logged in via OAuth (`main_mode_source !== 'oauth_setup'`)
- ❌ Not in whitelist

**Configuration:**

Edit `config/user-cleanup.php` to customize role protection and whitelist. OAuth setup users are always protected by the command.

```php
return [
    'whitelist' => [
        'special_user',
        'important_account',
    ],
    'protection_rules' => [
        'roles' => ['admin', 'master'],
        'min_age_days' => 0,
    ],
];
```

**Recovery:**

Soft-deleted users can be restored using:

```bash
docker compose exec -u sail laravel.test php artisan tinker
>>> User::withTrashed()->find($id)->restore()
```

**Scheduling:**

Cleanup is scheduled in `routes/console.php` after weekly essential podium profile sync succeeds. This keeps newly badged podium users synced before orphan cleanup runs. Weekly maintenance then runs `maintenance:database-prune --force` without physical reclaim.

**Safety Checklist:**

Before running cleanup on production:
- [ ] Run with `--dry-run` first
- [ ] Review the list of users to be deleted
- [ ] Verify whitelist is properly configured
- [ ] Create database backup
- [ ] Test on staging environment first
- [ ] Have rollback plan ready (restore from backup or use soft delete restore)

---

### `maintenance:database-prune`

Prune and compact storage-heavy database maintenance data.

**Usage:**

```bash
docker compose exec -u sail laravel.test php artisan maintenance:database-prune [options]
```

**Description:**

- Deletes `tournament_parse_histories` older than 30 days unless they are among the latest 5 histories for that tournament
- Compacts old retained parse histories by removing full `parsed_data`, description raw old/new payloads, and generated `diff_html`
- Preserves compact summaries, hashes, byte counts, timestamps, source, and visible change metadata
- Prunes completed `job_batches` older than 14 days using Laravel batch pruning behavior
- Keeps unfinished job batches
- Does not delete `admin_audit_logs`; reports logs older than the 180-day retention marker for visibility
- Can optionally run PostgreSQL `VACUUM (FULL, ANALYZE)` on `tournament_parse_histories` and `job_batches`

**Production:** Ready, but run dry-run and backup first

**Safety:** Destructive with `--force`; physical reclaim with `--reclaim` requires a maintenance window

**Queue:** No

**Options:**

- `--dry-run`: Report expected deletes/compactions without changing data
- `--force`: Apply pruning and compaction
- `--reclaim`: Run `VACUUM (FULL, ANALYZE)` after pruning; requires `--force`

**Examples:**

```bash
# Preview expected changes without modifying data
docker compose exec -u sail laravel.test php artisan maintenance:database-prune --dry-run

# Prune and compact eligible maintenance data
docker compose exec -u sail laravel.test php artisan maintenance:database-prune --force

# Physical file reclaim during an approved short maintenance window only
docker compose exec -u sail laravel.test php artisan maintenance:database-prune --force --reclaim
```

**What It Does Not Touch:**

- Does not modify `tournaments.description`
- Does not clean `tournament_staff`
- Does not delete `admin_audit_logs`
- Does not manage WAL files
- Does not run physical reclaim unless `--reclaim` is explicitly provided

**Production Runbook:**

Before running with `--force` in production:
- Confirm a fresh database backup exists
- Deploy the migration adding `tournament_parse_histories.compacted_at`
- Run `maintenance:database-prune --dry-run`
- Record expected counts
- Run `maintenance:database-prune --force` during a low-traffic window
- Only run `--force --reclaim` during an approved short maintenance window
- Recheck database size, relation sizes, and Railway volume usage afterward

**Typical Output:**

```text
Database prune summary
Mode: dry-run
Parse histories to delete: 1754
Old kept parse histories to compact: 7044
Completed job batches to prune: 1581
Admin audit logs older than 180 days retained for now: 0
No data changed.
```

**Scheduling:**

The weekly maintenance command runs `maintenance:database-prune --force` after essential profile sync, tournament badge sync, and orphan cleanup. It intentionally does not pass `--reclaim`.

---


## Queue Configuration

### Starting Horizon Workers

**Development:**
```bash
docker compose exec -u sail laravel.test php artisan horizon
```

**Production:**
```bash
# Using supervisor (recommended)
sudo supervisorctl start horizon

# Or manually
php artisan horizon
```

**Monitoring:**
- **Horizon Dashboard:** http://localhost/horizon
- **Horizon Status:** `php artisan horizon:status`

**Stopping Horizon:**
```bash
docker compose exec -u sail laravel.test php artisan horizon:terminate
```

### Standard Queue Workers

If not using Horizon, start standard workers:

```bash
docker compose exec -u sail laravel.test php artisan queue:work redis --queue=default --sleep=3 --tries=3
```

### Queue Configuration

**Current Setup:**
- **Driver:** Redis
- **Connection:** default
- **Queue:** default
- **After Commit:** Enabled

**Horizon Configuration (`config/horizon.php`):**
- **Production:** 10 processes, auto-scaling
- **Local:** 3 processes
- **Memory:** 128MB per process
- **Timeout:** 60 seconds
- **Tries:** 1 (network-dependent jobs)

**Job Retry Configuration:**
- ParseForumTopicJob: 3 tries, exponential backoff [60s, 120s, 240s]
- AggregateStaffJob: 3 tries, exponential backoff [60s, 120s, 240s]
- BatchFetchUsersJob: 3 tries, exponential backoff [60s, 120s, 240s]
- BatchMergeStaffJob: 3 tries, exponential backoff [60s, 120s, 240s]

---

## Scheduled Tasks

### Viewing Schedule

```bash
docker compose exec -u sail laravel.test php artisan schedule:list
```

### Running Scheduler

**Development (manual run):**
```bash
docker compose exec -u sail laravel.test php artisan schedule:work
```

**Production (cron job):**
```bash
# Add to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Current Schedule

| Command | Schedule | Description |
|---------|----------|-------------|
| `tournaments:parse` | `0 9 * * *` | Daily at 9 AM - Parse forum for new tournaments |
| `reminders:registration` | `0 * * * *` | Every hour - Send registration deadline reminders |
| `streams:check` | `*/3 * * *` | Every 3 minutes - Check for live streams |
| `maintenance:weekly-essential` | Mondays 2 AM Asia/Seoul | Sequential essential podium sync, tournament badge sync, orphan cleanup, then `maintenance:database-prune --force` without physical reclaim |

---

## Production Setup

### Environment Variables

```bash
# Queue Configuration
QUEUE_CONNECTION=redis

# Horizon Configuration
HORIZON_DARK_MODE=true
HORIZON_PREFIX=horizon:
```

### Supervisor Configuration (Production)

Create `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
command=php /var/www/html/tourney-method/artisan horizon
autostart=true
autorestart=true
user=sail
redirect_stderr=true
stdout_logfile=/var/www/html/tourney-method/storage/logs/horizon.log
stopwaitsecs=3600
```

**Commands:**
```bash
# Start Horizon
sudo supervisorctl start horizon

# Restart Horizon
sudo supervisorctl restart horizon

# Check status
sudo supervisorctl status horizon
```

---

## Troubleshooting

### Jobs Not Processing

**Symptom:** Jobs remain in pending state

**Solutions:**
1. Check Horizon is running: `docker compose exec -u sail laravel.test php artisan horizon:status`
2. Check Redis connection: `docker compose exec redis redis-cli PING`
3. Check Horizon dashboard: http://localhost/horizon
4. Restart Horizon: `docker compose exec -u sail laravel.test php artisan horizon:terminate`

### Jobs Failing

**Symptom:** Jobs failing with errors

**Solutions:**
1. Check logs: `docker compose exec -u sail laravel.test tail storage/logs/laravel.log`
2. Check Horizon dashboard for failed jobs
3. Retry failed jobs via Horizon dashboard
4. Check external API status (osu!, Twitch, Discord)

### High Memory Usage

**Symptom:** Horizon processes using too much memory

**Solutions:**
1. Reduce number of processes in `config/horizon.php`
2. Adjust memory limit per process
3. Check for memory leaks in jobs
4. Restart Horizon periodically

### Slow Job Processing

**Symptom:** Jobs taking too long to process

**Solutions:**
1. Check network latency to external APIs
2. Optimize database queries
3. Increase number of Horizon processes
4. Check Redis performance

### Database Lock Issues

**Symptom:** Jobs waiting for database locks

**Solutions:**
1. Reduce job concurrency
2. Optimize database queries
3. Use database transactions properly
4. Check for long-running queries

---

## Command Help

For detailed help on any command:

```bash
docker compose exec -u sail laravel.test php artisan help [command]
```

**Example:**
```bash
docker compose exec -u sail laravel.test php artisan help tournaments:parse
```

---

**Need more help?** See [QUEUES.md](QUEUES.md) for queue architecture details or [RUNBOOK.md](RUNBOOK.md) for production operations.
