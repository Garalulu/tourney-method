<?php

namespace App\Console\Commands;

use App\Models\TournamentParseHistory;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PruneDatabaseStorage extends Command
{
    private const PARSE_HISTORY_RETENTION_DAYS = 30;

    private const PARSE_HISTORY_KEEP_PER_TOURNAMENT = 5;

    private const JOB_BATCH_RETENTION_DAYS = 14;

    private const AUDIT_LOG_RETENTION_DAYS = 180;

    protected $signature = 'maintenance:database-prune
        {--dry-run : Report what would be pruned or compacted without changing data}
        {--force : Apply pruning and compaction}
        {--reclaim : Run VACUUM FULL on reclaimed tables after pruning; requires --force}';

    protected $description = 'Prune and compact database storage-heavy maintenance data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $reclaim = (bool) $this->option('reclaim');

        if ($dryRun === $force) {
            $this->error('Specify exactly one of --dry-run or --force.');

            return self::FAILURE;
        }

        if ($reclaim && ! $force) {
            $this->error('--reclaim can only be used together with --force.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays(self::PARSE_HISTORY_RETENTION_DAYS);
        $deleteIds = $this->parseHistoryDeleteIds($cutoff);
        $compactIds = $this->parseHistoryCompactIds($cutoff);
        $jobBatchCount = $this->completedJobBatchesOlderThan(self::JOB_BATCH_RETENTION_DAYS)->count();
        $oldAuditLogCount = DB::table('admin_audit_logs')
            ->where('created_at', '<', now()->subDays(self::AUDIT_LOG_RETENTION_DAYS))
            ->count();

        $this->info('Database prune summary');
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'force'));
        $this->line('Parse histories to delete: '.$deleteIds->count());
        $this->line('Old kept parse histories to compact: '.$compactIds->count());
        $this->line('Completed job batches to prune: '.$jobBatchCount);
        $this->line('Admin audit logs older than '.self::AUDIT_LOG_RETENTION_DAYS.' days retained for now: '.$oldAuditLogCount);

        if ($dryRun) {
            $this->warn('No data changed.');

            return self::SUCCESS;
        }

        $deletedHistories = $deleteIds->isEmpty()
            ? 0
            : TournamentParseHistory::query()->whereIn('id', $deleteIds)->delete();

        $compactedHistories = $this->compactParseHistories($compactIds);

        Artisan::call('queue:prune-batches', [
            '--hours' => self::JOB_BATCH_RETENTION_DAYS * 24,
        ]);

        $this->info("Deleted parse histories: {$deletedHistories}");
        $this->info("Compacted parse histories: {$compactedHistories}");
        $this->line(trim(Artisan::output()));

        if ($reclaim) {
            $this->runPhysicalReclaim();
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, int>
     */
    private function parseHistoryDeleteIds(CarbonInterface $cutoff): Collection
    {
        $ranked = TournamentParseHistory::query()
            ->select('id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY tournament_id ORDER BY COALESCE(parsed_at, created_at) DESC, id DESC) as parse_rank');

        return DB::query()
            ->fromSub($ranked, 'ranked_histories')
            ->join('tournament_parse_histories', 'tournament_parse_histories.id', '=', 'ranked_histories.id')
            ->where('ranked_histories.parse_rank', '>', self::PARSE_HISTORY_KEEP_PER_TOURNAMENT)
            ->where('tournament_parse_histories.created_at', '<', $cutoff)
            ->pluck('tournament_parse_histories.id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @return Collection<int, int>
     */
    private function parseHistoryCompactIds(CarbonInterface $cutoff): Collection
    {
        $ranked = TournamentParseHistory::query()
            ->select('id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY tournament_id ORDER BY COALESCE(parsed_at, created_at) DESC, id DESC) as parse_rank');

        return DB::query()
            ->fromSub($ranked, 'ranked_histories')
            ->join('tournament_parse_histories', 'tournament_parse_histories.id', '=', 'ranked_histories.id')
            ->where('ranked_histories.parse_rank', '<=', self::PARSE_HISTORY_KEEP_PER_TOURNAMENT)
            ->where('tournament_parse_histories.created_at', '<', $cutoff)
            ->whereNull('tournament_parse_histories.compacted_at')
            ->pluck('tournament_parse_histories.id')
            ->map(fn ($id) => (int) $id);
    }

    private function completedJobBatchesOlderThan(int $days): Builder
    {
        return DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', now()->subDays($days)->getTimestamp());
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function compactParseHistories(Collection $ids): int
    {
        $compacted = 0;

        foreach ($ids->sort()->values() as $id) {
            $history = TournamentParseHistory::find($id);
            if (! $history instanceof TournamentParseHistory) {
                continue;
            }

            $history->forceFill([
                'changes' => $this->compactChanges($history->changes ?? []),
                'parsed_data' => $this->compactParsedData($history->parsed_data),
                'compacted_at' => now(),
            ])->save();

            $compacted++;
        }

        return $compacted;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function compactChanges(array $changes): array
    {
        foreach ($changes as $field => $change) {
            if (! is_array($change)) {
                continue;
            }

            unset($change['diff_html']);

            if ($field === 'description') {
                $change = $this->compactDescriptionChange($change);
            }

            $changes[$field] = $change;
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function compactDescriptionChange(array $change): array
    {
        foreach (['old', 'new'] as $key) {
            if (! array_key_exists($key, $change)) {
                continue;
            }

            $value = $change[$key];
            $stringValue = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES);

            $change["{$key}_sha256"] = hash('sha256', $stringValue ?: '');
            $change["{$key}_bytes"] = strlen($stringValue ?: '');
            $change["{$key}_preview"] = Str::limit($stringValue ?: '', 240);
            unset($change[$key]);
        }

        return $change;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function compactParsedData(mixed $parsedData): ?array
    {
        if ($parsedData === null) {
            return null;
        }

        $encoded = json_encode($parsedData, JSON_UNESCAPED_SLASHES);

        return [
            'compacted' => true,
            'original_sha256' => hash('sha256', $encoded ?: ''),
            'original_bytes' => strlen($encoded ?: ''),
            'top_level_keys' => is_array($parsedData) ? array_keys($parsedData) : [],
        ];
    }

    private function runPhysicalReclaim(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->warn('Skipping physical reclaim because VACUUM FULL is only supported here for PostgreSQL.');

            return;
        }

        $this->warn('Running VACUUM (FULL, ANALYZE) on tournament_parse_histories and job_batches.');
        DB::statement('VACUUM (FULL, ANALYZE) tournament_parse_histories');
        DB::statement('VACUUM (FULL, ANALYZE) job_batches');
        $this->info('Physical reclaim completed.');
    }
}
