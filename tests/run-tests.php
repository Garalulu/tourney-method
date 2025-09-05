<?php

/**
 * Test Runner Script
 * 
 * Comprehensive test execution script that runs different test suites
 * with proper reporting and error handling.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TourneyMethod\Tests\Helpers\TestHelper;

class TestRunner
{
    private const TEST_SUITES = [
        'unit' => 'Unit Tests',
        'integration' => 'Integration Tests', 
        'functional' => 'Functional Tests',
        'all' => 'All Tests'
    ];
    
    private const GROUPS = [
        'security' => 'Security Tests',
        'oauth' => 'OAuth Tests',
        'localization' => 'Korean Localization Tests'
    ];
    
    public static function main(array $args): void
    {
        $runner = new self();
        
        if (empty($args)) {
            $runner->showHelp();
            return;
        }
        
        $command = $args[0] ?? '';
        $suite = $args[1] ?? 'all';
        
        switch ($command) {
            case 'run':
                $runner->runTests($suite);
                break;
            case 'coverage':
                $runner->runWithCoverage($suite);
                break;
            case 'group':
                $group = $args[1] ?? '';
                $runner->runGroup($group);
                break;
            case 'clean':
                $runner->cleanTestEnvironment();
                break;
            case 'setup':
                $runner->setupTestEnvironment();
                break;
            default:
                $runner->showHelp();
        }
    }
    
    private function runTests(string $suite): void
    {
        echo "🧪 Running " . (self::TEST_SUITES[$suite] ?? $suite) . "\n";
        echo str_repeat('=', 50) . "\n";
        
        $this->setupTestEnvironment();
        
        $command = $this->buildPhpUnitCommand($suite);
        $this->executeCommand($command);
        
        $this->cleanTestEnvironment();
    }
    
    private function runWithCoverage(string $suite): void
    {
        echo "📊 Running " . (self::TEST_SUITES[$suite] ?? $suite) . " with Coverage\n";
        echo str_repeat('=', 50) . "\n";
        
        $this->setupTestEnvironment();
        
        $command = $this->buildPhpUnitCommand($suite, true);
        $this->executeCommand($command);
        
        echo "\n📋 Coverage report generated in coverage/\n";
        
        $this->cleanTestEnvironment();
    }
    
    private function runGroup(string $group): void
    {
        echo "🎯 Running " . (self::GROUPS[$group] ?? $group) . " Group\n";
        echo str_repeat('=', 50) . "\n";
        
        $this->setupTestEnvironment();
        
        $command = "./vendor/bin/phpunit --group {$group} --colors --verbose";
        $this->executeCommand($command);
        
        $this->cleanTestEnvironment();
    }
    
    private function buildPhpUnitCommand(string $suite, bool $withCoverage = false): string
    {
        $command = './vendor/bin/phpunit';
        
        if ($suite !== 'all') {
            $command .= " --testsuite {$suite}";
        }
        
        $command .= ' --colors --verbose';
        
        if ($withCoverage) {
            $command .= ' --coverage-html coverage/ --coverage-text';
        }
        
        return $command;
    }
    
    private function executeCommand(string $command): void
    {
        echo "💻 Executing: {$command}\n\n";
        
        $startTime = microtime(true);
        passthru($command, $exitCode);
        $endTime = microtime(true);
        
        $duration = round($endTime - $startTime, 2);
        
        echo "\n" . str_repeat('-', 50) . "\n";
        echo "⏱️  Test execution time: {$duration} seconds\n";
        
        if ($exitCode === 0) {
            echo "✅ Tests completed successfully!\n";
        } else {
            echo "❌ Tests failed with exit code: {$exitCode}\n";
            exit($exitCode);
        }
    }
    
    private function setupTestEnvironment(): void
    {
        echo "🔧 Setting up test environment...\n";
        
        TestHelper::setupTestEnvironment();
        TestHelper::cleanupTestDatabase();
        
        // Create test directories if they don't exist
        $testDirs = [
            __DIR__ . '/fixtures',
            __DIR__ . '/fixtures/sessions',
            dirname(__DIR__) . '/coverage'
        ];
        
        foreach ($testDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        echo "✅ Test environment ready\n\n";
    }
    
    private function cleanTestEnvironment(): void
    {
        echo "\n🧹 Cleaning up test environment...\n";
        
        TestHelper::cleanupTestDatabase();
        TestHelper::cleanupTestEnvironment();
        TestHelper::clearTestSession();
        
        echo "✅ Cleanup complete\n";
    }
    
    private function showHelp(): void
    {
        echo "\n🧪 Tourney Method Test Runner\n";
        echo str_repeat('=', 30) . "\n\n";
        
        echo "Usage:\n";
        echo "  php tests/run-tests.php <command> [options]\n\n";
        
        echo "Commands:\n";
        echo "  run [suite]        Run test suite (unit|integration|functional|all)\n";
        echo "  coverage [suite]   Run tests with coverage report\n";
        echo "  group <group>      Run specific test group\n";
        echo "  setup              Set up test environment only\n";
        echo "  clean              Clean test environment only\n\n";
        
        echo "Test Suites:\n";
        foreach (self::TEST_SUITES as $key => $name) {
            echo "  {$key:<12} {$name}\n";
        }
        echo "\n";
        
        echo "Test Groups:\n";
        foreach (self::GROUPS as $key => $name) {
            echo "  {$key:<12} {$name}\n";
        }
        echo "\n";
        
        echo "Examples:\n";
        echo "  php tests/run-tests.php run unit\n";
        echo "  php tests/run-tests.php coverage all\n";
        echo "  php tests/run-tests.php group security\n\n";
    }
}

// Run the test runner if called directly
if (isset($argv) && basename($argv[0]) === 'run-tests.php') {
    TestRunner::main(array_slice($argv, 1));
}