<?php

namespace App\Console\Commands;

use App\Services\Localization\PublicLocaleExporter;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class ExportPublicLocales extends Command
{
    protected $signature = 'locales:export-public
                            {repository : Path to a clean checkout of the public locale repository}';

    protected $description = 'Export approved public translations into the locale-only repository';

    public function handle(PublicLocaleExporter $exporter): int
    {
        $repository = $this->absolutePath((string) $this->argument('repository'));

        if ($this->hasUncommittedChanges($repository)) {
            $this->error('The destination repository has uncommitted changes. Commit or discard them before exporting.');

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $policy */
            $policy = config('public-locales');
            $errors = $exporter->export(base_path(), $repository, $policy);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($errors !== []) {
            $this->error('Public locale validation failed:');
            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }

            return self::FAILURE;
        }

        $this->info('Public locale snapshot exported and validated.');
        $this->newLine();
        $this->line('Review the diff below. This command does not commit or push anything.');
        $this->showDiff($repository);

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim($path, '\\/');
        }

        return base_path($path);
    }

    private function hasUncommittedChanges(string $repository): bool
    {
        if (! is_dir($repository.DIRECTORY_SEPARATOR.'.git')) {
            return false;
        }

        $process = new Process(['git', 'status', '--porcelain'], $repository);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function showDiff(string $repository): void
    {
        if (! is_dir($repository.DIRECTORY_SEPARATOR.'.git')) {
            $this->line("Initial snapshot created at {$repository}");

            return;
        }

        $process = new Process(['git', '--no-pager', 'diff', '--stat'], $repository);
        $process->run();
        $output = trim($process->getOutput());
        $this->line($output === '' ? 'No locale changes detected.' : $output);
    }
}
