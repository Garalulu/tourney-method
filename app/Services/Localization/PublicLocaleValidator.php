<?php

namespace App\Services\Localization;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class PublicLocaleValidator
{
    /**
     * @param  array{
     *     locales: list<string>,
     *     files: list<string>,
     *     allowed_url_hosts: list<string>,
     *     forbidden_phrases: list<string>
     * }  $policy
     * @return list<string>
     */
    public function validate(string $root, array $policy): array
    {
        $errors = [];
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $langRoot = $root.DIRECTORY_SEPARATOR.'lang';

        if (! is_dir($langRoot)) {
            return ["Missing locale directory: {$langRoot}"];
        }

        $errors = array_merge($errors, $this->validateFileSet($langRoot, $policy));

        $sourceKeys = null;
        $sourcePlaceholders = [];

        foreach ($policy['locales'] as $locale) {
            foreach ($policy['files'] as $file) {
                $path = $langRoot.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$file;

                if (! is_file($path)) {
                    $errors[] = "Missing required file: lang/{$locale}/{$file}";

                    continue;
                }

                try {
                    $translations = require $path;
                } catch (Throwable $exception) {
                    $errors[] = "Invalid PHP in lang/{$locale}/{$file}: {$exception->getMessage()}";

                    continue;
                }

                if (! is_array($translations)) {
                    $errors[] = "Locale file must return an array: lang/{$locale}/{$file}";

                    continue;
                }

                $flat = $this->flatten($translations);
                $qualified = [];

                foreach ($flat as $key => $value) {
                    $fullKey = "{$file}:{$key}";
                    $qualified[$fullKey] = $value;
                    $errors = array_merge(
                        $errors,
                        $this->validateValue("lang/{$locale}/{$file}:{$key}", $value, $policy),
                    );
                }

                if ($locale === 'en') {
                    $sourceKeys ??= [];
                    $sourceKeys = array_merge($sourceKeys, array_keys($qualified));

                    foreach ($qualified as $key => $value) {
                        $sourcePlaceholders[$key] = $this->placeholders($value);
                    }

                    continue;
                }

                if ($sourceKeys === null) {
                    continue;
                }

                $errors = array_merge(
                    $errors,
                    $this->compareKeys($locale, $file, $sourceKeys, array_keys($qualified)),
                );

                foreach ($qualified as $key => $value) {
                    if (! array_key_exists($key, $sourcePlaceholders)) {
                        continue;
                    }

                    $actual = $this->placeholders($value);
                    if ($actual !== $sourcePlaceholders[$key]) {
                        $errors[] = sprintf(
                            'Placeholder mismatch for %s in %s: expected [%s], got [%s]',
                            $key,
                            $locale,
                            implode(', ', $sourcePlaceholders[$key]),
                            implode(', ', $actual),
                        );
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array{locales: list<string>, files: list<string>}  $policy
     * @return list<string>
     */
    private function validateFileSet(string $langRoot, array $policy): array
    {
        $errors = [];
        $allowed = [];

        foreach ($policy['locales'] as $locale) {
            foreach ($policy['files'] as $file) {
                $allowed[str_replace('\\', '/', "{$locale}/{$file}")] = true;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($langRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(
                ['\\', str_replace('\\', '/', $langRoot).'/'],
                ['/', ''],
                str_replace('\\', '/', $file->getPathname()),
            );

            if (! isset($allowed[$relative])) {
                $errors[] = "Unexpected public locale file: lang/{$relative}";
            }
        }

        return $errors;
    }

    /**
     * @param  array<array-key, mixed>  $translations
     * @return array<string, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            $qualified = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $qualified));

                continue;
            }

            if (is_string($value)) {
                $flat[$qualified] = $value;
            }
        }

        return $flat;
    }

    /**
     * @param  array{allowed_url_hosts: list<string>, forbidden_phrases: list<string>}  $policy
     * @return list<string>
     */
    private function validateValue(string $location, string $value, array $policy): array
    {
        $errors = [];
        $lower = mb_strtolower($value);

        foreach ($policy['forbidden_phrases'] as $phrase) {
            if (str_contains($lower, mb_strtolower($phrase))) {
                $errors[] = "Forbidden phrase '{$phrase}' at {$location}";
            }
        }

        $secretPatterns = [
            '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
            '/\bAKIA[0-9A-Z]{16}\b/',
            '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/',
            '/\bsk-[A-Za-z0-9]{20,}\b/',
            '/https?:\/\/[^\/\s:@]+:[^\/\s@]+@/i',
        ];

        foreach ($secretPatterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $errors[] = "Possible credential at {$location}";
            }
        }

        preg_match_all('/https?:\/\/[^\s\'"<>]+/i', $value, $matches);

        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,);]');
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');

            if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
                $errors[] = "Invalid or insecure URL '{$url}' at {$location}";

                continue;
            }

            if (! in_array($host, $policy['allowed_url_hosts'], true)) {
                $errors[] = "URL host '{$host}' is not allowlisted at {$location}";
            }
        }

        if (preg_match('/(?:localhost|127\.0\.0\.1|0\.0\.0\.0|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)/i', $value) === 1) {
            $errors[] = "Internal host or address at {$location}";
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/(?<!:):([a-z_][A-Za-z0-9_]*)|\{([a-z_][A-Za-z0-9_]*)\}|%(?:\d+\$)?[bcdeEfFgGosuxX](?![A-Fa-f0-9])/', $value, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return array_values(array_unique($placeholders));
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     * @return list<string>
     */
    private function compareKeys(string $locale, string $file, array $expected, array $actual): array
    {
        $prefix = "{$file}:";
        $expectedForFile = array_values(array_filter($expected, fn (string $key): bool => str_starts_with($key, $prefix)));
        $missing = array_diff($expectedForFile, $actual);
        $extra = array_diff($actual, $expectedForFile);
        $errors = [];

        foreach ($missing as $key) {
            $errors[] = "Missing key in {$locale}: {$key}";
        }

        foreach ($extra as $key) {
            $errors[] = "Unexpected key in {$locale}: {$key}";
        }

        return $errors;
    }
}
