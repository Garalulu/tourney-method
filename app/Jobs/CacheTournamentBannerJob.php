<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Services\BannerCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CacheTournamentBannerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public Tournament $tournament
    ) {}

    public function handle(BannerCacheService $bannerCache): void
    {
        $bannerCache->cacheBanner($this->tournament);
    }
}
