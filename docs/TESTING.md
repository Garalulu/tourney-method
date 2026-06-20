# Testing Requirements

## ⚠️ CRITICAL: Database Isolation (MANDATORY)

**TESTS MUST NEVER CONNECT TO PRODUCTION DATABASE**

### Mandatory Verification Before Running Tests
```bash
# ALWAYS verify tests are isolated before running
docker compose exec -u sail laravel.test php artisan test:verify
```

Expected output:
```
✅ All checks passed - Tests are properly isolated!
  - Tests run in: testing environment
  - Tests use: testing database (NOT tourney_method)
  - RefreshDatabase trait is safe to use
```

If you see CRITICAL/ERROR errors, **DO NOT RUN TESTS** until fixed.

### 6 Layers of Defense

1. **Custom Bootstrap** (`tests/bootstrap.php`) - Forces test environment before Laravel loads
2. **TestCase Safety Check** (`tests/TestCase::setUp()`) - **CRITICAL**: Runs BEFORE RefreshDatabase migrations
3. **Pest Hook** (`tests/Pest.php`) - Secondary check in beforeEach()
4. **Verification Command** (`php artisan test:verify`) - Runs actual test to verify isolation
5. **Isolation Test** (`tests/Unit/DatabaseIsolationTest.php`) - Unit test for verification
6. **Pre-commit Hook** (`.git/hooks/pre-commit`) - Prevents dangerous commits

### ⚠️ CRITICAL: Execution Order (Why TestCase Check is Essential)

**Test Setup Execution Order:**
1. `tests/TestCase::setUp()` - **SAFETY CHECK HERE** (before migrations)
2. `RefreshDatabase::setUp()` - Runs migrations (could wipe data)
3. `tests/Pest.php` `beforeEach()` - Too late, migrations already ran

**Why This Matters:**
- The `beforeEach()` hook in Pest.php runs AFTER the `RefreshDatabase` trait's `setUp()` method
- By the time Pest's hook runs, migrations have already executed and potentially wiped production data
- The safety check MUST be in `tests/TestCase::setUp()` to run BEFORE migrations

** NEVER Do These Things**

- ❌ **NEVER** use `env('DB_DATABASE')` in safety checks (can be tricked)
- ❌ **NEVER** manually delete/truncate tables in tests
- ❌ **NEVER** run tests without verifying `test:verify` passes
- ❌ **NEVER** commit `.env` file (pre-commit hook will block this)
- ❌ **NEVER** hardcode `'tourney_method'` as the test database name
- ❌ **NEVER** put safety checks only in `beforeEach()` hooks (too late!)

** ALWAYS Do These Things**

- ✅ **ALWAYS** use hardcoded `'tourney_method'` for production DB checks
- ✅ **ALWAYS** verify with `php artisan test:verify` before running tests
- ✅ **ALWAYS** run `DatabaseIsolationTest.php` first if unsure
- ✅ **ALWAYS** stop immediately if safety check triggers
- ✅ **ALWAYS** use `.env.testing` for test configuration
- ✅ **ALWAYS** put PRIMARY safety check in `tests/TestCase::setUp()`

### If Tests Connect to Production

**IMMEDIATE STEPS:**
1. Stop all test execution
2. Run: `php artisan test:verify`
3. Check `tests/bootstrap.php` exists and is loaded by phpunit.xml
4. Check `.env.testing` has `DB_DATABASE=testing`
5. Check `tests/TestCase.php` has safety check in `setUp()` method

---

## Minimum Test Coverage: 80%

Test Types (ALL required):
1. **Unit Tests** - Individual functions, utilities, components
2. **Integration Tests** - API endpoints, database operations
3. **E2E Tests** - Critical user flows (framework chosen per language)

## Test-Driven Development

MANDATORY workflow:
1. Write test first (RED)
2. Run test - it should FAIL
3. Write minimal implementation (GREEN)
4. Run test - it should PASS
5. Refactor (IMPROVE)
6. Verify coverage (80%+)

## Troubleshooting Test Failures

1. Use **tdd-guide** agent
2. Check test isolation
3. Verify mocks are correct
4. Fix implementation, not tests (unless tests are wrong)

## Common Issues and Solutions

### Issue: Cache Pollution Between Tests

**Symptoms:**
- Tests pass individually but fail when run together
- Tests getting stale HTTP response data from previous tests
- `Http::fake()` not working as expected

**Root Cause:**
Services like `OsuApiService` cache responses for 60+ minutes. When tests share the same cache keys (e.g., forum topic IDs), they get stale cached data instead of the mocked responses.

**Solution:**
Clear cache in test hooks:

```php
beforeEach(function () {
    // Clear default cache store (array in test environment)
    Cache::flush();

    // Clear specific service cache keys
    foreach (range(200001, 200005) as $topicId) {
        Cache::forget("forum_topic_{$topicId}");
    }

    // Mock HTTP calls
    Http::fake([/* ... */]);
});

afterEach(function () {
    // Cleanup cache
    Cache::flush();
});
```

