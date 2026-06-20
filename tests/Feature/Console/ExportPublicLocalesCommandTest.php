<?php

use Symfony\Component\Process\Process;

test('public locale export omits admin files and unreleased keys', function () {
    $destination = sys_get_temp_dir().'/public-locales-export-'.bin2hex(random_bytes(4));

    $this->artisan('locales:export-public', ['repository' => $destination])
        ->assertSuccessful();

    expect("{$destination}/lang/en/admin.php")->not->toBeFile();
    expect("{$destination}/lang/en/search-internal.php")->not->toBeFile();

    expect("{$destination}/LICENSE")->toBeFile();
    expect("{$destination}/tools/validate-locales.php")->toBeFile();
});

test('public locale import updates allowlisted files and preserves private-only files', function () {
    $public = sys_get_temp_dir().'/public-locales-import-source-'.bin2hex(random_bytes(4));
    $private = sys_get_temp_dir().'/public-locales-import-target-'.bin2hex(random_bytes(4));

    $this->artisan('locales:export-public', ['repository' => $public])
        ->assertSuccessful();

    mkdir("{$private}/config", 0777, true);
    mkdir("{$private}/tools", 0777, true);
    copy(base_path('config/public-locales.php'), "{$private}/config/public-locales.php");
    copy(base_path('tools/import-public-locales.php'), "{$private}/tools/import-public-locales.php");
    foreach (config('public-locales.locales') as $locale) {
        mkdir("{$private}/lang/{$locale}", 0777, true);
        file_put_contents(
            "{$private}/lang/{$locale}/search-internal.php",
            "<?php\n\nreturn ['private_only' => true];\n"
        );

        foreach (config('public-locales.files') as $file) {
            copy(base_path("lang/{$locale}/{$file}"), "{$private}/lang/{$locale}/{$file}");
        }
    }

    $common = require "{$public}/lang/en/common.php";
    $common['nav']['dashboard'] = 'Public translation update';
    file_put_contents("{$public}/lang/en/common.php", "<?php\n\nreturn ".var_export($common, true).";\n");

    $process = new Process([PHP_BINARY, "{$private}/tools/import-public-locales.php", $public, $private]);
    $process->mustRun();

    $imported = require "{$private}/lang/en/common.php";
    expect($imported['nav']['dashboard'])->toBe('Public translation update');
    expect("{$private}/lang/en/search-internal.php")->toBeFile();
});
