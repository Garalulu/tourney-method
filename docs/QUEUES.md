# Queue Architecture

**Last Updated:** 2026-03-06

This document describes the queue architecture, job chains, and workflow dependencies for the Tourney Method application.

---

## Table of Contents

1. [Queue Configuration](#queue-configuration)
2. [Horizon Setup](#horizon-setup)
3. [Job Chains and Dependencies](#job-chains-and-dependencies)
4. [Job Specifications](#job-specifications)
5. [Monitoring and Debugging](#monitoring-and-debugging)
6. [Production Deployment](#production-deployment)

---

## Queue Configuration

### Current Setup

**Queue Driver:** Redis
**Connection:** default
**Queues:** `osu-user`, `osu-admin-priority`, `osu-admin`, `default`, `imports`
**After Commit:** Enabled

**Environment Variables:**
```bash
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### Why Redis?

- **Fast:** In-memory storage for quick job processing
- **Reliable:** Persistence for job durability
- **Scalable:** Handle multiple workers
- **Atomic:** Job locking and retry support

---

## Horizon Setup

### Configuration File: `config/horizon.php`

```php
'environments' => [
    'production' => [
        'user-supervisor' => [
            'connection' => 'redis',
            'queue' => ['osu-user'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
        ],
        'admin-supervisor' => [
            'connection' => 'redis',
            'queue' => ['osu-admin-priority', 'osu-admin', 'default', 'imports'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
        ],
    ],
],
```

The user lane is intentionally isolated from admin parsing/import work. All osu! API calls still share the Redis-backed `osu_api_request_slot` lock, but a long admin batch cannot consume the only Horizon worker available for user-initiated sync.

### Starting Horizon

**Development:**
```bash
docker compose exec -u sail laravel.test php artisan horizon
```

**Production (with Supervisor):**
```bash
sudo supervisorctl start horizon
```

### Horizon Dashboard

- **URL:** http://localhost/horizon (local) or https://yourdomain.com/horizon (production)
- **Features:** Job metrics, failed jobs, worker status, job retries
- **Authentication:** Requires `auth.password` middleware (configured in `routes/web.php`)

### Horizon Commands

```bash
# Start Horizon
php artisan horizon

# Stop Horizon gracefully
php artisan horizon:terminate

# Pause Horizon
php artisan horizon:pause

# Resume Horizon
php artisan horizon:continue

# Check status
php artisan horizon:status

# Purge failed jobs
php artisan horizon:purge
```

---

## Job Chains and Dependencies

### 3-Stage Tournament Parsing Pipeline

The application uses a sophisticated 3-stage queue chain for efficient tournament parsing:

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Stage 1: ParseForumTopicJob                 │
│  (Parallel - one job per forum topic)                               │
│                                                                     │
│  ├─ Fetch forum topics from osu! API                               │
│  ├─ Parse BBcode for tournament metadata                           │
│  ├─ Extract staff roles                                             │
│  └─ Store staff payload in parse batch tables                      │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Stage 2: Aggregation                       │
│  (Parallel - two independent jobs)                                  │
│                                                                     │
│  ┌────────────────────┐    ┌──────────────────────────────────────┐ │
│  │ AggregateStaffJob  │    │  BatchFetchUsersJob                  │ │
│  │                    │    │                                      │ │
│  │ Collect unique     │───▶│ Batch fetch users (50 per API call)  │ │
│  │ osu! user IDs      │    │ Unwrap API response from 'users' key │ │
│  │ Deduplicate        │    │ Create/update User records           │ │
│  │ Prepare fetches     │    │ Persist user ID mapping             │ │
│  └────────────────────┘    └──────────────────────────────────────┘ │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Stage 3: BatchMergeStaffJob                    │
│  (Single job - final aggregation)                                   │
│                                                                     │
│  ├─ Attach staff to tournaments with pivot IDs                      │
│  ├─ Update tournament host from organizer                          │
│  └─ Mark persisted transaction complete                            │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Features

**Transaction IDs:**
- Each parsing run gets unique transaction ID for tracking
- Used as the parse batch key in Postgres-backed handoff tables
- Prevents cross-job data contamination

**60% Reduction in API Calls:**
- Deduplicate user fetches across tournaments
- Batch 50 users per API call (osu! API limit)
- Significantly faster processing

**Better Error Handling:**
- Isolated stages with retry logic
- Exponential backoff: [60s, 120s, 240s]
- Failed jobs don't block entire pipeline

**Scalability:**
- Handle 50+ tournaments in single batch
- Parallel processing in Stage 1
- Batch user fetching in Stage 2

**Pivot ID Support:**
- Staff records include pivot.id for proper delete/update operations
- Versioning support for staff changes

### Notification Chain

```
streams:check / reminders:registration
  └─ SendNotificationJob (queued)
      └─ Discord webhook delivery
```

**Workflow:**
1. Scheduled command triggers (`streams:check` or `reminders:registration`)
2. Queues `SendNotificationJob` for each notification
3. Job sends Discord webhook
4. Retries on failure with exponential backoff

---

## Job Specifications

### ParseForumTopicJob

**Purpose:** Parse individual forum topic for tournament data

**Queue:** `osu-admin`
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 60 seconds

**Process:**
1. Fetch forum topic from osu! API
2. Parse BBcode structure
3. Extract tournament metadata
4. Extract staff roles and user IDs
5. Store parsed staff payloads in the parse batch tables
6. Dispatch next stage jobs

**Failure Handling:**
- Retry with backoff (API rate limits, network errors)
- If all retries fail, mark job as failed
- Manual intervention may be required

**Dependencies:** None (first stage)

---

### AggregateStaffJob

**Purpose:** Aggregate unique user IDs across tournaments

**Queue:** `osu-admin`
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 60 seconds

**Process:**
1. Read tournament staff payloads from the parse batch tables
2. Extract all osu! user IDs
3. Deduplicate user IDs
4. Batch into groups of 50 (osu! API limit)
5. Pass to `BatchFetchUsersJob`

**Failure Handling:**
- Retry with backoff (database errors)
- If all retries fail, transaction may be incomplete
- Manual review of the parse batch may be required

**Dependencies:** ParseForumTopicJob (must complete first)

---

### BatchFetchUsersJob

**Purpose:** Batch fetch user data from osu! API

**Queue:** `osu-admin`
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 60 seconds

**Process:**
1. Receive batch of user IDs (max 50)
2. Call osu! API `/users` endpoint
3. **CRITICAL:** Unwrap response from `users` key
4. Create/update User records
5. Persist user ID mapping on the parse batch
6. Signal completion

**API Response Structure:**
```json
{
  "users": [
    {"id": 123, "username": "user1", ...},
    {"id": 456, "username": "user2", ...}
  ]
}
```

**Failure Handling:**
- Retry with backoff (API rate limits, network errors)
- If all retries fail, some users may not be fetched
- Re-running parsing job will retry failed fetches

**Dependencies:** AggregateStaffJob (must complete first)

---

### BatchMergeStaffJob

**Purpose:** Attach staff to tournaments with pivot IDs

**Queue:** `osu-admin`
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 60 seconds

**Process:**
1. Read tournament staff and user mapping data from the parse batch tables
2. For each tournament:
   - Attach staff using `sync()` with pivot IDs
   - Update tournament host from organizer
3. Mark the persisted transaction complete
4. Signal completion

**Pivot ID Support:**
```php
$tournament->staff()->syncWithPivotValues([
    $userId => ['id' => $pivotId, 'role' => $role],
]);
```

**Failure Handling:**
- Retry with backoff (database errors)
- If all retries fail, data may be inconsistent
- Manual review required

**Dependencies:** AggregateStaffJob + BatchFetchUsersJob (both must complete)

---

### SendNotificationJob

**Purpose:** Send notifications via Discord webhook

**Queue:** default
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 30 seconds

**Process:**
1. Format notification message
2. Send to Discord webhook URL
3. Handle rate limiting (Discord limits)
4. Log success/failure

**Failure Handling:**
- Retry with backoff (Discord rate limits)
- If all retries fail, notification is lost
- Monitoring required for critical notifications

**Dependencies:** None (independent)

---

### UpdateUserRankJob

**Purpose:** Update user rank when they log in

**Queue:** default
**Tries:** 3
**Backoff:** [60, 120, 240] (exponential)
**Timeout:** 30 seconds

**Process:**
1. Fetch user rank from osu! API
2. Update user record in database
3. Cache rank for 24 hours

**Failure Handling:**
- Retry with backoff (API errors)
- If all retries fail, cached rank is used
- Non-critical (updated on next login)

**Dependencies:** None (independent)

---

## Monitoring and Debugging

### Horizon Metrics

**Key Metrics to Monitor:**
- **Jobs per minute:** Throughput indicator
- **Failed jobs:** Error rate
- **Queue size:** Backlog indicator
- **Worker utilization:** Resource usage

**Accessing Metrics:**
1. Horizon Dashboard (http://localhost/horizon)
2. `php artisan horizon:stats` command
3. Redis monitoring (`redis-cli INFO`)

### Common Issues

#### Jobs Stuck in Pending

**Symptoms:**
- Jobs not processing
- Queue size growing

**Causes:**
- Horizon not running
- Redis connection issues
- Worker crashes

**Solutions:**
```bash
# Check Horizon status
docker compose exec -u sail laravel.test php artisan horizon:status

# Restart Horizon
docker compose exec -u sail laravel.test php artisan horizon:terminate

# Check Redis
docker compose exec redis redis-cli PING
```

#### Jobs Failing Repeatedly

**Symptoms:**
- High retry count
- Failed jobs accumulating

**Causes:**
- External API failures (osu!, Discord, Twitch)
- Database connection issues
- Code bugs

**Solutions:**
```bash
# Check logs
docker compose exec -u sail laravel.test tail storage/logs/laravel.log

# Review failed jobs in Horizon dashboard
# Retry or delete failed jobs as needed

# Fix root cause and retry jobs
```

#### Memory Leaks

**Symptoms:**
- Worker processes using increasing memory
- Workers crashing

**Causes:**
- Large data structures in memory
- Unclosed connections
- Memory leaks in PHP code

**Solutions:**
- Reduce `maxProcesses` in Horizon config
- Lower `memory` limit per process
- Restart Horizon periodically
- Profile memory usage with `memory_get_usage()`

#### Slow Job Processing

**Symptoms:**
- Jobs taking longer than expected
- Queue backlog growing

**Causes:**
- External API latency
- Database query performance
- Insufficient workers

**Solutions:**
- Increase `maxProcesses` in Horizon config
- Optimize slow queries
- Add database indexes
- Cache API responses

### Debugging Tools

#### Horizon Tags

Tag jobs for filtering:
```php
dispatch((new ParseForumTopicJob($topicId))->onQueue('default')->addTags(['tournament', 'parse']));
```

#### Job Batches

Track related jobs:
```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ParseForumTopicJob(1),
    new ParseForumTopicJob(2),
])->then(function (Batch $batch) {
    // All jobs completed successfully
})->catch(function (Batch $batch, Throwable $e) {
    // First batch failure detected
})->finally(function (Batch $batch) {
    // Batch finished (success or failure)
})->dispatch();
```

#### Job Middleware

Add custom middleware:
```php
class RateLimitedJob
{
    public function middleware()
    {
        return [new RateLimited('osu-api')];
    }

    public function handle()
    {
        // Job logic
    }
}
```

---

## Production Deployment

### Supervisor Configuration

Create `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
command=php /var/www/html/tourney-method/artisan horizon
autostart=true
autorestart=true
user=www-data
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

# Stop Horizon
sudo supervisorctl stop horizon

# Check status
sudo supervisorctl status horizon
```

### Cron Job for Scheduler

Add to crontab (`crontab -e`):

```bash
* * * * * cd /var/www/html/tourney-method && php artisan schedule:run >> /dev/null 2>&1
```

### Monitoring

**Health Checks:**
```bash
# Horizon status
php artisan horizon:status

# Redis connection
redis-cli PING

# Queue size
redis-cli LLEN queues:default
```

**Alerts:**
- Horizon dashboard unavailable
- Failed jobs > threshold (e.g., 100 per hour)
- Queue size > threshold (e.g., 1000)
- Worker processes down

### Scaling

**Vertical Scaling:**
- Increase `maxProcesses` in Horizon config
- Increase `memory` limit per process
- Add more CPU cores

**Horizontal Scaling:**
- Deploy multiple Horizon workers on different servers
- Use Redis as shared queue backend
- Load balance with supervisor

### Backup and Recovery

**Backup Redis:**
```bash
# Save Redis snapshot
redis-cli BGSAVE

# Copy RDB file
cp /var/lib/redis/dump.rdb /backup/redis-$(date +%Y%m%d).rdb
```

**Restore Redis:**
```bash
# Stop Redis
sudo systemctl stop redis

# Restore RDB file
cp /backup/redis-20260306.rdb /var/lib/redis/dump.rdb

# Start Redis
sudo systemctl start redis
```

**Emergency Queue Clear:**
```bash
# Clear all jobs (DANGEROUS - use only in emergency)
redis-cli DEL queues:default

# Clear specific job
redis-cli LREM queues:default 0 <job-payload>
```

---

**Need more help?** See [COMMANDS.md](COMMANDS.md) for command reference or [RUNBOOK.md](RUNBOOK.md) for production operations.
