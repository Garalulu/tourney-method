<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserProfilesJob;
use App\Models\AdminMaintenanceRun;
use App\Models\User;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\SyncUserProfilesBatchHandler;
use App\Services\UserProfileSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncUserProfiles extends Command
{
    protected $signature = 'sync:user-profiles
        {--type=all : User type to sync: all|winners|staff|hosts}
        {--start-year= : Start year for filtering tournaments (default: last year)}
        {--end-year= : End year for filtering tournaments (default: current year)}
        {--queue : Dispatch as queued job instead of interactive execution}
        {--force : Run even if recently synced}
        {--dry-run : Preview changes without saving}
        {--essential-only : Only sync users missing essential profile data}
        {--skip-sip : Skip SIP fetch queuing}
        {--chunk-size=10 : Users per queued job (default: 10, only applies with --queue)}
        {--batch-size=50 : Users per batch (for staff basic sync)}
        {--delay=1 : Delay between individual syncs (seconds, for winner full sync)}';

    protected $description = 'Sync user profile data from osu! API for tournament participants';

    /** @var Collection<int, User> */
    private Collection $failedUsers;

    private UserProfileSyncService $profileSyncService;

    private int $newBadgeUsers = 0;

    private int $newBadges = 0;

    public function handle(AdminMaintenanceRunRecorder $maintenanceRuns): int
    {
        $type = $this->normalizeType((string) $this->option('type'));

        if ($type === null) {
            $this->error('Invalid --type value. Allowed values: all, winners, staff, hosts.');

            return self::FAILURE;
        }

        [$startYear, $endYear, $usedDynamicDefault] = $this->resolveYearRange();
        $dryRun = (bool) $this->option('dry-run');
        $skipSip = (bool) $this->option('skip-sip');
        $essentialOnly = (bool) $this->option('essential-only');
        $maintenanceRun = $dryRun ? null : $maintenanceRuns->start(AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES, [
            'type' => $type,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'essential_only' => $essentialOnly,
            'skip_sip' => $skipSip,
            'queue' => (bool) $this->option('queue'),
        ]);

        if ($this->option('queue')) {
            return $this->dispatchBatches(
                $type,
                $startYear,
                $endYear,
                $skipSip,
                $essentialOnly,
                (int) $this->option('chunk-size'),
                $usedDynamicDefault,
                $maintenanceRun?->id
            );
        }

        $this->info($startYear === $endYear
            ? "Syncing {$type} users from {$startYear}..."
            : "Syncing {$type} users from {$startYear} to {$endYear}..."
        );

        if ($usedDynamicDefault) {
            $this->info('(Using dynamic year range: '.($endYear - 1)." to {$endYear})");
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($essentialOnly) {
            $this->warn('ESSENTIAL ONLY MODE - Filtering to users missing essential profile data');
        }

        if ($skipSip) {
            $this->warn('SIP queuing SKIPPED (--skip-sip flag set)');
        }

        $this->failedUsers = collect();
        $this->profileSyncService = app(UserProfileSyncService::class);

        $users = User::getTournamentUsers($type, $startYear, $endYear, $essentialOnly);

        if ($users->isEmpty()) {
            $this->warn('No users found for the specified criteria.');
            $maintenanceRuns->complete($maintenanceRun, [
                'processed' => 0,
                'failed' => 0,
                'new_badge_users' => 0,
                'new_badges' => 0,
            ]);

            return self::SUCCESS;
        }

        $winners = $users->filter(fn ($u) => $u->tournamentWinners()->exists());
        $staffAndHosts = $users->filter(fn ($u) => ! $u->tournamentWinners()->exists());

        $this->info("Found {$users->count()} users to sync.");
        $this->info("  - Winners: {$winners->count()} (full profile sync)");
        $this->info("  - Staff/Hosts: {$staffAndHosts->count()} (basic info sync)");

        if ($staffAndHosts->isNotEmpty()) {
            $this->syncBasicInfoBatch($staffAndHosts, $dryRun);
        }

        if ($winners->isNotEmpty()) {
            $this->processWinners($winners, $startYear, $endYear, $dryRun, $skipSip, $maintenanceRun);
        }

        $this->newLine();
        $this->info('Sync complete!');
        $this->info("  - Total users: {$users->count()}");
        $this->info("  - Users with new badges: {$this->newBadgeUsers}");
        $this->info("  - Failed: {$this->failedUsers->count()}");
        $maintenanceRuns->complete($maintenanceRun, [
            'processed' => $users->count(),
            'failed' => $this->failedUsers->count(),
            'new_badge_users' => $this->newBadgeUsers,
            'new_badges' => $this->newBadges,
        ]);

        if ($this->failedUsers->isNotEmpty()) {
            $this->newLine();
            $this->warn('Failed users:');

            foreach ($this->failedUsers as $user) {
                $this->line("  - {$user->username} (osu_id: {$user->osu_id})");
            }
        }

        return self::SUCCESS;
    }

    private function normalizeType(string $type): ?string
    {
        return match ($type) {
            'all', 'winners', 'staff', 'hosts' => $type,
            'winner' => 'winners',
            'host' => 'hosts',
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int, 2: bool}
     */
    private function resolveYearRange(): array
    {
        $currentYear = (int) date('Y');
        $lastYear = $currentYear - 1;

        $startYearOption = $this->option('start-year');
        $endYearOption = $this->option('end-year');

        $startYear = ($startYearOption !== '' && $startYearOption !== null) ? (int) $startYearOption : $lastYear;
        $endYear = ($endYearOption !== '' && $endYearOption !== null) ? (int) $endYearOption : $currentYear;

        return [$startYear, $endYear, $startYear === $lastYear && $endYear === $currentYear && ($startYearOption === '' || $startYearOption === null)];
    }

    private function dispatchBatches(
        string $type,
        int $startYear,
        int $endYear,
        bool $skipSip,
        bool $essentialOnly,
        int $chunkSize,
        bool $usedDynamicDefault,
        ?int $maintenanceRunId = null
    ): int {
        if ($usedDynamicDefault) {
            $this->info('(Using dynamic year range: '.($endYear - 1)." to {$endYear})");
        }

        $this->info("Dispatching batched jobs for {$type} users from {$startYear} to {$endYear}...");
        $this->info("Chunk size: {$chunkSize} users per job");

        if ($essentialOnly) {
            $this->warn('ESSENTIAL ONLY MODE - Filtering to users missing essential profile data');
        }

        $allUsers = User::getTournamentUsers($type, $startYear, $endYear, $essentialOnly);

        if ($allUsers->isEmpty()) {
            $this->warn('No users found for the specified criteria.');
            app(AdminMaintenanceRunRecorder::class)->complete($maintenanceRunId, [
                'processed' => 0,
                'failed' => 0,
                'new_badge_users' => 0,
                'new_badges' => 0,
            ]);

            return self::SUCCESS;
        }

        $winners = $allUsers->filter(fn ($u) => $u->tournamentWinners()->exists());
        $staffAndHosts = $allUsers->filter(fn ($u) => ! $u->tournamentWinners()->exists());

        $this->info("Total users: {$allUsers->count()}");
        $this->info("  - Winners: {$winners->count()} (full profile sync)");
        $this->info("  - Staff/Hosts: {$staffAndHosts->count()} (basic info sync)");

        $handler = app(SyncUserProfilesBatchHandler::class);
        $batches = [];

        if ($winners->isNotEmpty()) {
            $batches[] = $this->dispatchUserBatch($winners, 'winners', $startYear, $endYear, $skipSip, $essentialOnly, $chunkSize, $handler, $maintenanceRunId);
        }

        if ($staffAndHosts->isNotEmpty()) {
            $batches[] = $this->dispatchUserBatch($staffAndHosts, 'staff', $startYear, $endYear, $skipSip, $essentialOnly, $chunkSize, $handler, $maintenanceRunId);
        }

        app(AdminMaintenanceRunRecorder::class)->updateSummary($maintenanceRunId, [
            'processed' => 0,
            'failed' => 0,
            'new_badge_users' => 0,
            'new_badges' => 0,
            'total_batches' => count($batches),
            'completed_batches' => 0,
        ]);

        $this->newLine();
        $this->info('Batches dispatched!');

        foreach ($batches as $index => $batch) {
            $this->info('  '.($index + 1).". Batch ID: {$batch->id}");
        }

        $this->info('Approx time: '.$this->formatApproxDuration($this->estimateQueueSeconds($winners, $staffAndHosts)));

        $this->newLine();
        $this->info('Monitor batches at: http://localhost/horizon/batches');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $winners
     * @param  Collection<int, User>  $staffAndHosts
     */
    private function estimateQueueSeconds(Collection $winners, Collection $staffAndHosts): int
    {
        $winnerApiCalls = $winners->count(); // GET /users/{id}/{main_mode}.
        $staffApiCalls = (int) ceil($staffAndHosts->count() / 50);

        return max(1, $winnerApiCalls + $staffApiCalls);
    }

    private function formatApproxDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        if ($hours === 0 && $remainingSeconds > 0) {
            $parts[] = "{$remainingSeconds}s";
        }

        return implode(' ', $parts);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function dispatchUserBatch(
        Collection $users,
        string $type,
        int $startYear,
        int $endYear,
        bool $skipSip,
        bool $essentialOnly,
        int $chunkSize,
        SyncUserProfilesBatchHandler $handler,
        ?int $maintenanceRunId = null
    ): Batch {
        $jobs = [];

        foreach ($users->chunk($chunkSize) as $offset => $chunk) {
            $jobs[] = new SyncUserProfilesJob($type, $startYear, $endYear, $skipSip, $essentialOnly, $offset * $chunkSize, $chunkSize, $maintenanceRunId);
        }

        $this->info('Dispatching '.count($jobs)." jobs for {$type}...");

        return Bus::batch($jobs)
            ->then(fn (Batch $batch) => $handler->onSuccess($batch, $type, $maintenanceRunId))
            ->catch(fn (Batch $batch, \Throwable $e) => $handler->onFailure($batch, $e, $type, $maintenanceRunId))
            ->finally(fn (Batch $batch) => Log::info('Batch finished', [
                'batch_id' => $batch->id,
                'type' => $type,
            ]))
            ->name("Sync {$type} profiles")
            ->onQueue(QueueNames::OSU_ADMIN)
            ->dispatch();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function syncBasicInfoBatch(Collection $users, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Syncing staff/hosts basic info (batch API calls)...');

        if ($dryRun) {
            foreach ($users as $user) {
                $this->info("  [DRY RUN] Would update: {$user->username} (basic info)");
            }

            return;
        }

        $synced = 0;
        $failed = 0;

        $users->chunk((int) $this->option('batch-size'))->each(function (Collection $chunk) use (&$synced, &$failed) {
            $result = $this->profileSyncService->syncBasicProfiles($chunk);
            $synced += $result['synced'];
            $failed += $result['failed'];
        });

        $this->info("Staff/hosts sync complete: {$synced} updated, {$failed} failed");
    }

    /**
     * @param  Collection<int, User>  $winners
     */
    private function processWinners(
        Collection $winners,
        int $startYear,
        int $endYear,
        bool $dryRun,
        bool $skipSip,
        ?AdminMaintenanceRun $maintenanceRun = null
    ): void {
        $this->newLine();
        $this->info('Syncing winners full profiles (individual API calls)...');

        $bar = $this->output->createProgressBar($winners->count());
        $bar->start();

        foreach ($winners as $user) {
            try {
                if (! $dryRun) {
                    $result = $this->profileSyncService->syncWinnerProfile($user, $startYear, $endYear, $skipSip, $maintenanceRun);
                    if ($result['new_badges'] > 0) {
                        $this->newBadgeUsers++;
                        $this->newBadges += $result['new_badges'];
                    }
                    $user = $user->fresh();
                }

                $this->info("Synced: {$user->username} (Mode: ".($user->main_mode ?? 'not set').')');

                if (! $dryRun && (int) $this->option('delay') > 0) {
                    sleep((int) $this->option('delay'));
                }
            } catch (\Throwable $e) {
                $this->failedUsers->push($user);
                $this->error("Failed: {$user->username} - {$e->getMessage()}");

                Log::error('Failed to sync winner profile', [
                    'user_id' => $user->id,
                    'osu_id' => $user->osu_id,
                    'exception' => $e,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
