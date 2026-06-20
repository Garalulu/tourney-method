<?php

namespace App\Jobs;

use App\Models\AdminMaintenanceRun;
use App\Services\AdminMaintenanceRunRecorder;
use App\Services\TournamentBadgeSyncService;
use App\Support\QueueNames;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTournamentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 600;

    public function __construct(
        private int $startYear,
        private int $endYear,
        private bool $dryRun = false,
        private bool $essentialOnly = false,
        private ?int $maintenanceRunId = null,
    ) {
        $this->onQueue(QueueNames::OSU_ADMIN);
    }

    public function handle(TournamentBadgeSyncService $syncService): void
    {
        $maintenanceRun = $this->maintenanceRunId
            ? AdminMaintenanceRun::query()->find($this->maintenanceRunId)
            : null;

        $stats = $syncService->syncRange($this->startYear, $this->endYear, $this->dryRun, $this->essentialOnly, $maintenanceRun);

        app(AdminMaintenanceRunRecorder::class)->complete($maintenanceRun, $stats);

        Log::info('Tournament sync job completed', [
            'start_year' => $this->startYear,
            'end_year' => $this->endYear,
            'dry_run' => $this->dryRun,
            'essential_only' => $this->essentialOnly,
            ...$stats,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        app(AdminMaintenanceRunRecorder::class)->fail($this->maintenanceRunId, $exception->getMessage());
    }
}
