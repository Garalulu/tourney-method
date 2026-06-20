@extends('layouts.admin')

@section('title', 'Tournament Review Queue')

@section('content')
@php
    $hasFilters = filled($search) || ! empty($selectedModes) || $badgeOnly || $updatedOnly;
    $currentQuery = [
        'tab' => $activeTab,
        'search' => $search ?: null,
        'modes' => ! empty($selectedModes) ? $selectedModes : null,
        'badge' => $badgeOnly ? '1' : null,
        'updated' => $updatedOnly ? '1' : null,
        'sort' => $currentSort,
        'direction' => $currentDirection,
    ];
    $sortUrl = function (string $sort) use ($currentQuery, $currentSort, $currentDirection): string {
        $nextDirection = $currentSort === $sort && $currentDirection === 'asc' ? 'desc' : 'asc';

        return route('admin.tournaments.pending', array_filter(array_merge($currentQuery, [
            'sort' => $sort,
            'direction' => $nextDirection,
        ]), fn ($value) => $value !== null));
    };
    $sortIndicator = function (string $sort) use ($currentSort, $currentDirection): string {
        if ($currentSort !== $sort || $currentDirection === null) {
            return '';
        }

        return $currentDirection === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="space-y-6 p-4 sm:p-6 lg:p-8" data-active-tab="{{ $activeTab }}">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                Admin Review
            </p>
            <h1 class="mt-1 text-2xl font-bold text-[var(--admin-text)] sm:text-3xl">
                Tournament Queue
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-[var(--admin-muted)]">
                Scan submissions, review recent edits, and open tournament details for final decisions.
            </p>
        </div>

        <a href="{{ route('admin.tournaments.create') }}"
           class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--osu-pink)] px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 sm:w-auto">
            <x-icon name="lucide-plus" class="h-4 w-4" />
            Add Tournament
        </a>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach($tabs as $key => $tab)
            @php
                $tabQuery = [
                    'tab' => $key,
                    'search' => $search ?: null,
                    'modes' => ! empty($selectedModes) ? $selectedModes : null,
                    'badge' => $badgeOnly ? '1' : null,
                    'updated' => $key === 'approved' && $updatedOnly ? '1' : null,
                ];
            @endphp
            <a href="{{ route('admin.tournaments.pending', array_filter($tabQuery, fn ($value) => $value !== null)) }}"
               class="rounded-lg border p-4 transition {{ $activeTab === $key ? 'border-[var(--osu-pink)] bg-[var(--admin-surface)] shadow-lg shadow-pink-500/10' : 'border-[var(--admin-border)] bg-[var(--admin-surface)]/70 hover:border-[var(--admin-muted)]' }}"
               aria-current="{{ $activeTab === $key ? 'page' : 'false' }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold text-[var(--admin-text)]">{{ $tab['label'] }}</div>
                        <div class="mt-1 text-xs text-[var(--admin-muted)]">{{ $tab['description'] }}</div>
                    </div>
                    <span class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-1 text-lg font-bold text-[var(--admin-text)]">
                        {{ $counts[$key] ?? 0 }}
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.tournaments.pending') }}" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        @if($currentSort && $currentDirection)
            <input type="hidden" name="sort" value="{{ $currentSort }}">
            <input type="hidden" name="direction" value="{{ $currentDirection }}">
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div>
                <label for="queue-search" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                    Search
                </label>
                <div class="relative">
                    <input id="queue-search"
                           type="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="{{ __('admin.pending.search_placeholder') }}"
                           class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-4 py-3 pl-10 text-sm text-[var(--admin-text)] placeholder-[var(--admin-muted)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                    <x-icon name="lucide-search" class="absolute left-3 top-3.5 h-4 w-4 text-[var(--admin-muted)]" />
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-[var(--osu-pink)] px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                    Apply
                </button>
                @if($hasFilters)
                    <a href="{{ route('admin.tournaments.pending', ['tab' => $activeTab]) }}"
                       class="inline-flex items-center justify-center rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-5 py-3 text-sm font-semibold text-[var(--admin-text)] transition hover:border-[var(--admin-muted)]">
                        Clear
                    </a>
                @endif
            </div>
        </div>

        <fieldset class="mt-4">
            <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                Modes
            </legend>
            <div class="flex flex-wrap gap-2">
                @foreach($modeOptions as $mode => $label)
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition {{ in_array($mode, $selectedModes, true) ? 'border-[var(--osu-pink)] bg-[var(--osu-pink)]/10 text-white' : 'border-[var(--admin-border)] bg-[var(--admin-bg)] text-[var(--admin-muted)] hover:text-[var(--admin-text)]' }}">
                        <input type="checkbox"
                               name="modes[]"
                               value="{{ $mode }}"
                               @checked(in_array($mode, $selectedModes, true))
                               class="h-4 w-4 rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="mt-4">
            <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                Flags
            </legend>
            <div class="flex flex-wrap gap-2">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition {{ $badgeOnly ? 'border-[var(--osu-pink)] bg-[var(--osu-pink)]/10 text-white' : 'border-[var(--admin-border)] bg-[var(--admin-bg)] text-[var(--admin-muted)] hover:text-[var(--admin-text)]' }}">
                    <input type="checkbox"
                           name="badge"
                           value="1"
                           @checked($badgeOnly)
                           class="h-4 w-4 rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]">
                    <span>Badge</span>
                </label>
                @if($activeTab === 'approved')
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition {{ $updatedOnly ? 'border-[var(--osu-pink)] bg-[var(--osu-pink)]/10 text-white' : 'border-[var(--admin-border)] bg-[var(--admin-bg)] text-[var(--admin-muted)] hover:text-[var(--admin-text)]' }}">
                        <input type="checkbox"
                               name="updated"
                               value="1"
                               @checked($updatedOnly)
                               class="h-4 w-4 rounded border-[var(--admin-border)] bg-[var(--admin-surface)] text-[var(--osu-pink)] focus:ring-[var(--osu-pink)]">
                        <span>Updated</span>
                    </label>
                @endif
            </div>
        </fieldset>
    </form>

    @if($tournaments->isEmpty())
        <x-admin.tournaments.queue-empty
            :active-tab="$activeTab"
            :has-filters="$hasFilters"
        />
    @else
        <div class="space-y-3 lg:hidden">
            @foreach($tournaments as $tournament)
                <x-admin.tournaments.queue-item
                    :tournament="$tournament"
                    :active-tab="$activeTab"
                    layout="card"
                />
            @endforeach
        </div>

        <div class="hidden overflow-hidden rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] lg:block">
            <table class="min-w-full divide-y divide-[var(--admin-border)]">
                <thead class="bg-[var(--admin-bg)]">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Tournament</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Host</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Modes</th>
                        @if($activeTab === 'pending')
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                <a href="{{ $sortUrl('registration_start') }}" class="inline-flex items-center gap-1 transition hover:text-[var(--admin-text)]">
                                    <span>Reg Start</span>
                                    <span aria-hidden="true">{{ $sortIndicator('registration_start') }}</span>
                                </a>
                            </th>
                        @endif
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                            <a href="{{ $sortUrl('tournament_end') }}" class="inline-flex items-center gap-1 transition hover:text-[var(--admin-text)]">
                                <span>Tourney End</span>
                                <span aria-hidden="true">{{ $sortIndicator('tournament_end') }}</span>
                            </a>
                        </th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                            <a href="{{ $sortUrl('updated_at') }}" class="inline-flex items-center gap-1 transition hover:text-[var(--admin-text)]">
                                <span>Last Edited</span>
                                <span aria-hidden="true">{{ $sortIndicator('updated_at') }}</span>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--admin-border)]">
                    @foreach($tournaments as $tournament)
                        <x-admin.tournaments.queue-item
                            :tournament="$tournament"
                            :active-tab="$activeTab"
                            layout="row"
                        />
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($tournaments->hasPages())
            <div class="pt-2">
                {{ $tournaments->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
