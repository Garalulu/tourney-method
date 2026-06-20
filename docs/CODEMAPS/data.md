# Data Layer Codemap

<!-- Generated: 2026-03-31 | Tables: 29 | Token estimate: ~1,400 -->

**Last Updated:** 2026-03-31
**Database:** PostgreSQL 18
**ORM:** Eloquent (Laravel 11.x)
**PHP Version:** 8.5.3

## Core Tables

### users
**Purpose:** Application users (osu! authentication)
```php
id, osu_id (unique), username,
country_code (2-char ISO, e.g., 'US', 'KR', 'JP'),
main_mode, main_mode_source,
role (user/admin/moderator), discord_webhook_url, discord_webhook_valid,
notify_registration, notify_stream, osu_data_synced_at,
rank_mania_4k, rank_mania_7k,
**NEW FIELDS:** sip, sip_updated_at, previous_usernames (json),
created_at, updated_at, deleted_at (soft delete)
```
**New Fields:**
- `country_code` - User's country code (2-character ISO 3166-1 alpha-2)
- `rank_mania_4k` - Mania 4K rank (variant-specific eligibility)
- `rank_mania_7k` - Mania 7K rank (variant-specific eligibility)
- `main_mode_source` - Source of main_mode setting ('manual', 'detected', 'podium_sync')
- `sip` - skillissue.app performance score (osu!standard only)
- `sip_updated_at` - Timestamp when SIP was last updated
- `previous_usernames` - JSON array of previous usernames for search
**Relationships:**
- HasMany: TournamentStaff, UserBadge, UserMatchParticipation, UserRankHistory
- HasMany: OsuMatch (through participations)
- HasMany: SipFetchQueue (queue items for this user)
**Indexes:**
- users_osu_id_unique (unique)
- users_rank_mania_4k_index
- users_rank_mania_7k_index
- users_sip_index (for SIP lookups)
- users_username_index
- **NEW:** users_search_performance_index (composite: username, previous_usernames, id)

### tournaments
**Purpose:** Tournament records
```php
id, forum_topic_id (unique), forum_post_url, title, description,
host_osu_id, host_username, status (pending/approved/rejected),
modes (json), team_size_min, team_size_max,
registration_start, registration_end, tournament_start, tournament_end,
rank_range_min, rank_range_max,
is_badge, badge_status (approved/pending/rejected), badge_urls (json),
is_bws, bws_base_exponent, bws_badge_power, bws_divisor, bws_badge_age_cutoff,
star_rating_first, star_rating_last, star_rating_qualifier,
format, start_round_size, restricted_countries (json),
banner_url, discord_url, twitch_url, spreadsheet_url,
bracket_url, registration_url, tcomm_url, tcomm_id (unique), otr_id (unique),
import_source, rejection_reason, vs_size,
parse_count, last_parsed_at, last_parsed_by,
parsed_at, imported_at, reviewed_at, reviewed_by,
viewed_at, field_sources (jsonb), banner_image_cached_at,
created_at, updated_at, deleted_at
```

**Badge System (placement-based):**
- `badge_urls` format: `{"1": ["url1", "url2"], "2": ["url3"], "3": ["url4"]}`
  - Key "1" = 1st place badges, "2" = 2nd place, "3" = 3rd place
  - Supports multiple badges per placement
  - Single badge tournaments: Only key "1" populated
  - Tri-badge tournaments: All three keys populated
- `badge_status` enum: Controls visibility on public page
  - `approved`: Badges shown on public tournament page
  - `pending`: Badges hidden from public
  - `rejected`: Badges hidden from public

**Banner Caching:**
- `banner_image_cached_at` - Timestamp of last banner cache to R2
- `banner_url` - Original Discord URL (fallback)
- Cached banners stored at: `https://tourney-method-banners.r2.dev/banners/{id}.png`

**Relationships:**
- BelongsToMany: User (via tournament_staff)
- HasMany: TournamentParseHistory, TournamentWatch, TournamentWinner, OsuMatch, SipFetchQueue
- BelongsTo: User (host_osu_id, reviewed_by, last_parsed_by)

**Indexes:**
- idx_tournaments_forum_topic (unique)
- idx_tournaments_tcomm_id (unique)
- idx_tournaments_otr_id (unique)
- idx_tournaments_status
- idx_tournaments_dates (registration_end, tournament_start)
- idx_tournaments_rank_range (rank_range_min, rank_range_max)
- idx_tournaments_import_source
- tournaments_start_round_size_index
- **NEW:** tournaments_search_performance_index (composite: title, status, id)

