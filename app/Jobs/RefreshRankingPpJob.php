<?php

namespace App\Jobs;

use App\Services\OsuApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshRankingPpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<int>  $ranks
     */
    public function __construct(
        public string $mode,
        public array $ranks = [100, 1000, 10000],
    ) {}

    public function handle(OsuApiService $osuApiService): void
    {
        $osuApiService->refreshRankingPP($this->mode, $this->ranks);
    }
}
