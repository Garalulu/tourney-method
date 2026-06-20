<?php

namespace App\Services\Localization;

use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PublicLocaleExporter
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly PublicLocaleValidator $validator,
    ) {}

    /**
     * @param  array{
     *     locales: list<string>,
     *     files: list<string>,
     *     allowed_url_hosts: list<string>,
     *     forbidden_phrases: list<string>
     * }  $policy
     * @return list<string>
     */
    public function export(string $sourceRoot, string $destinationRoot, array $policy): array
    {
        $sourceRoot = rtrim($sourceRoot, DIRECTORY_SEPARATOR);
        $destinationRoot = rtrim($destinationRoot, DIRECTORY_SEPARATOR);

        if ($destinationRoot === '' || realpath($destinationRoot) === realpath($sourceRoot)) {
            throw new RuntimeException('The public locale destination must be separate from the private application repository.');
        }

        $this->files->ensureDirectoryExists($destinationRoot);
        $this->files->deleteDirectory($destinationRoot.DIRECTORY_SEPARATOR.'lang');

        foreach ($policy['locales'] as $locale) {
            $localeDestination = $destinationRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.$locale;
            $this->files->ensureDirectoryExists($localeDestination);

            foreach ($policy['files'] as $file) {
                $source = $sourceRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$file;

                if (! is_file($source)) {
                    throw new RuntimeException("Missing source locale file: {$source}");
                }

                $this->files->copy($source, $localeDestination.DIRECTORY_SEPARATOR.$file);
            }
        }

        $templateRoot = $sourceRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'public-locales';
        $this->copyDirectoryContents($templateRoot, $destinationRoot);

        return $this->validator->validate($destinationRoot, $policy);
    }

    private function copyDirectoryContents(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            throw new RuntimeException("Missing public locale template directory: {$source}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($source) + 1);
            $target = $destination.DIRECTORY_SEPARATOR.$relative;
            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($file->getPathname(), $target);
        }
    }
}
