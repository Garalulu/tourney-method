<?php

use App\Services\Localization\PublicLocaleValidator;

/**
 * @return array{
 *     locales: list<string>,
 *     files: list<string>,
 *     allowed_url_hosts: list<string>,
 *     forbidden_phrases: list<string>
 * }
 */
function publicLocalePolicy(): array
{
    return [
        'locales' => ['en', 'ko'],
        'files' => ['common.php'],
        'allowed_url_hosts' => ['support.discord.com'],
        'forbidden_phrases' => ['coming soon', 'unreleased'],
    ];
}

function writeLocaleFixture(string $root, string $locale, string $contents): void
{
    $directory = "{$root}/lang/{$locale}";
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents("{$directory}/common.php", $contents);
}

test('public locale validator accepts matching safe translations', function () {
    $root = sys_get_temp_dir().'/locale-validator-'.bin2hex(random_bytes(4));
    writeLocaleFixture($root, 'en', "<?php return ['hello' => 'Hello :name'];");
    writeLocaleFixture($root, 'ko', "<?php return ['hello' => '안녕하세요 :name'];");

    $errors = (new PublicLocaleValidator)->validate($root, publicLocalePolicy());

    expect($errors)->toBe([]);
});

test('public locale validator rejects mismatched keys placeholders and forbidden content', function () {
    $root = sys_get_temp_dir().'/locale-validator-'.bin2hex(random_bytes(4));
    writeLocaleFixture($root, 'en', "<?php return ['hello' => 'Hello :name'];");
    writeLocaleFixture($root, 'ko', "<?php return ['other' => 'Coming soon :user'];");

    $errors = (new PublicLocaleValidator)->validate($root, publicLocalePolicy());

    expect(implode("\n", $errors))
        ->toContain('Forbidden phrase')
        ->toContain('Missing key')
        ->toContain('Unexpected key');
});

test('public locale validator rejects unexpected files credentials and internal urls', function () {
    $root = sys_get_temp_dir().'/locale-validator-'.bin2hex(random_bytes(4));
    $fakeGithubToken = 'ghp_'.str_repeat('x', 36);
    writeLocaleFixture($root, 'en', "<?php return ['hello' => 'https://localhost/admin {$fakeGithubToken}'];");
    writeLocaleFixture($root, 'ko', "<?php return ['hello' => 'https://localhost/admin {$fakeGithubToken}'];");
    file_put_contents("{$root}/lang/en/admin.php", '<?php return [];');

    $errors = (new PublicLocaleValidator)->validate($root, publicLocalePolicy());

    expect(implode("\n", $errors))
        ->toContain('Unexpected public locale file')
        ->toContain('Possible credential')
        ->toContain('Internal host or address');
});
