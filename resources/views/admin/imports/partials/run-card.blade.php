<div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="truncate font-mono text-sm">{{ $run->label }}</div>
            <div class="mt-1 text-xs text-[var(--admin-muted)]">{{ $run->started_at?->format('M d, Y H:i') ?? 'Not started' }}</div>
        </div>
        <span class="shrink-0 rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClasses[$run->status] ?? 'bg-[var(--admin-muted)]/15 text-[var(--admin-muted)] border-[var(--admin-border)]' }}">
            {{ ucfirst($run->status) }}
        </span>
    </div>

    <div class="mt-3">
        @include('admin.imports.partials.run-counts', ['run' => $run])
    </div>

    <div class="mt-3 flex items-center justify-between gap-3 text-xs text-[var(--admin-muted)]">
        <span>{{ $run->durationForHumans() }}</span>
        @if(count($run->errors ?? []) > 0)
            <span class="text-[var(--danger)]">{{ count($run->errors ?? []) }} errors</span>
        @endif
    </div>

    <button type="button"
            class="mt-4 w-full rounded-md border border-[var(--admin-border)] px-3 py-2 text-sm text-[var(--admin-text)] hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
            x-on:click="expandedRun = expandedRun === {{ $run->id }} ? null : {{ $run->id }}">
        Details
    </button>

    <div class="mt-4 border-t border-[var(--admin-border)] pt-4" x-show="expandedRun === {{ $run->id }}" x-cloak>
        @include('admin.imports.partials.run-details', ['run' => $run])
    </div>
</div>
