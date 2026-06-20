<?php

use App\Services\CldrService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

test('get country name in English', function () {
    $service = app(CldrService::class);

    expect($service->getCountryName('KR', 'en'))->toBe('South Korea');
    expect($service->getCountryName('US', 'en'))->toBe('United States');
    expect($service->getCountryName('JP', 'en'))->toBe('Japan');
});

test('get country name in Korean', function () {
    $service = app(CldrService::class);

    expect($service->getCountryName('KR', 'ko'))->toBe('대한민국');
    expect($service->getCountryName('US', 'ko'))->toBe('미국');
    expect($service->getCountryName('JP', 'ko'))->toBe('일본');
});

test('get country name in Japanese', function () {
    $service = app(CldrService::class);

    expect($service->getCountryName('KR', 'ja'))->toBe('韓国');
    expect($service->getCountryName('JP', 'ja'))->toBe('日本');
    expect($service->getCountryName('US', 'ja'))->toBe('アメリカ合衆国');
});

test('get country name returns code for invalid territory', function () {
    $service = app(CldrService::class);

    // Invalid code should return the code itself as fallback
    expect($service->getCountryName('XX', 'en'))->toBe('XX');
    expect($service->getCountryName('INVALID', 'en'))->toBe('INVALID');
});

test('get countries returns array with proper structure', function () {
    $service = app(CldrService::class);

    $countries = $service->getCountries('en');

    expect($countries)->toBeArray();
    expect($countries)->toHaveKey('KR');
    expect($countries)->toHaveKey('US');
    expect($countries)->toHaveKey('JP');
    expect($countries['KR'])->toBe('South Korea');
});

test('get countries caches results', function () {
    $service = app(CldrService::class);

    // First call
    $countries1 = $service->getCountries('en');

    // Second call should return cached data
    $countries2 = $service->getCountries('en');

    expect($countries1)->toBe($countries2);

    // Verify cache key exists
    expect(Cache::has('cldr:countries:en'))->toBeTrue();
});

test('is valid territory code with valid codes', function () {
    $service = app(CldrService::class);

    expect($service->isValidTerritoryCode('KR'))->toBeTrue();
    expect($service->isValidTerritoryCode('US'))->toBeTrue();
    expect($service->isValidTerritoryCode('JP'))->toBeTrue();
    expect($service->isValidTerritoryCode('GB'))->toBeTrue();
    expect($service->isValidTerritoryCode('FR'))->toBeTrue();
});

test('is valid territory code with invalid codes', function () {
    $service = app(CldrService::class);

    expect($service->isValidTerritoryCode('XX'))->toBeFalse();
    expect($service->isValidTerritoryCode('INVALID'))->toBeFalse();
    expect($service->isValidTerritoryCode('K1'))->toBeFalse();
    expect($service->isValidTerritoryCode('K'))->toBeFalse();
    expect($service->isValidTerritoryCode('USA'))->toBeFalse();
});

test('get native country name', function () {
    $service = app(CldrService::class);

    expect($service->getNativeCountryName('KR'))->toBe('대한민국');
    expect($service->getNativeCountryName('JP'))->toBe('日本');
    expect($service->getNativeCountryName('US'))->toBe('United States');
});

test('get native country name returns code for unsupported territory', function () {
    $service = app(CldrService::class);

    expect($service->getNativeCountryName('XX'))->toBe('XX');
});
