@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@php
    use App\Models\AdminMaintenanceRunItem;

    $statusClass = fn (string $status): string => match ($status) {
        'approved', 'resolved', 'completed', 'partially_approved' => 'border-[var(--success)]/40 bg-[var(--success)]/15 text-[var(--success)]',
        'rejected', 'failed' => 'border-[var(--danger)]/40 bg-[var(--danger)]/15 text-[var(--danger)]',
        'processing', 'running' => 'border-[var(--osu-cyan)]/40 bg-[var(--osu-cyan)]/15 text-[var(--osu-cyan)]',
        default => 'border-amber-400/40 bg-amber-400/10 text-amber-300',
    };
@endphp

@section('content')
<div class="px-4 py-5 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 border-b border-[var(--admin-border)] pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Admin Control Center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Admin Dashboard</h1>
            <p class="mt-2 max-w-3xl text-sm text-[var(--admin-muted)]">
                Recent parser output, administrative activity, participation moderation, and corrections.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.tournaments.create') }}" class="rounded-lg bg-[var(--osu-pink)] px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                Create Tournament
            </a>
            <a href="{{ route('admin.imports.index') }}" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]">
                Maintenance Runs
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
                <div>
                    <h2 class="font-semibold">Recently Parsed Tournaments</h2>
                    <p class="mt-1 text-xs text-[var(--admin-muted)]">Created or updated by tournament parsing.</p>
                </div>
                <a href="{{ route('admin.imports.index') }}" class="text-sm font-semibold text-[var(--osu-cyan)] hover:underline">View runs</a>
            </div>
            @if($recentParsedTournaments->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">No recently parsed tournaments.</div>
            @else
                <div class="divide-y divide-[var(--admin-border)]">
                    @foreach($recentParsedTournaments as $item)
                        <div class="flex items-center justify-between gap-4 px-4 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $item->action === AdminMaintenanceRunItem::ACTION_CREATED ? 'border-[var(--success)]/40 bg-[var(--success)]/15 text-[var(--success)]' : 'border-[var(--osu-cyan)]/40 bg-[var(--osu-cyan)]/15 text-[var(--osu-cyan)]' }}">
                                        {{ ucfirst($item->action) }}
                                    </span>
                                    <span class="text-xs text-[var(--admin-muted)]">{{ $item->created_at?->format('M d, H:i') }}</span>
                                </div>
                                @if($item->tournament_id)
                                    <a href="{{ route('admin.tournaments.show', $item->tournament_id) }}" class="mt-2 block truncate font-tournament font-semibold hover:text-[var(--osu-cyan)]">
                                        {{ $item->tournament_title ?? 'Tournament #'.$item->tournament_id }}
                                    </a>
                                @else
                                    <div class="mt-2 truncate font-tournament font-semibold">{{ $item->tournament_title ?? 'Untitled tournament' }}</div>
                                @endif
                            </div>
                            @if($item->tournament_id)
                                <span class="shrink-0 font-mono text-xs text-[var(--admin-muted)]">#{{ $item->tournament_id }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
                <div>
                    <h2 class="font-semibold">Recent Audit Activity</h2>
                    <p class="mt-1 text-xs text-[var(--admin-muted)]">Latest administrative edits and decisions.</p>
                </div>
                <a href="{{ route('admin.audit-log.index') }}" class="text-sm font-semibold text-[var(--osu-cyan)] hover:underline">View all</a>
            </div>
            @if($recentAuditEntries->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">No audit activity recorded yet.</div>
            @else
                <div class="divide-y divide-[var(--admin-border)]">
                    @foreach($recentAuditEntries as $entry)
                        <div class="px-4 py-4">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-semibold">{{ $entry['admin']->username }}</span>
                                <span class="rounded bg-[var(--osu-pink)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-pink)]">{{ $entry['action_label'] }}</span>
                                <span class="text-xs text-[var(--admin-muted)]">{{ $entry['created_at']->format('M d, H:i') }}</span>
                            </div>
                            <div class="mt-2 truncate text-sm">
                                @if($entry['target']['url'])
                                    <a href="{{ $entry['target']['url'] }}" class="font-semibold text-[var(--osu-cyan)] hover:underline">{{ $entry['target']['label'] }}</a>
                                @else
                                    <span class="font-semibold">{{ $entry['target']['label'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
                <div>
                    <h2 class="font-semibold">Recent Participation Moderation</h2>
                    <p class="mt-1 text-xs text-[var(--admin-muted)]">Pending reports, podium additions, and removal requests.</p>
                </div>
                <a href="{{ route('admin.participation-moderation.index') }}" class="text-sm font-semibold text-[var(--osu-cyan)] hover:underline">Open moderation</a>
            </div>
            @if($recentParticipationModeration->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">No pending participation moderation requests.</div>
            @else
                <div class="divide-y divide-[var(--admin-border)]">
                    @foreach($recentParticipationModeration as $entry)
                        <a href="{{ route('admin.participation-moderation.index') }}" class="block px-4 py-4 transition hover:bg-[var(--admin-bg)]">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $entry['type'] }}</span>
                                <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClass($entry['status']) }}">{{ str($entry['status'])->headline() }}</span>
                                <span class="ml-auto text-xs text-[var(--admin-muted)]">{{ $entry['updated_at']?->format('M d, H:i') }}</span>
                            </div>
                            <div class="mt-2 truncate text-sm font-semibold text-[var(--admin-text)]">{{ $entry['user'] }} · {{ $entry['tournament'] }}</div>
                            <div class="mt-1 truncate text-xs text-[var(--admin-muted)]">{{ $entry['description'] }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--admin-border)] px-4 py-3">
                <div>
                    <h2 class="font-semibold">Recent Corrections</h2>
                    <p class="mt-1 text-xs text-[var(--admin-muted)]">Pending tournament corrections and new tournament requests.</p>
                </div>
                <a href="{{ route('admin.tournament-corrections.index') }}" class="text-sm font-semibold text-[var(--osu-cyan)] hover:underline">View all</a>
            </div>
            @if($recentCorrections->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">No pending tournament corrections or new tournament requests.</div>
            @else
                <div class="divide-y divide-[var(--admin-border)]">
                    @foreach($recentCorrections as $correction)
                        <a href="{{ route('admin.tournament-corrections.show', $correction) }}" class="block px-4 py-4 transition hover:bg-[var(--admin-bg)]">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClass($correction->status) }}">{{ str($correction->status)->headline() }}</span>
                                <span class="text-xs text-[var(--admin-muted)]">{{ $correction->updated_at?->format('M d, H:i') }}</span>
                            </div>
                            <div class="mt-2 truncate font-tournament font-semibold">{{ $correction->tournament?->title ?? 'New tournament request' }}</div>
                            <div class="mt-1 truncate text-xs text-[var(--admin-muted)]">Submitted by {{ $correction->submitter->username }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
