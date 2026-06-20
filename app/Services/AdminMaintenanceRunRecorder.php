<?php

namespace App\Services;

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminMaintenanceRunRecorder
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function start(string $command, array $options = [], string $source = 'cli'): AdminMaintenanceRun
    {
        return AdminMaintenanceRun::query()->create([
            'command' => $command,
            'label' => $this->labelFor($command),
            'source' => $source,
            'status' => AdminMaintenanceRun::STATUS_RUNNING,
            'options' => $options,
            'summary' => [],
            'errors' => [],
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function updateSummary(AdminMaintenanceRun|int|null $run, array $summary): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $run->update([
            'summary' => array_merge($run->summary ?? [], $summary),
        ]);
    }

    /**
     * @param  array<string, int>  $increments
     */
    public function incrementSummary(AdminMaintenanceRun|int|null $run, array $increments): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $summary = $run->summary ?? [];

        foreach ($increments as $key => $value) {
            $summary[$key] = (int) ($summary[$key] ?? 0) + $value;
        }

        $run->update(['summary' => $summary]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordTournament(
        AdminMaintenanceRun|int|null $run,
        Tournament $tournament,
        string $action,
        array $metadata = []
    ): void {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $run->items()->updateOrCreate(
            [
                'item_type' => AdminMaintenanceRunItem::TYPE_TOURNAMENT,
                'action' => $action,
                'tournament_id' => $tournament->id,
                'user_id' => $metadata['user_id'] ?? null,
            ],
            [
                'status' => AdminMaintenanceRunItem::STATUS_COMPLETED,
                'tournament_title' => $tournament->title,
                'user_id' => $metadata['user_id'] ?? null,
                'osu_id' => $metadata['osu_id'] ?? null,
                'username' => $metadata['username'] ?? null,
                'metadata' => $metadata,
            ]
        );

        $this->refreshItemSummary($run);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordUser(AdminMaintenanceRun|int|null $run, User $user, string $action, array $metadata = []): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $run->items()->updateOrCreate(
            [
                'item_type' => AdminMaintenanceRunItem::TYPE_USER,
                'action' => $action,
                'user_id' => $user->id,
            ],
            [
                'status' => AdminMaintenanceRunItem::STATUS_COMPLETED,
                'user_id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'metadata' => $metadata,
            ]
        );

        $this->refreshItemSummary($run);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordError(AdminMaintenanceRun|int|null $run, string $message, array $context = []): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $errors = $run->errors ?? [];
        $errors[] = array_merge($context, [
            'message' => $message,
            'recorded_at' => now()->toISOString(),
        ]);

        $run->update(['errors' => $errors]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function fail(AdminMaintenanceRun|int|null $run, string $message, array $context = []): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $this->recordError($run, $message, $context);
        $run->refresh();

        $run->update([
            'status' => AdminMaintenanceRun::STATUS_FAILED,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function complete(AdminMaintenanceRun|int|null $run, array $summary = []): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $this->updateSummary($run, $summary);
        $run->refresh();

        if ($run->status === AdminMaintenanceRun::STATUS_FAILED) {
            return;
        }

        $run->update([
            'status' => AdminMaintenanceRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function refreshItemSummary(AdminMaintenanceRun|int|null $run): void
    {
        $run = $this->resolveRun($run);

        if (! $run) {
            return;
        }

        $counts = AdminMaintenanceRunItem::query()
            ->select('action', DB::raw('count(*) as total'))
            ->where('admin_maintenance_run_id', $run->id)
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn (mixed $count) => (int) $count)
            ->all();

        $this->updateSummary($run, $counts);
    }

    private function resolveRun(AdminMaintenanceRun|int|null $run): ?AdminMaintenanceRun
    {
        if ($run instanceof AdminMaintenanceRun) {
            return $run;
        }

        if ($run === null) {
            return null;
        }

        return AdminMaintenanceRun::query()->find($run);
    }

    private function labelFor(string $command): string
    {
        return $command === AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT
            ? 'sync:tournaments'
            : $command;
    }
}