**Constraints:**
- Check: badge_status in ['approved', 'pending', 'rejected']

## Tournament-Related Tables

### tournament_staff
**Purpose:** Tournament staff pivot with ID
```php
id, tournament_id (FK), user_id (FK),
role (organizer/mapper/mappooler/referee/streamer/commentator/playtester/gfx/sheeter/other),
notes, status (pending/approved/rejected),
submitted_at, reviewed_at, reviewed_by (FK),
source (manual/parsed), created_at, updated_at
```
**Relationships:**
- BelongsTo: Tournament, User
**Constraints:**
- Composite unique: (tournament_id, user_id, role)
- Check: role in 10 allowed values
- Check: status in ['pending', 'approved', 'rejected']
- Check: source in ['manual', 'parsed']
**Indexes:**
- unique_tournament_user_role (unique)
- tournament_staff_status_index

### tournament_parse_histories
**Purpose:** Parse attempt tracking with version history
```php
id, tournament_id (FK), parsed_by (FK),
changes (json), parsed_data (json),
parse_source (forum/manual/reparse), parse_notes (text),
conflicts (jsonb), resolution (jsonb),
parsed_at, created_at, updated_at
```
**Relationships:**
- BelongsTo: Tournament, User
**Features:**
- JSON diff viewer (GitHub-style) for staff changes
- Parse source tracking
- Full data snapshot for audit trail
**Indexes:**
- tournament_parse_histories_tournament_id_index
- tournament_parse_histories_created_at_index
- tournament_parse_histories_parsed_by_index

### tournament_winners
**Purpose:** Tournament placement and badge tracking
```php
id, tournament_id (FK), user_id (FK), osu_id, username,
placement, gamemode,
badge_image_url, badge_image_2x_url, badge_description, badge_url, badge_awarded_at,
metadata (json), created_at, updated_at
```
**Relationships:**
- BelongsTo: Tournament, User
**Badge System:**
- Stores tournament winner placement (1st, 2nd, 3rd, etc.)
- Links to osu! API badge images and descriptions
- Gamemode filtering (osu!, taiko, catch, mania)
- **NEW:** Supports tri-badge tournaments (1st, 2nd, 3rd place)
- Used for BWS calculation and user profile badge display
**Metadata Fields:**
- `cover_url` - User's profile cover image URL
- `imported_from_tcomm` - Flag indicating data source
**Constraints:**
- Foreign key: tournament_id → tournaments.id (CASCADE delete)
- Foreign key: user_id → users.id (SET NULL delete)
- Check: gamemode not null (default: 'osu')
**Indexes:**
- tournament_winners_tournament_id_placement_index (composite)
- idx_tournament_winners_gamemode (composite: tournament_id, gamemode)
**Usage:**
- Display badges on user profiles filtered by gamemode
- Calculate Bayesian Weighted Score (BWS) for tournament ranking
- Track historical tournament winners and their placements

### tournament_watches
**Purpose:** User tournament watchlist with notification preferences
```php
id, user_id (FK), tournament_id (FK),
watch_type (interested/watching/stream),
notify_registration, notify_stream,
created_at, updated_at
```
**Relationships:**
- BelongsTo: User, Tournament
**Features:**
- Notification flags for registration reminders (`notify_registration`)
- Notification flags for live streams (`notify_stream`)
- Watch types: interested, watching, stream
**Constraints:**
- Composite unique: (user_id, tournament_id)
- Check: watch_type in ['interested', 'watching', 'stream']
- Boolean defaults: notify_registration=false, notify_stream=false
**Indexes:**
- tournament_watches_user_id_tournament_id_unique (unique)
- tournament_watches_user_id_created_at_index
- tournament_watches_tournament_id_watch_type_index

## Match Tables

### matches (osu_matches)
**Purpose:** Match records from osu! multiplayer
```php
id, osu_match_id (unique), name, tournament_id (FK),
start_time, end_time, status (pending/approved/rejected),
submitted_by (FK), reviewed_by (FK), reviewed_at,
raw_data (jsonb), created_at, updated_at
```
**Relationships:**
- BelongsTo: Tournament, User (submitted_by, reviewed_by)
- HasMany: MatchGame, UserMatchParticipation
**Indexes:**
- matches_osu_match_id_unique (unique)
- matches_tournament_id_index
- matches_status_index
- matches_submitted_by_index

