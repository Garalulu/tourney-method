<?php

namespace App\Jobs;

use App\Models\TournamentCorrection;
use App\Services\TournamentCorrectionService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTournamentCorrectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(private int $correctionId)
    {
        $this->onQueue(QueueNames::OSU_ADMIN_PRIORITY);
    }

    public function handle(TournamentCorrectionService $service): void
    {
        $correction = TournamentCorrection::query()->find($this->correctionId);

        if (! $correction || $correction->status !== TournamentCorrection::STATUS_PROCESSING) {
            return;
        }

        $service->processAcceptedParticipants($correction);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Tournament correction background processing failed', [
            'correction_id' => $this->correctionId,
            'exception' => $exception,
        ]);

        app(TournamentCorrectionService::class)->failProcessing(
            $this->correctionId,
            'Background member processing failed. Please ask an administrator to review the server log.'
        );
    }
}
