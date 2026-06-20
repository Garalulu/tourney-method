<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pest\Expectation;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| CRITICAL SAFETY CHECK
|--------------------------------------------------------------------------
|
| Prevents tests from running against the main database and wiping real data.
| This check runs BEFORE any test with RefreshDatabase trait.
|
*/

beforeEach(function () {
    $currentDb = DB::connection()->getDatabaseName();

    // HARDCODED production database name - never reads from config
    $mainDb = 'tourney_method';
    $expectedTestDb = 'testing';

    // Safety check: Ensure we're in testing database
    if ($currentDb === $mainDb) {
        throw new RuntimeException(
            "\n".
            "╔═══════════════════════════════════════════════════════════════════╗\n".
            "║           CRITICAL: DATABASE SAFETY CHECK FAILED                  ║\n".
            "╠═══════════════════════════════════════════════════════════════════╣\n".
            "║  Current database: {$currentDb}                                  \n".
            "║  Expected test database: {$expectedTestDb}                        \n".
            "║  Production database: {$mainDb}                                  \n".
            "║                                                                 \n".
            "║  ERROR: Tests are running against PRODUCTION DATABASE!           \n".
            "║  This will WIPE REAL DATA when RefreshDatabase rolls back!       \n".
            "║                                                                 \n".
            "║  IMMEDIATE ACTIONS:                                               \n".
            "║  1. STOP running tests immediately                                \n".
            "║  2. Verify tests/bootstrap.php exists and is loaded              \n".
            "║  3. Verify .env.testing has DB_DATABASE=testing                  \n".
            "║  4. Run: php artisan test:verify                                 \n".
            "║                                                                 \n".
            '║  Environment: '.app()->environment()."\n".
            "╚═══════════════════════════════════════════════════════════════════╝\n"
        );
    }

    // Log which database we're using for debugging
    if (app()->environment('testing')) {
        Log::info("Tests running in database: {$currentDb}");
    }
});

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');
uses(TestCase::class, RefreshDatabase::class)->in('Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    /** @var Expectation $this */
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}
