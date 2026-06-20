<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$policy = require $root.'/config/public-locales.php';
$errors = [];
$flatten = function (array $values, string $prefix = '') use (&$flatten): array {
    $result = [];
    foreach ($values as $key => $value) {
        $qualified = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        if (is_array($value)) {
            $result += $flatten($value, $qualified);
        } elseif (is_string($value)) {
            $result[$qualified] = $value;
        }
    }

    return $result;
};
$placeholders = function (string $value): array {
    preg_match_all('/(?<!:):([a-z_][A-Za-z0-9_]*)|\{([a-z_][A-Za-z0-9_]*)\}|%(?:\d+\$)?[bcdeEfFgGosuxX](?![A-Fa-f0-9])/', $value, $matches);
    $result = array_values(array_unique($matches[0]));
    sort($result);

    return $result;
};
$expectedPaths = [];
foreach ($policy['locales'] as $locale) {
    foreach ($policy['files'] as $file) {
        $expectedPaths["{$locale}/{$file}"] = true;
    }
}
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/lang', RecursiveDirectoryIterator::SKIP_DOTS),
);
foreach ($iterator as $candidate) {
    if (! $candidate->isFile() || $candidate->getExtension() !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($candidate->getPathname(), strlen($root.'/lang') + 1));
    if (! isset($expectedPaths[$relative])) {
        $errors[] = "Unexpected public locale file: lang/{$relative}";
    }
}
$source = [];
foreach ($policy['files'] as $file) {
    $sourcePath = $root."/lang/en/{$file}";
    if (! is_file($sourcePath)) {
        $errors[] = "Missing required file: lang/en/{$file}";

        continue;
    }
    try {
        $sourceValues = require $sourcePath;
    } catch (Throwable $exception) {
        $errors[] = "Invalid PHP in lang/en/{$file}: {$exception->getMessage()}";

        continue;
    }
    if (! is_array($sourceValues)) {
        $errors[] = "Locale file must return an array: lang/en/{$file}";

        continue;
    }
    $source[$file] = $flatten($sourceValues);
}
foreach ($policy['locales'] as $locale) {
    foreach ($policy['files'] as $file) {
        $path = $root."/lang/{$locale}/{$file}";
        if (! is_file($path)) {
            $errors[] = "Missing required file: lang/{$locale}/{$file}";

            continue;
        }
        try {
            $values = require $path;
        } catch (Throwable $exception) {
            $errors[] = "Invalid PHP in lang/{$locale}/{$file}: {$exception->getMessage()}";

            continue;
        }
        if (! is_array($values)) {
            $errors[] = "Locale file must return an array: lang/{$locale}/{$file}";

            continue;
        }
        $flat = $flatten($values);
        $expectedKeys = array_keys($source[$file] ?? []);
        foreach (array_diff($expectedKeys, array_keys($flat)) as $key) {
            $errors[] = "Missing key in {$locale}: {$file}:{$key}";
        }
        foreach (array_diff(array_keys($flat), $expectedKeys) as $key) {
            $errors[] = "Unexpected key in {$locale}: {$file}:{$key}";
        }
        foreach ($flat as $key => $value) {
            foreach ($policy['forbidden_phrases'] as $phrase) {
                if (str_contains(mb_strtolower($value), mb_strtolower($phrase))) {
                    $errors[] = "Forbidden phrase '{$phrase}' at lang/{$locale}/{$file}:{$key}";
                }
            }
            foreach ([
                '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
                '/\bAKIA[0-9A-Z]{16}\b/',
                '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/',
                '/\bsk-[A-Za-z0-9]{20,}\b/',
                '/https?:\/\/[^\/\s:@]+:[^\/\s@]+@/i',
            ] as $secretPattern) {
                if (preg_match($secretPattern, $value) === 1) {
                    $errors[] = "Possible credential at lang/{$locale}/{$file}:{$key}";
                }
            }
            preg_match_all('/https?:\/\/[^\s\'"<>]+/i', $value, $matches);
            foreach ($matches[0] as $url) {
                $url = rtrim($url, '.,);]');
                $parts = parse_url($url);
                $host = strtolower($parts['host'] ?? '');
                if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, $policy['allowed_url_hosts'], true)) {
                    $errors[] = "URL is invalid or not allowlisted at lang/{$locale}/{$file}:{$key}";
                }
            }
            if (preg_match('/(?:localhost|127\.0\.0\.1|0\.0\.0\.0|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)/i', $value) === 1) {
                $errors[] = "Internal host or address at lang/{$locale}/{$file}:{$key}";
            }
            if (isset($source[$file][$key]) && $placeholders($value) !== $placeholders($source[$file][$key])) {
                $errors[] = "Placeholder mismatch at lang/{$locale}/{$file}:{$key}";
            }
        }
    }
}
$errors = array_values(array_unique($errors));
if ($errors !== []) {
    fwrite(STDERR, "Locale validation failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
fwrite(STDOUT, "Locale validation passed.\n");
