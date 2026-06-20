<?php

declare(strict_types=1);

$suite = $argv[1] ?? 'Feature';
$partitionIndex = filter_var($argv[2] ?? 0, FILTER_VALIDATE_INT);
$partitionCount = filter_var($argv[3] ?? 1, FILTER_VALIDATE_INT);
$startAt = isset($argv[4]) ? str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $argv[4]) : null;

if ($partitionIndex === false || $partitionCount === false || $partitionCount < 1
    || $partitionIndex < 0 || $partitionIndex >= $partitionCount) {
    fwrite(STDERR, "Usage: php scripts/run-test-partition.php <suite> <zero-based-index> <partition-count>\n");
    exit(2);
}

$root = dirname(__DIR__);
$suiteDirectory = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.$suite;

if (! is_dir($suiteDirectory)) {
    fwrite(STDERR, "Unknown test suite directory: {$suiteDirectory}\n");
    exit(2);
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($suiteDirectory));
$files = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

sort($files, SORT_STRING);
$selected = array_values(array_filter(
    $files,
    static fn (string $_, int $index): bool => $index % $partitionCount === $partitionIndex,
    ARRAY_FILTER_USE_BOTH,
));

if ($startAt !== null) {
    $selected = array_values(array_filter(
        $selected,
        static fn (string $file): bool => str_replace($root.DIRECTORY_SEPARATOR, '', $file) >= $startAt,
    ));
}

chdir($root);

$run = static function (array $command): void {
    $escaped = array_map('escapeshellarg', $command);
    passthru(implode(' ', $escaped), $exitCode);

    if ($exitCode !== 0) {
        exit($exitCode);
    }
};

$run([PHP_BINARY, 'artisan', 'test:verify']);

foreach ($selected as $file) {
    $relativePath = str_replace($root.DIRECTORY_SEPARATOR, '', $file);
    fwrite(STDOUT, "\n=== {$relativePath} ===\n");

    $run([PHP_BINARY, 'artisan', 'migrate:fresh', '--env=testing', '--force', '--no-interaction', '--quiet']);
    $run([PHP_BINARY, './vendor/bin/pest', $relativePath, '--compact']);
}
