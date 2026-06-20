<?php

declare(strict_types=1);

$publicRoot = realpath($argv[1] ?? '');
$privateRoot = realpath($argv[2] ?? dirname(__DIR__));

if ($publicRoot === false || $privateRoot === false) {
    fwrite(STDERR, "Usage: php tools/import-public-locales.php <public-repository> [private-repository]\n");
    exit(1);
}

$validator = $publicRoot.'/tools/validate-locales.php';
passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($validator), $validatorExit);
if ($validatorExit !== 0) {
    exit($validatorExit);
}

$policy = require $privateRoot.'/config/public-locales.php';

foreach ($policy['locales'] as $locale) {
    foreach ($policy['files'] as $file) {
        $publicPath = "{$publicRoot}/lang/{$locale}/{$file}";
        $privatePath = "{$privateRoot}/lang/{$locale}/{$file}";
        copy($publicPath, $privatePath);
    }
}

fwrite(STDOUT, "Public locales imported; private-only files were left untouched.\n");