**Key Points:**
- Use `Cache::flush()` for array cache (test environment default)
- Don't use `Cache::store('redis')->flush()` unless Redis is available
- Clear specific cache keys that tests might share

### Issue: AWS S3/R2 Backup Errors in Tests

**Symptoms:**
```
Missing required client configuration options:
region: (string)
```

**Root Cause:**
Commands with backup functionality try to connect to AWS S3/R2, but test environment doesn't have AWS credentials configured.

**Solution:**
Use `--no-backup` flag in tests:

```php
$this->artisan('tournaments:parse', [
    '--confirm' => true,
    '--no-backup' => true  // Skip AWS backup
]);
```

**Alternative:** Add `shouldCreateBackup()` check in command:

```php
// In command handle()
if (! $isDryRun && $this->shouldCreateBackup()) {
    $backupPath = $this->createBackup();
}
```

### Issue: Tests Wipe Production Database

**Symptoms:**
- Production data disappears after running tests
- DatabaseIsolationTest fails with "Current database: tourney_method"

**Root Cause:**
Safety check running too late (in `beforeEach()` instead of `TestCase::setUp()`)

**Solution:**
Ensure `tests/TestCase.php` has safety check in `setUp()`:

```php
protected function setUp(): void
{
    // Create app first
    $this->createApplication();

    // CRITICAL: Check database BEFORE RefreshDatabase migrations
    $currentDb = DB::connection()->getDatabaseName();
    $productionDb = 'tourney_method';

    if ($currentDb === $productionDb) {
        $this->fail('CRITICAL: DATABASE SAFETY CHECK FAILED');
    }

    // NOW safe to run parent setup (includes migrations)
    parent::setUp();
}
```

**Verification:**
```bash
docker compose exec -u sail laravel.test php artisan test:verify
```

### Issue: Jobs Not Running in Tests

**Symptoms:**
- Tests create records in database, but they're not updated
- Jobs queued but not executed

**Root Cause:**
Test environment uses `QUEUE_CONNECTION=sync`, but commands use `Bus::batch()` which may not execute synchronously.

**Solution:**
Test the job directly instead of the command:

```php
// Instead of testing command:
$this->artisan('tournaments:parse');

// Test the job directly:
$job = new ParseForumTopicJob($topicId);
$job->handle();
```

Or use `Bus::fake()` and `Bus::assertDispatched()` for command-level tests.

## Test Best Practices

### 1. Cache Management

**DO:**
```php
beforeEach(function () {
    Cache::flush();  // Clear all cache
    Cache::forget('specific_key');  // Clear specific keys
});
```

**DON'T:**
```php
// Don't try to clear stores that don't exist in test env
Cache::store('redis')->flush();  // Redis not available in tests
```

### 2. HTTP Mocking

**DO:**
```php
Http::fake([
    'osu.ppy.sh/api/v2/forums/topics*' => Http::response([/* ... */]),  // Wildcard
    'osu.ppy.sh/api/v2/forums/topics/12345' => Http::response([/* ... */]),
]);
```

**DON'T:**
```php
// Don't forget wildcard for list endpoints
Http::fake([
    'osu.ppy.sh/api/v2/forums/topics' => Http::response([/* ... */]),  // Missing wildcard
]);
```

### 3. Database Safety

**DO:**
```php
// Primary safety check in TestCase::setUp()
protected function setUp(): void {
    $this->createApplication();
    if (DB::connection()->getDatabaseName() === 'tourney_method') {
        $this->fail('CRITICAL: DATABASE SAFETY CHECK FAILED');
    }
    parent::setUp();
}
```

**DON'T:**
```php
// Don't put safety check only in beforeEach()
beforeEach(function () {
    // TOO LATE! RefreshDatabase already ran migrations
    if (DB::connection()->getDatabaseName() === 'tourney_method') {
        throw new RuntimeException('Too late!');
    }
});
```

### 4. Backup Skipping

**DO:**
```php
$this->artisan('command:name', [
    '--confirm' => true,
    '--no-backup' => true,  // Skip AWS S3/R2 backup
]);
```

**DON'T:**
```php
// Don't try to mock AWS credentials in tests
config(['filesystems.disks.s3.region' => 'us-west-1']);
```

## Running Tests

### Full Test Suite
```bash
docker compose exec -u sail laravel.test composer test
```

### Specific Test File
```bash
docker compose exec -u sail laravel.test ./vendor/bin/pest tests/Unit/DatabaseIsolationTest.php
```

### With Coverage
```bash
docker compose exec -u sail laravel.test composer test-coverage
```

### Verify Before Running
```bash
# ALWAYS run this first
docker compose exec -u sail laravel.test php artisan test:verify
```
