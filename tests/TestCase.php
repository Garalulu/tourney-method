<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup the test environment BEFORE traits run.
     *
     * CRITICAL SAFETY: This runs BEFORE RefreshDatabase trait migrates the database.
     * If we check in beforeEach() hook, it's too late - migrations already wiped data.
     */
    protected function setUp(): void
    {
        // Create application first (needed to check database)
        $this->createApplication();

        // Cached local configuration may contain production-oriented drivers.
        // Force isolated, in-memory services before any test traits execute.
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.default' => 'array',
        ]);

        // CRITICAL: Check database BEFORE RefreshDatabase runs migrations
        // This MUST happen before parent::setUp() calls trait setup methods
        $currentDb = DB::connection()->getDatabaseName();
        $productionDb = 'tourney_method';

        if ($currentDb === $productionDb) {
            $this->fail(
                "\n".
                "╔═══════════════════════════════════════════════════════════════════╗\n".
                "║           CRITICAL: DATABASE SAFETY CHECK FAILED                  ║\n".
                "╠═══════════════════════════════════════════════════════════════════╣\n".
                "║  Current database: {$currentDb}                                  \n".
                "║  Production database: {$productionDb}                             \n".
                "║                                                                 \n".
                "║  ERROR: Tests are running against PRODUCTION DATABASE!           \n".
                "║  RefreshDatabase trait will WIPE REAL DATA via migrations!       \n".
                "║                                                                 \n".
                "║  IMMEDIATE ACTIONS:                                               \n".
                "║  1. STOP running tests immediately                                \n".
                "║  2. Verify tests/bootstrap.php is loaded by phpunit.xml          \n".
                "║  3. Verify .env.testing has DB_DATABASE=testing                  \n".
                "║  4. Run: php artisan test:verify                                 \n".
                "║                                                                 \n".
                '║  Environment: '.app()->environment()."\n".
                "╚═══════════════════════════════════════════════════════════════════╝\n"
            );
        }

        // Now call parent setup (includes RefreshDatabase migrations)
        parent::setUp();
    }
}
