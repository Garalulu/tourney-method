<?php

use App\Services\UnitedNationsGeoschemeService;

test('geoscheme templates include expected un subregions', function () {
    $templates = collect(app(UnitedNationsGeoschemeService::class)->templates());

    $easternEurope = $templates->firstWhere('name', 'Eastern Europe');
    $centralAsia = $templates->firstWhere('name', 'Central Asia');

    expect($easternEurope)->not->toBeNull()
        ->and($easternEurope['type'])->toBe('subregion')
        ->and($easternEurope['countries'])->toContain('PL', 'UA', 'RU')
        ->and($centralAsia)->not->toBeNull()
        ->and($centralAsia['type'])->toBe('subregion')
        ->and($centralAsia['countries'])->toContain('KZ', 'UZ', 'TJ');
});

test('geoscheme exposes continents intermediary regions and all subregions', function () {
    $templates = collect(app(UnitedNationsGeoschemeService::class)->templates());

    expect($templates->where('type', 'continent')->pluck('name')->all())->toContain(
        'Africa',
        'Americas',
        'Asia',
        'Europe',
        'Oceania',
        'Antarctica'
    );

    expect($templates->where('type', 'intermediary')->pluck('name')->all())->toContain(
        'Sub-Saharan Africa',
        'Latin America and the Caribbean'
    );

    expect($templates->where('type', 'subregion'))->toHaveCount(22);
});

test('geoscheme templates include requested custom regions', function () {
    $templates = collect(app(UnitedNationsGeoschemeService::class)->templates());

    expect($templates->firstWhere('name', 'Russian Speaking')['countries'])->toBe([
        'AM', 'AZ', 'BY', 'EE', 'GE', 'KG', 'KZ', 'LT', 'LV', 'MD', 'RU', 'TJ', 'UA', 'UZ',
    ]);

    expect($templates->firstWhere('name', 'Ibero-American')['countries'])->toBe([
        'AD', 'AR', 'BO', 'BR', 'CL', 'CO', 'CR', 'EC', 'ES', 'GT', 'HN', 'MX', 'NI', 'PA', 'PE', 'PT', 'PY', 'SV', 'UY', 'VE',
    ]);

    expect($templates->firstWhere('name', 'Balkan')['countries'])->toBe([
        'AL', 'BA', 'BG', 'GR', 'HR', 'ME', 'MK', 'RO', 'RS', 'SI', 'XK',
    ]);
});

test('geoscheme templates include extra custom regions', function () {
    $templates = collect(app(UnitedNationsGeoschemeService::class)->templates());

    expect($templates->firstWhere('name', 'Chinese Speaking')['countries'])->toBe(['CN', 'HK', 'MO', 'TW']);
    expect($templates->firstWhere('name', 'Asia-Pacific')['countries'])->toBe([
        'AU', 'BN', 'CN', 'HK', 'ID', 'JP', 'KH', 'KR', 'LA', 'MM', 'MO', 'MY', 'NZ', 'PH', 'SG', 'TH', 'TL', 'TW', 'VN',
    ]);
    expect($templates->firstWhere('name', 'Nordic')['countries'])->toBe(['DK', 'FI', 'IS', 'NO', 'SE']);
    expect($templates->firstWhere('name', 'DACH')['countries'])->toBe(['AT', 'CH', 'DE']);
    expect($templates->firstWhere('name', 'Benelux')['countries'])->toBe(['BE', 'LU', 'NL']);
    expect($templates->firstWhere('name', 'Central Europe')['countries'])->toBe(['AT', 'CH', 'CZ', 'DE', 'HU', 'PL', 'SI', 'SK']);
    expect($templates->firstWhere('name', 'Western Europe')['countries'])->toBe(['AT', 'BE', 'CH', 'DE', 'FR', 'LI', 'LU', 'MC', 'NL']);
});

test('all geoscheme template countries are selectable territory codes', function () {
    $service = app(UnitedNationsGeoschemeService::class);
    $countryCodes = collect($service->countryOptions('en'))->pluck('code')->all();
    $templateCountries = collect($service->templates())
        ->flatMap(fn (array $template) => $template['countries'])
        ->unique()
        ->values();

    expect($templateCountries->diff($countryCodes)->values()->all())->toBe([]);
});

test('country options are searchable by alpha two code and names', function () {
    $countries = collect(app(UnitedNationsGeoschemeService::class)->countryOptions('en'));

    $southKorea = $countries->firstWhere('code', 'KR');

    expect($southKorea)->not->toBeNull()
        ->and($southKorea['name'])->toContain('South Korea')
        ->and($southKorea['english_name'])->toContain('South Korea');
});