### match_games
**Purpose:** Individual games within match
```php
id, match_id (FK), game_id, beatmap_id,
beatmap_title, beatmap_version,
mods (json), mode, scoring_type, team_type,
start_time, end_time, created_at, updated_at
```
**Relationships:**
- BelongsTo: Match (osu_matches)
- HasMany: MatchScore
**Indexes:**
- match_games_match_id_index
- match_games_beatmap_id_index

### match_scores
**Purpose:** Player scores in games
```php
id, match_game_id (FK), user_id (FK), osu_user_id, username, team,
score, accuracy, max_combo,
count_300, count_100, count_50, count_miss, count_geki, count_katu,
perfect, passed, mods (json),
created_at, updated_at
```
**Relationships:**
- BelongsTo: MatchGame, User
**Indexes:**
- match_scores_match_game_id_index
- match_scores_user_id_index
- match_scores_osu_user_id_index

### user_match_participation
Retired. Participation MP links and score summaries now live in `participation_record_matches`.

## User Data Tables

### user_badges
**Purpose:** User's osu! badges
```php
id, user_id (FK), name, image_url, image_2x_url, awarded_at, is_bws_eligible,
badge_url, tournament_id, created_at
```
**Relationships:**
- BelongsTo: User, Tournament
**Features:**
- Gamemode filtering is resolved through linked `tournament_winners.gamemode`
- High-resolution badge images (1x and 2x)
- Used for BWS calculation and profile display
**Indexes:**
- user_badges_user_id_index
- idx_user_badges_bws_eligible (user_id, is_bws_eligible)
- idx_user_badges_tournament_bws (tournament_id, is_bws_eligible)

### user_rank_history
**Purpose:** Historical rank tracking
```php
id, user_id (FK), mode, rank, country_rank, pp, recorded_at
```
**Relationships:**
- BelongsTo: User
**Indexes:**
- idx_user_rank_history_lookup (user_id, mode, recorded_at)

### year_recap_cache
**Purpose:** Pre-generated yearly recaps
```php
id, user_id (FK), year, image_path, stats_json (json),
generated_at, created_at, updated_at
```
**Relationships:**
- BelongsTo: User
**Constraints:**
- Composite unique: (user_id, year)

### **NEW TABLES**

#### sip_fetch_queue
**Purpose:** Queue for SIP fetch requests processed by Google Apps Script
```php
id, user_id (FK), osu_id (FK), username,
status (pending/processing/completed/failed),
sip (nullable), error_message (nullable),
created_at, updated_at
```
**Relationships:**
- BelongsTo: User
**Features:**
- Status tracking for async processing
- Error message storage for debugging
- Prevents duplicate queuing (7-day cooldown)
- Used by SipService and SipController
**Constraints:**
- Check: status in ['pending', 'processing', 'completed', 'failed']
**Indexes:**
- sip_fetch_queue_status_index
- sip_fetch_queue_osu_id_index
- **NEW:** sip_fetch_queue_cooldown_index (composite: osu_id, created_at)

## System Tables

### admin_audit_logs
**Purpose:** Admin action audit trail
```php
id, admin_id (FK), action, entity_type, entity_id,
details (jsonb), ip_address, created_at
```
**Relationships:**
- BelongsTo: User (admin_id)
**Indexes:**
- admin_audit_logs_admin_id_index
- admin_audit_logs_entity_type_entity_id_index
- admin_audit_logs_created_at_index

### import_jobs
**Purpose:** Import job tracking
```php
id, source (tcomm/otr/combined), status (pending/running/completed/failed/cancelled),
started_at, completed_at,
tournaments_imported, tournaments_updated, tournaments_failed,
matches_imported, matches_failed,
last_cursor, error_log (jsonb), created_at, updated_at
```
**Constraints:**
- Check: source in ['tcomm', 'otr', 'combined']
- Check: status in ['pending', 'running', 'completed', 'failed', 'cancelled']
**Indexes:**
- idx_import_jobs_source_status
- idx_import_jobs_created

### notification_queue
**Purpose:** Pending notifications
```php
id, user_id (FK), tournament_id (FK), type, channel,
payload (jsonb), status, attempts, last_attempt_at, error_message,
scheduled_for, sent_at, created_at, updated_at
```
**Relationships:**
- BelongsTo: User, Tournament
**Indexes:**
- idx_notification_queue_status (status, scheduled_for)
- idx_notification_queue_user (user_id)
- idx_notification_queue_dedup (user_id, tournament_id, type)

