# Dependencies Codemap

<!-- Generated: 2026-03-31 | Token estimate: ~880 -->

**Last Updated:** 2026-03-31

## PHP Dependencies (composer.json)

### Core Framework
- **laravel/framework** ^11.31 - Laravel 11.x framework
- **laravel/horizon** ^5.42 - Queue dashboard and monitoring
- **laravel/socialite** ^5.24 - OAuth authentication (osu!)
- **livewire/livewire** ^3.7 - Full-stack framework for dynamic UI
- **laravel/tinker** ^2.9 - REPL for Laravel application

### API Integration
- **intervention/image** ^3.11 - Image manipulation and processing
- **league/flysystem-aws-s3-v3** ^3.32 - Cloudflare R2 S3-compatible storage

### Diff Utilities
- **jfcherng/php-diff** ^6.0 - GitHub-style diff viewer for parse history

### Development Dependencies
- **laravel/pail** ^1.1 - Log viewer (Laravel Tail)
- **laravel/pint** ^1.13 - Code style fixer (PSR-12)
- **laravel/sail** ^1.26 - Docker development environment
- **pestphp/pest** ^4.0 - Testing framework
- **pestphp/pest-plugin-laravel** ^4.0 - Laravel Pest integration
- **phpstan/phpstan** ^2.1 - Static analysis tool (level 6)
- **phpro/grumphp** * - Git hooks (pre-commit quality checks)
- **php-parallel-lint/php-parallel-lint** ^1.4 - Parallel PHP linting
- **mockery/mockery** ^1.6 - Mocking framework for tests
- **nunomaduro/collision** ^8.1 - Error reporting for CLI
- **phpunit/phpunit** ^12.0 - Unit testing framework
- **fakerphp/faker** ^1.23 - Test data generation

## Frontend Dependencies (package.json)

### Core Dependencies
- **alpinejs** ^3.15.3 - Lightweight JavaScript framework
- **@alpinejs/collapse** ^3.15.8 - Collapse/transition animations for Alpine

### Build Tools
- **vite** ^6.0.11 - Frontend build tool and dev server
- **laravel-vite-plugin** ^1.2.0 - Laravel integration for Vite
- **tailwindcss** ^3.4.19 - Utility-first CSS framework
- **postcss** ^8.5.6 - CSS transformation pipeline
- **autoprefixer** ^10.4.23 - Vendor prefix CSS

### Development Utilities
- **axios** ^1.7.4 - HTTP client for AJAX requests
- **concurrently** ^9.0.1 - Run multiple npm scripts simultaneously

## Infrastructure Dependencies

### External APIs
- **osu! API v2** - User data, forum topics, OAuth2 authentication, avatars
- **tcomm.hivie.tn API** - Tournament data aggregation
- **o!TR API** - Historical match data (updated endpoint configuration)
- **Twitch API** - Stream status detection
- **Discord API** - Webhook notifications with environment variable URL support

### Infrastructure Services
- **PostgreSQL 18** - Primary database
- **Redis** - Cache, queue backend, rate limiting
- **Cloudflare R2** - Object storage (banners, backups)
- **Railway** - Production deployment platform
- **Docker** - Containerization (via Laravel Sail)

## Service Architecture

### API Integration Services
- `OsuApiService` - osu! API client with OAuth2 token management
- `TcommApiService` - tcomm API client with rate limiting
- `OtrApiService` - o!TR API client for match data
- `TwitchService` - Twitch API client for stream detection
- `DiscordService` - Discord webhook sender

### Internal Services
- `BatchTransactionService` - Multi-stage job coordination
- `BannerCacheService` - Discord banner caching to R2
- `ForumParser` - BBcode parsing and staff extraction
- `ImportService` - Tournament/match import orchestration
- `BwsCalculator` - Bayesian Weighted Score calculation
- `YearRecapService` - Yearly recap generation
- `AuditLogger` - Admin action audit trail
- `MatchStatsService` - Match statistics and accuracy

## Job Queue System

