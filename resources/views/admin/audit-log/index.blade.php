@extends('layouts.admin')

@php
    use App\Support\AdminAuditLogPresenter;
@endphp

@section('title', 'Audit Log')

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 space-y-5">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Audit Log</h1>
            <p class="mt-1 text-sm text-[var(--admin-muted)]">
                Review administrative changes across tournaments, staff, podiums, users, and matches.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.audit-log.index') }}" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.2fr_1fr_1fr_1fr_auto]">
                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Tournament</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search title or id"
                        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                    >
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Action</span>
                    <select
                        name="action"
                        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                    >
                        <option value="">All Actions</option>
                        @foreach($actionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Entity</span>
                    <select
                        name="entity_type"
                        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                    >
                        <option value="">All Entities</option>
                        @foreach($entityOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('entity_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Admin</span>
                    <select
                        name="admin_id"
                        class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"
                    >
                        <option value="">All Admins</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((string) request('admin_id') === (string) $admin->id)>{{ $admin->username }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                        Filter
                    </button>
                    <a href="{{ route('admin.audit-log.index') }}" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold text-[var(--admin-text)] transition hover:bg-[var(--admin-bg)]">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    @if($logs->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] py-20 text-center">
            <x-icon name="lucide-file-text" class="mb-5 h-12 w-12 text-[var(--admin-muted)]" />
            <h2 class="text-xl font-bold">No Audit Logs Found</h2>
            <p class="mt-2 max-w-md text-sm text-[var(--admin-muted)]">
                No audit logs match the current filter criteria.
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($presentedLogs as $entry)
                @php
                    $hasExpandableDetails = $entry['changes']
                        || $entry['bulk_items']['successes']
                        || $entry['bulk_items']['failures']
                        || $entry['detail_rows'];
                @endphp

                <article
                    x-data="{ expanded: false }"
                    class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 transition hover:border-[var(--osu-cyan)]/70"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 flex-1 gap-3">
                            <img
                                src="{{ $entry['admin']->avatar_url }}"
                                alt="{{ $entry['admin']->username }}"
                                class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]"
                            >

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-[var(--admin-text)]">{{ $entry['admin']->username }}</span>
                                    <span class="text-sm text-[var(--admin-muted)]">performed</span>
                                    <span class="rounded bg-[var(--osu-pink)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-pink)]">
                                        {{ $entry['action_label'] }}
                                    </span>
                                    <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-xs text-[var(--admin-muted)]">
                                        {{ $entry['entity_label'] }}
                                    </span>
                                    @if($entry['changes'])
                                        <span class="rounded border border-[var(--osu-cyan)]/30 bg-[var(--osu-cyan)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-cyan)]">
                                            {{ count($entry['changes']) }} {{ count($entry['changes']) === 1 ? 'field' : 'fields' }}
                                        </span>
                                    @endif
                                    @if($entry['bulk_items']['successes'] || $entry['bulk_items']['failures'])
                                        <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs text-[var(--admin-muted)]">
                                            {{ $entry['bulk_items']['success_count'] ?? count($entry['bulk_items']['successes']) }} ok /
                                            {{ $entry['bulk_items']['failed_count'] ?? count($entry['bulk_items']['failures']) }} failed
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <span class="text-[var(--admin-muted)]">Target:</span>
                                        @if($entry['target']['url'])
                                            <a href="{{ $entry['target']['url'] }}" class="font-semibold text-[var(--osu-cyan)] hover:underline">
                                                {{ $entry['target']['label'] }}
                                            </a>
                                        @else
                                            <span class="font-semibold {{ $entry['target']['missing'] ? 'text-[var(--danger)]' : 'text-[var(--admin-text)]' }}">
                                                {{ $entry['target']['label'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($hasExpandableDetails)
                                        <button
                                            type="button"
                                            class="inline-flex w-fit items-center gap-1 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2.5 py-1 text-xs font-semibold text-[var(--admin-text)] transition hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]"
                                            @click="expanded = !expanded"
                                            :aria-expanded="expanded.toString()"
                                        >
                                            <span x-text="expanded ? 'Hide details' : 'Show details'"></span>
                                            <x-icon name="lucide-chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="{ 'rotate-180': expanded }" />
                                        </button>
                                    @endif
                                </div>

                                @if($hasExpandableDetails)
                                    <div
                                        x-show="expanded"
                                        x-transition
                                        class="mt-4 space-y-3"
                                        style="display: none;"
                                    >
                                        @if($entry['changes'])
                                            <div class="w-full max-w-5xl rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                                <div class="space-y-2">
                                                    @foreach($entry['changes'] as $change)
                                                        @php
                                                            $beforeValue = AdminAuditLogPresenter::formatValue($change['before']);
                                                            $afterValue = AdminAuditLogPresenter::formatValue($change['after']);
                                                        @endphp

                                                        <div class="rounded border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3 text-sm">
                                                            <div class="mb-3 font-semibold text-[var(--admin-text)]">
                                                                {{ AdminAuditLogPresenter::fieldLabel($change['field']) }}
                                                            </div>

                                                            <div class="grid gap-2 md:grid-cols-2">
                                                                <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">Before</div>
                                                                    <div class="max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm text-[var(--admin-muted)]">{{ $beforeValue }}</div>
                                                                </div>
                                                                <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">After</div>
                                                                    <div class="max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm font-medium text-[var(--admin-text)]">{{ $afterValue }}</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if($entry['bulk_items']['successes'] || $entry['bulk_items']['failures'])
                                            <div class="max-w-4xl rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                                <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide">
                                                    <span class="rounded bg-[var(--success)]/10 px-2 py-1 text-[var(--success)]">
                                                        {{ $entry['bulk_items']['success_count'] ?? count($entry['bulk_items']['successes']) }} Added
                                                    </span>
                                                    <span class="rounded bg-[var(--danger)]/10 px-2 py-1 text-[var(--danger)]">
                                                        {{ $entry['bulk_items']['failed_count'] ?? count($entry['bulk_items']['failures']) }} Failed
                                                    </span>
                                                    @if($entry['bulk_items']['total'] !== null)
                                                        <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-[var(--admin-muted)]">
                                                            {{ $entry['bulk_items']['total'] }} Requested
                                                        </span>
                                                    @endif
                                                </div>

                                                @if($entry['bulk_items']['successes'])
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach($entry['bulk_items']['successes'] as $item)
                                                            <span class="rounded border border-[var(--success)]/30 bg-[var(--success)]/10 px-2 py-1 text-xs text-[var(--admin-text)]">
                                                                {{ $item['username'] ?? 'Unknown user' }}
                                                                @if(isset($item['role']))
                                                                    <span class="text-[var(--admin-muted)]">as {{ $item['role'] }}</span>
                                                                @endif
                                                                @if(isset($item['placement']))
                                                                    <span class="text-[var(--admin-muted)]">place {{ $item['placement'] }}</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if($entry['bulk_items']['failures'])
                                                    <div class="mt-3 space-y-2">
                                                        @foreach($entry['bulk_items']['failures'] as $item)
                                                            <div class="rounded border border-[var(--danger)]/30 bg-[var(--danger)]/10 px-2 py-1 text-xs">
                                                                <span class="font-semibold text-[var(--danger)]">{{ $item['username'] ?? 'Unknown user' }}</span>
                                                                <span class="text-[var(--admin-muted)]">{{ $item['message'] ?? 'Failed' }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        @if($entry['detail_rows'])
                                            <div class="flex max-w-4xl flex-wrap gap-2">
                                                @foreach($entry['detail_rows'] as $row)
                                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs">
                                                        <span class="text-[var(--admin-muted)]">{{ $row['label'] }}:</span>
                                                        <span class="text-[var(--admin-text)]">{{ $row['value'] }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 text-left text-xs text-[var(--admin-muted)] lg:text-right">
                            <div>{{ $entry['created_at']->format('M d, Y') }}</div>
                            <div>{{ $entry['created_at']->format('H:i:s') }}</div>
                            <div class="mt-1">{{ $entry['created_at']->diffForHumans() }}</div>
                            @if($entry['ip_address'])
                                <div class="mt-2 font-mono">{{ $entry['ip_address'] }}</div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($logs->hasPages())
            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