### discord_channels
**Purpose:** Discord webhook configurations with environment variable support
```php
id, channel_name (unique), webhook_url, mode, is_badge,
role_mappings (jsonb), is_active, created_at, updated_at
```
**Features:**
- Webhook URL can be stored in environment variables
- Accessor method: `getWebhookUrlAttribute()` - checks env var first, falls back to database
- Mode filtering: osu, taiko, catch, mania
- Badge support for tournament winner announcements
**Constraints:**
- Check: mode in ['osu', 'taiko', 'catch', 'mania']
**Indexes:**
- discord_channel_lookup (mode, is_badge) unique

### bws_exclusion_patterns
Retired. Non-tournament badges remain unlinked and are not BWS eligible.

### jobs (Laravel Queue)
**Purpose:** Queue job storage
```php
id, queue, payload (text), attempts, reserved_at, available_at, created_at
```

### failed_jobs
**Purpose:** Failed queue jobs
```php
id, uuid (unique), connection, queue, payload (text), exception, failed_at
```

### job_batches
**Purpose:** Job batch tracking
```php
id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids (text),
options, cancelled_at, created_at, finished_at
```

## Key Relationships

### One-to-Many
- Tournament → TournamentParseHistory
- Tournament → TournamentWatch
- Tournament → OsuMatch
- Tournament → SipFetchQueue
- OsuMatch → MatchGame
- MatchGame → MatchScore
- User → TournamentStaff
- User → UserBadge
- User → UserMatchParticipation
- User → SipFetchQueue

### Many-to-Many
- Tournament ↔ User (via tournament_staff)
- Tournament ↔ User (via tournament_watches)

### Has Many Through
- Tournament → MatchScore (through OsuMatch → MatchGame)

## Performance Indexes

### Foreign Keys
- All foreign key columns indexed
- Cascade deletes for data integrity
- Set null for audit trails

### Composite Indexes
- (tournament_id, user_id, role) - Staff lookups
- (user_id, match_id) - Participation lookups
- (status, created_at) - Filter + sort queries
- (registration_end, tournament_start) - Date range queries
- **NEW:** (username, previous_usernames, id) - User search performance
- **NEW:** (title, status, id) - Tournament search performance
- **NEW:** (osu_id, created_at) - SIP fetch queue cooldown

### Unique Constraints
- osu_id (users)
- osu_match_id (matches)
- forum_topic_id, tcomm_id, otr_id (tournaments)
- (user_id, tournament_id) (tournament_watches)
- (tournament_id, user_id, role) (tournament_staff)

## Migration Best Practices

- Use `->nullable()` for optional fields
- Use `->default()` for sensible defaults
- Always add foreign key constraints
- Use `->onDelete('cascade')` for cleanup
- Use `->onDelete('set null')` for audit trails
- Add composite indexes for frequent query patterns
- Use JSONB for flexible schema (changes, details, etc.)
- **NEW:** Add GIN indexes for JSONB columns (previous_usernames, changes, details)

## New SIP Integration Tables

### sip_fetch_queue
- **Purpose:** Queue for Google Apps Script processing
- **Status Flow:** pending → processing → completed/failed
- **Security:** No sensitive data stored (osu_id only)
- **Features:**
  - 7-day cooldown prevents duplicate requests
  - Error tracking for debugging
  - Batch processing (50 items at a time)
  - Supports manual retry of failed items

### users table enhancements
- **sip:** Integer field for skillissue.app performance score
- **sip_updated_at:** Timestamp for last SIP update
- **main_mode_source:** Tracks source of main_mode setting
- **previous_usernames:** JSON array of past usernames for search
- **Usage:**
  - Displayed on user profiles for osu!standard players
  - Updated asynchronously via Google Apps Script integration
  - Cached for performance
  - Enables username search across name changes

## Search Performance Indexes (NEW)

### User Search
- `users_search_performance_index` on (username, previous_usernames, id)
  - Optimizes LIKE queries on username and previous_usernames
  - Supports global search with fuzzy matching
  - Covers previous username search feature

### Tournament Search
- `tournaments_search_performance_index` on (title, status, id)
  - Optimizes LIKE queries on title
  - Supports filtering by status
  - Improves global search performance

### SIP Queue Cooldown
- `sip_fetch_queue_cooldown_index` on (osu_id, created_at)
  - Prevents duplicate SIP fetch requests
  - Enforces 7-day cooldown period
  - Optimizes cooldown check queries
