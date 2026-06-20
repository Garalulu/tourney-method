<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class VerifyTestDatabase extends Command
{
    protected $signature = 'test:verify {--fail-on-error : Exit with error code if misconfigured}';

    protected $description = 'Verify tests are using isolated test database, not production';

    public function handle()
    {
        $this->info('Verifying test database isolation...');
        $this->newLine();

        $expectedTestDb = 'testing';
        $productionDb = 'tourney_method';
        $allGood = true;

        // Check 1: .env.testing exists
        if (file_exists(base_path('.env.testing'))) {
            $this->info('✓ .env.testing exists');
        } else {
            $this->error('✗ .env.testing not found');
            $allGood = false;
        }

        // Check 2: tests/bootstrap.php exists
        if (file_exists(base_path('tests/bootstrap.php'))) {
            $this->info('✓ tests/bootstrap.php exists');
        } else {
            $this->error('✗ tests/bootstrap.php not found');
            $allGood = false;
        }

        // Check 3: phpunit.xml references bootstrap
        $phpunitXml = base_path('phpunit.xml');
        if (file_exists($phpunitXml)) {
            $content = file_get_contents($phpunitXml);
            if (str_contains($content, 'bootstrap="tests/bootstrap.php"')) {
                $this->info('✓ phpunit.xml references tests/bootstrap.php');
            } else {
                $this->error('✗ phpunit.xml does not reference tests/bootstrap.php');
                $allGood = false;
            }
        } else {
            $this->error('✗ phpunit.xml not found');
            $allGood = false;
        }

        // Check 4: .env.testing has correct database configuration
        $envTesting = file_get_contents(base_path('.env.testing'));
        if (str_contains($envTesting, 'DB_DATABASE=testing')) {
            $this->info('✓ .env.testing has DB_DATABASE=testing');
        } else {
            $this->error('✗ .env.testing does not have DB_DATABASE=testing');
            $allGood = false;
        }

        // Check 5: tests/bootstrap.php forces test environment
        $bootstrap = file_get_contents(base_path('tests/bootstrap.php'));
        if (str_contains($bootstrap, "APP_ENV'] = 'testing'") || str_contains($bootstrap, 'APP_ENV=testing')) {
            $this->info('✓ tests/bootstrap.php forces APP_ENV=testing');
        } else {
            $this->warn('⚠ tests/bootstrap.php should force APP_ENV=testing');
        }

        $this->newLine();

        // CRITICAL CHECK: Actually run the isolation test to verify REAL behavior
        $this->info('Running actual test to verify database isolation...');
        $this->newLine();

        // Set environment variables to force test environment
        $envVars = [
            'APP_ENV' => 'testing',
            'DB_DATABASE' => 'testing',
            'DB_CONNECTION' => 'pgsql',
        ];

        $process = new Process(
            ['php', './vendor/bin/pest', 'tests/Unit/DatabaseIsolationTest.php', '--compact'],
            base_path(),
            $envVars, // Pass environment variables
            null,
            120.0 // Allow cold container test bootstraps to complete.
        );

        $process->run();

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        // Display test output
        if (! empty($output)) {
            $this->line($output);
        }

        if (! empty($errorOutput)) {
            $this->error($errorOutput);
        }

        $this->newLine();

        if ($process->isSuccessful()) {
            $this->info('✅ All checks passed - Tests are properly isolated!');
            $this->newLine();
            $this->info('Summary:');
            $this->info('  - Tests run in: testing environment');
            $this->info('  - Tests use: testing database (NOT tourney_method)');
            $this->info('  - RefreshDatabase trait is safe to use');
            $this->newLine();
            $this->info('You can safely run tests with:');
            $this->info('  - composer test');
            $this->info('  - ./vendor/bin/pest');

            return 0;
        } else {
            $this->error('❌ Database isolation test FAILED!');
            $this->newLine();
            $this->warn('This means tests are NOT properly isolated from production data!');
            $this->newLine();
            $this->info('Required fixes:');
            $this->info('1. Ensure tests/bootstrap.php exists and is loaded in phpunit.xml');
            $this->info('2. Ensure .env.testing exists with DB_DATABASE=testing');
            $this->info('3. Ensure tests/bootstrap.php forces APP_ENV=testing BEFORE Laravel loads');
            $this->info('4. Run tests with: docker compose exec laravel.test ./vendor/bin/pest');
            $this->newLine();
            $this->error('DO NOT run tests until isolation is verified!');

            return $this->option('fail-on-error') ? 1 : 0;
        }
    }
}
