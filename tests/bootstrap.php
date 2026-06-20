<?php

/*
|--------------------------------------------------------------------------
| CRITICAL: Force Test Environment
|--------------------------------------------------------------------------
|
| This MUST run BEFORE Laravel boots to prevent loading .env file.
| Laravel loads .env by default which would override phpunit.xml settings.
|
| This is the PRIMARY defense against tests wiping production data.
|
*/

// Force test environment IMMEDIATELY before any Laravel code runs
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'testing';
$_ENV['DB_CONNECTION'] = 'pgsql';

// Prevent accidental loading of main .env file
putenv('APP_ENV=testing');
putenv('DB_DATABASE=testing');

// Load Composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Explicitly load .env.testing file to get all test config
if (file_exists(__DIR__.'/../.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable(
        __DIR__.'/../',
        '.env.testing'
    );
    $dotenv->load();
}
