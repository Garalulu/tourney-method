@extends('layouts.admin')

@section('title', 'Maintenance Runs')

@php
    use App\Models\AdminMaintenanceRun;
    use App\Models\AdminMaintenanceRunItem;

    $commands = [
        AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE => 'tournaments:parse',
        AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT => 'sync:tournaments',
        AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES => 'sync:user-profiles',
    ];

    $statusClasses = [
        AdminMaintenanceRun::STATUS_COMPLETED => 'bg-[var(--success)]/15 text-[var(--success)] border-[var(--success)]/40',
        AdminMaintenanceRun::STATUS_FAILED => 'bg-[var(--danger)]/15 text-[var(--danger)] border-[var(--danger)]/40',
        AdminMaintenanceRun::STATUS_RUNNING => 'bg-[var(--osu-cyan)]/15 text-[var(--osu-cyan)] border-[var(--osu-cyan)]/40',
    ];

    $countFor = fn ($run, string $key): int => (int) data_get($run->summary ?? [], $key, 0);
@endphp

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8" x-data="{ expandedRun: null }">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl" style="font-family: 'Outfit', sans-serif;">
                Maintenance Runs
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-[var(--admin-muted)]">
                Daily parser and badge sync results for tournament records, podium badge links, and user profile badge updates.
            </p>
        </div>
        <div class="text-xs text-[var(--admin-muted)]">
            Schedule timezone: Asia/Seoul
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach($commands as $command => $label)
            @php($latest = $latestRuns->get($command))
            <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate font-mono text-sm text-[var(--admin-text)]">{{ $label }}</div>
                        <div class="mt-1 text-xs text-[var(--admin-muted)]">
                            {{ $latest?->started_at?->format('M d, H:i') ?? 'No runs yet' }}
                        </div>
                    </div>
                    @if($latest)
                        <span class="shrink-0 rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClasses[$latest->status] ?? 'bg-[var(--admin-muted)]/15 text-[var(--admin-muted)] border-[var(--admin-border)]' }}">
                            {{ ucfirst($latest->status) }}
                        </span>
                    @endif
                </div>
                @if($latest)
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @if($command === AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE)
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">+{{ $countFor($latest, AdminMaintenanceRunItem::ACTION_CREATED) }} created</span>
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--osu-cyan)]">{{ $countFor($latest, AdminMaintenanceRunItem::ACTION_UPDATED) }} updated</span>
                        @elseif($command === AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT)
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">{{ $countFor($latest, AdminMaintenanceRunItem::ACTION_SYNCED) }} synced</span>
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--admin-muted)]">{{ $countFor($latest, 'unmatched') }} unmatched</span>
                        @else
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--success)]">{{ $countFor($latest, 'new_badge_users') }} users</span>
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-[var(--osu-cyan)]">{{ $countFor($latest, 'new_badges') }} badges</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if($runs->isEmpty())
        <div class="rounded-lg border border-dashed border-[var(--admin-border)] bg-[var(--admin-surface)] px-6 py-16 text-center">
            <h2 class="text-xl font-semibold">No Maintenance Runs Yet</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm text-[var(--admin-muted)]">
                Scheduled parser and sync results will appear here after `tournaments:parse`, `sync:tournament`, or `sync:user-profiles` runs.
            </p>
        </div>
    @else
        <div class="space-y-3 lg:hidden">
            @foreach($runs as $run)
                @include('admin.imports.partials.run-card', ['run' => $run, 'statusClasses' => $statusClasses])
            @endforeach
        </div>

        <div class="hidden overflow-hidden rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] lg:block">
            <table class="w-full table-fixed">
                <thead class="border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                    <tr>
                        <th class="w-[22%] px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Run</th>
                        <th class="w-[14%] px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Status</th>
                        <th class="w-[34%] px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Counts</th>
                        <th class="w-[20%] px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Duration</th>
                        <th class="w-[10%] px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--admin-border)]">
                    @foreach($runs as $run)
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                <div class="truncate font-mono text-sm">{{ $run->label }}</div>
                                <div class="mt-1 text-xs text-[var(--admin-muted)]">{{ $run->started_at?->format('M d, Y H:i') ?? 'Not started' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClasses[$run->status] ?? 'bg-[var(--admin-muted)]/15 text-[var(--admin-muted)] border-[var(--admin-border)]' }}">
                                    {{ ucfirst($run->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @include('admin.imports.partials.run-counts', ['run' => $run])
                            </td>
                            <td class="px-5 py-4 text-sm">
                                <div>{{ $run->durationForHumans() }}</div>
                                @if(count($run->errors ?? []) > 0)
                                    <div class="mt-1 text-xs text-[var(--danger)]">{{ count($run->errors ?? []) }} errors</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button"
                                        class="rounded-md border border-[var(--admin-border)] px-3 py-2 text-xs text-[var(--admin-text)] hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                                        x-on:click="expandedRun = expandedRun === {{ $run->id }} ? null : {{ $run->id }}">
                                    View
                                </button>
                            </td>
                        </tr>
                        <tr x-show="expandedRun === {{ $run->id }}" x-cloak>
                            <td colspan="5" class="bg-[var(--admin-bg)] px-5 py-4">
                                @include('admin.imports.partials.run-details', ['run' => $run])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($runs->hasPages())
            <div class="mt-6">
                {{ $runs->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
