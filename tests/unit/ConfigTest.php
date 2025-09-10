<?php

namespace TourneyMethod\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testDevelopmentConfigExists(): void
    {
        $configPath = __DIR__ . '/../../config/development.php';
        $this->assertFileExists($configPath, 'Development config file should exist');
        
        $config = require $configPath;
        $this->assertIsArray($config, 'Config should return an array');
    }

    public function testDevelopmentConfigStructure(): void
    {
        $config = require __DIR__ . '/../../config/development.php';
        
        $expectedKeys = ['database', 'oauth', 'app', 'security', 'logging', 'api'];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Config should have '{$key}' section");
        }
    }

    public function testDatabaseConfigStructure(): void
    {
        $config = require __DIR__ . '/../../config/development.php';
        
        $this->assertArrayHasKey('path', $config['database']);
        $this->assertArrayHasKey('options', $config['database']);
        
        $dbPath = $config['database']['path'];
        $this->assertStringContainsString('tournament_method.db', $dbPath);
    }

    public function testSecurityConfigDefaults(): void
    {
        $config = require __DIR__ . '/../../config/development.php';
        
        $this->assertArrayHasKey('csrf_token_name', $config['security']);
        $this->assertArrayHasKey('session_name', $config['security']);
        $this->assertArrayHasKey('session_lifetime', $config['security']);
        
        $this->assertIsInt($config['security']['session_lifetime']);
        $this->assertGreaterThan(0, $config['security']['session_lifetime']);
    }

    public function testAppConfigDefaults(): void
    {
        $config = require __DIR__ . '/../../config/development.php';
        
        $this->assertTrue($config['app']['debug']);
        $this->assertEquals('Asia/Seoul', $config['app']['timezone']);
        $this->assertEquals('development', $config['app']['environment']);
    }
}