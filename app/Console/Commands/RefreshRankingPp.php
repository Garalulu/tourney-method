<?php

namespace App\Console\Commands;

use App\Services\OsuApiService;
use Illuminate\Console\Command;

class RefreshRankingPp extends Command
{
    protected $signature = 'rankings:refresh-pp {mode? : Game mode to refresh: osu, taiko, catch, mania}';

    protected $description = 'Refresh cached PP values for dashboard rank milestones';

    /**
     * @var list<string>
     */
    private array $supportedModes = ['osu', 'taiko', 'catch', 'mania'];

    public function handle(OsuApiService $osuApiService): int
    {
        $mode = $this->argument('mode');
        $modes = $mode ? [(string) $mode] : $this->supportedModes;

        foreach ($modes as $modeToRefresh) {
            if (! in_array($modeToRefresh, $this->supportedModes, true)) {
                $this->error("Unsupported mode [{$modeToRefresh}]. Supported modes: ".implode(', ', $this->supportedModes));

                return Command::INVALID;
            }
        }

        foreach ($modes as $modeToRefresh) {
            $this->info("Refreshing rank milestones for {$modeToRefresh}...");
            $osuApiService->refreshRankingPP($modeToRefresh);
        }

        $this->info('Rank milestone cache refreshed.');

        return Command::SUCCESS;
    }
}
