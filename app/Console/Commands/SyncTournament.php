<?php

namespace App\Console\Commands;

use App\Jobs\SyncTournamentJob;
use App\Models\AdminMaintenanceRun;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\TournamentBadgeSyncService;
use Illuminate\Console\Command;

class SyncTournament extends Command
{
    protected $signature = 'sync:tournament
        {--start-year= : Start year for filtering tournaments by tournament_end}
        {--end-year= : End year for filtering tournaments by tournament_end}
        {--queue : Dispatch as a queued job}
        {--essential-only : Only sync tournaments with missing podium badge metadata}
        {--dry-run : Preview changes without saving}';

    protected $description = 'Sync badged tournaments from saved user badge data and auto-approve verified pending tournaments';

    public function handle(TournamentBadgeSyncService $syncService, AdminMaintenanceRunRecorder $maintenanceRuns): int
    {
        [$startYear, $endYear] = $this->resolveYearRange();
        $dryRun = (bool) $this->option('dry-run');
        $essentialOnly = (bool) $this->option('essential-only');
        $maintenanceRun = $dryRun ? null : $maintenanceRuns->start(AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT, [
            'start_year' => $startYear,
            'end_year' => $endYear,
            'essential_only' => $essentialOnly,
            'queue' => (bool) $this->option('queue'),
        ]);

        $mode = $essentialOnly ? 'essential badged tournaments' : 'approved badged tournaments';
        $this->info("Syncing {$mode} from {$startYear} to {$endYear}...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($this->option('queue')) {
            SyncTournamentJob::dispatch($startYear, $endYear, $dryRun, $essentialOnly, $maintenanceRun?->id);
            $this->info('Job dispatched successfully.');

            return self::SUCCESS;
        }

        try {
            $stats = $syncService->syncRange($startYear, $endYear, $dryRun, $essentialOnly, $maintenanceRun);
        } catch (\Throwable $e) {
            $maintenanceRuns->fail($maintenanceRun, $e->getMessage());

            throw $e;
        }

        $this->info('Tournament sync complete!');
        $this->info("  - Tournaments: {$stats['tournaments']}");
        $this->info("  - Podium winners checked: {$stats['winners']}");
        $this->info("  - Badge matches: {$stats['matched']}");
        $this->info("  - Unmatched podium winners: {$stats['unmatched']}");
        $this->info("  - Main modes updated: {$stats['main_modes_updated']}");
        $this->info("  - Stale badge links reset: {$stats['stale_badges_reset']}");
        $this->info("  - Pending tournaments auto-approved: {$stats['auto_approved']}");
        $maintenanceRuns->complete($maintenanceRun, $stats);

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveYearRange(): array
    {
        $currentYear = (int) date('Y');
        $lastYear = $currentYear - 1;

        $startYearOption = $this->option('start-year');
        $endYearOption = $this->option('end-year');

        return [
            ($startYearOption !== '' && $startYearOption !== null) ? (int) $startYearOption : $lastYear,
            ($endYearOption !== '' && $endYearOption !== null) ? (int) $endYearOption : $currentYear,
        ];
    }
}
