<?php

use Illuminate\Support\Facades\Config;

test('tests use testing database not production', function () {
    $currentDb = DB::connection()->getDatabaseName();
    $productionDb = 'tourney_method';
    $expectedTestDb = 'testing';

    // Primary check: must NOT be production database
    expect($currentDb)->not->toBe($productionDb);

    // Secondary check: should be testing database
    expect($currentDb)->toBe($expectedTestDb);
});

test('environment is set to testing', function () {
    expect(app()->environment())->toBe('testing');
});

test('database config matches test database', function () {
    $configDb = Config::get('database.connections.'.Config::get('database.default').'.database');

    expect($configDb)->toBe('testing');
    expect($configDb)->not->toBe('tourney_method');
});