### Queue Backend
- **Redis** - Job storage and locking
- **Laravel Horizon** - Queue monitoring and management
- **Workers:** 3 concurrent workers (configurable)

### Job Types
- **Tournament Parsing** (4-stage pipeline)
- **Import Jobs** (tournaments, matches, badges)
  - ImportSingleTournamentBadgesJob - Single tournament badge import
  - SyncTournamentBadgesToUsersJob - Sync badges to user profiles
  - SyncTournamentWinnersUserDataJob - Batch winner data sync
- **Notification Jobs** (Discord, email)
  - Enhanced Discord notifications with avatars and formatting
  - Skip webhook option for tournament approvals
- **User Sync Jobs** (profile, winner data)
- **System Jobs** (banner caching)

## Caching Layer

### Cache Backend
- **Redis** - In-memory data store

### Cache Strategies
- User data: 24-hour TTL
- Staff data: 5-minute TTL
- Banner URLs: 7-day TTL
- Rankings: 1-hour TTL

### Cache Tags
- `osu_user:{osu_id}` - Individual user profiles
- Parse staff payloads and batch user mappings are persisted in Postgres parse batch tables
- `tournament_rankings` - Computed rankings
- `tournament_banner:{tournament_id}` - Cached banner URLs

## Storage Layer

### Primary Storage
- **PostgreSQL 18** - Relational database

### Object Storage
- **Cloudflare R2** - S3-compatible storage
  - **Bucket:** tourney-method-banners
  - **Prefixes:**
    - `banners/` - Tournament banner images
    - `backups/` - Database backups (gzip compressed SQL)
  - **Access:** Public via R2 dev domain
  - **CDN:** Cloudflare global edge network

### Backup Strategy
- **Automated:** Daily backups at 08:55 UTC
- **Retention:** 7 days (auto-cleanup)
- **Format:** PostgreSQL pg_dump with gzip compression
- **Size:** ~600 KB (compressed)

## Development Tools

### Code Quality
- **Laravel Pint** - PSR-12 code style enforcement
- **PHPStan** - Level 6 static analysis
- **GrumPHP** - Pre-commit git hooks (phplint, phpstan, pint)

### Testing
- **Pest PHP** - Testing framework with parallel execution
- **PHPUnit** - Legacy unit testing
- **Xdebug** - Code coverage (disabled in CI)
- ** factories** - Test data generation
- **RefreshDatabase** - Database transaction rollback

### Monitoring
- **Laravel Horizon** - Queue metrics and job monitoring
- **Laravel Pail** - Real-time log viewer
- **Browser console** - Frontend error inspection
- **Audit Logs** - Admin action tracking

## Deployment

### Development
- **Laravel Sail** - Docker-based local environment
- **Mailpit** - Email testing (SMTP on port 1025)
- **Xdebug** - Step debugging
- **Vite HMR** - Hot module replacement

### Production
- **Railway** - Cloud deployment platform
- **Docker** - Container orchestration
- **Cloudflare R2** - CDN and object storage
- **PostgreSQL** - Managed database service
- **Redis** - Managed cache service

### Environment Variables Required
```
APP_ENV=production|local
APP_DEBUG=false|true
APP_KEY=base64:...
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=tourney_method
REDIS_HOST=...
REDIS_PASSWORD=...
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
HORIZON_MEMORY_LIMIT=512
OSU_CLIENT_ID=...
OSU_CLIENT_SECRET=...
OSU_REDIRECT_URL=...
CF_R2_ACCESS_KEY_ID=...
CF_R2_SECRET_ACCESS_KEY=...
CF_R2_BUCKET=tourney-method-banners
CF_R2_REGION=auto
MASTER_OSU_ID=...
```

## Version Compatibility

### PHP
- **Minimum:** 8.2
- **Recommended:** 8.5.x
- **Tested on:** 8.5.3

### Database
- **PostgreSQL:** 15+ (tested on 18)
- **Redis:** 7+

### Browser Support
- **Modern browsers:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile:** iOS Safari 14+, Chrome Android 90+
