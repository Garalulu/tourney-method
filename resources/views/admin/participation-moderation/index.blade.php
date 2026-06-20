@extends('layouts.admin')

@section('title', __('admin.participation.title'))

@php
    $formatValue = function ($value): string {
        if ($value === null || $value === []) {
            return 'None';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    };

    $fieldLabel = fn (string $field): string => str($field)->replace('_', ' ')->headline()->toString();
    $archivedUserName = fn ($user, ?int $fallbackId = null): string => $user?->username ?? ($fallbackId ? "Archived user #{$fallbackId}" : 'Archived user');
    $userAvatar = fn ($user): string => $user?->avatar_url ?? ($user?->osu_id ? 'https://a.ppy.sh/'.$user->osu_id : 'https://a.ppy.sh/');
    $renderUser = function ($user, ?int $fallbackId = null, string $class = 'font-semibold text-[var(--osu-cyan)] hover:underline'): \Illuminate\Support\HtmlString {
        $name = e($user?->username ?? ($fallbackId ? "Archived user #{$fallbackId}" : 'Archived user'));

        if ($user && ! $user->trashed()) {
            return new \Illuminate\Support\HtmlString('<a href="'.e(route('users.show', $user)).'" class="'.e($class).'">'.$name.'</a>');
        }

        return new \Illuminate\Support\HtmlString('<span class="'.e($class).' text-[var(--admin-muted)]" title="Archived or missing user">'.$name.'</span>');
    };
@endphp

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 space-y-5">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">{{ __('admin.participation.title') }}</h1>
            <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.participation.description') }}</p>
        </div>

        <form method="GET" action="{{ route('admin.participation-moderation.index') }}" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto]">
                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.filters.search') }}</span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.participation.filters.search_placeholder') }}" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.filters.action') }}</span>
                    <select name="action" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                        <option value="">{{ __('admin.participation.filters.all_actions') }}</option>
                        @foreach($actionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.filters.entity') }}</span>
                    <select name="entity_type" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                        <option value="">{{ __('admin.participation.filters.all_entities') }}</option>
                        @foreach($entityOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('entity_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.filters.flagged') }}</span>
                    <select name="flagged" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                        <option value="">{{ __('admin.participation.filters.all_logs') }}</option>
                        <option value="1" @selected(request()->boolean('flagged'))>{{ __('admin.participation.filters.flagged_only') }}</option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.filters.deletion_user') }}</span>
                    <input type="search" name="deletion_user" value="{{ request('deletion_user') }}" placeholder="{{ __('admin.participation.filters.username_placeholder') }}" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]">
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                        {{ __('admin.participation.actions.filter') }}
                    </button>
                    <a href="{{ route('admin.participation-moderation.index') }}" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold text-[var(--admin-text)] transition hover:bg-[var(--admin-bg)]">
                        {{ __('admin.participation.actions.clear') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    @if($locks->isNotEmpty())
        <section class="mb-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
            <div class="border-b border-[var(--admin-border)] px-4 py-3">
                <h2 class="font-semibold">{{ __('admin.participation.locks_title') }}</h2>
            </div>
            <div class="grid gap-3 p-4 md:grid-cols-2">
                @foreach($locks as $lock)
                    <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 gap-3">
                                <img src="{{ $userAvatar($lock->user) }}" alt="{{ $archivedUserName($lock->user, $lock->user_id) }}" class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]">
                                <div class="min-w-0">
                                    {!! $renderUser($lock->user, $lock->user_id) !!}
                                    <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ $lock->reason ?: __('admin.participation.no_reason') }}</p>
                                </div>
                            </div>
                            @if($lock->user && ! $lock->user->trashed())
                                <form method="POST" action="{{ route('admin.participation-moderation.unlock', $lock->user) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-[var(--admin-border)] px-3 py-1.5 text-sm font-semibold hover:bg-[var(--admin-surface)]">
                                        {{ __('admin.participation.actions.unlock') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]" data-reports-section>
        <div class="border-b border-[var(--admin-border)] px-4 py-3">
            <h2 class="font-semibold">{{ __('admin.participation.reports_title') }}</h2>
            <p class="mt-1 text-xs text-[var(--admin-muted)]">{{ __('admin.participation.reports_desc') }}</p>
        </div>

        @forelse($reports as $report)
            @php
                $record = $report->record;
                $categoryLabels = \App\Models\ParticipationRecordReport::categoryLabels();
                $placement = $record?->placementRangeLabel();
                $stage = $record?->result_summary;
                $lockRecommended = in_array($report->category, [
                    \App\Models\ParticipationRecordReport::CATEGORY_REPORT_SPAM,
                    \App\Models\ParticipationRecordReport::CATEGORY_INAPPROPRIATE_MEMO,
                ], true);
            @endphp
            <article class="border-b border-[var(--admin-border)] p-4 last:border-b-0" data-report-card x-data="{ expanded: false, resolveModal: false, lockUser: @js($lockRecommended) }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 gap-3">
                        <img src="{{ $userAvatar($report->reporter) }}" alt="{{ $archivedUserName($report->reporter, $report->reported_by) }}" class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                {!! $renderUser($report->reporter, $report->reported_by) !!}
                                <span class="text-sm text-[var(--admin-muted)]">{{ __('admin.participation.labels.reported') }}</span>
                                @if($record?->user)
                                    {!! $renderUser($record->user, $record->user_id, 'font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]') !!}
                                @endif
                                <span class="rounded bg-[var(--osu-pink)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-pink)]">{{ $categoryLabels[$report->category] ?? $report->category }}</span>
                                @if($placement)
                                    <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-xs text-[var(--admin-muted)]">{{ $placement }}</span>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2 text-sm text-[var(--admin-muted)]">
                                <span>{{ $report->created_at->format('M d, Y H:i') }}</span>
                                @if($record?->tournament)
                                    <span>/</span>
                                    <a href="{{ route('tournaments.show', $record->tournament) }}" class="font-tournament font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $record->tournament->title }}</a>
                                    <span class="font-mono">#{{ $record->tournament->id }}</span>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                @if($record?->team_name)
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.team_name') }}:</span>
                                        <span>{{ $record->team_name }}</span>
                                    </span>
                                @endif
                                @if($stage)
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.finished_stage') }}:</span>
                                        <span>{{ $stage }}</span>
                                    </span>
                                @endif
                                @if($record?->teammates->isNotEmpty())
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.labels.teammates') }}:</span>
                                        <span>{{ $record->teammates->pluck('username')->join(', ') }}</span>
                                    </span>
                                @endif
                            </div>

                            <button type="button" class="mt-3 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2.5 py-1 text-xs font-semibold hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]" @click="expanded = ! expanded">
                                <span x-text="expanded ? '{{ __('admin.participation.actions.hide_details') }}' : '{{ __('admin.participation.actions.show_details') }}'"></span>
                            </button>

                            <div x-show="expanded" x-transition class="mt-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 text-sm" style="display: none;">
                                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.explanation') }}</div>
                                <p class="mt-2 whitespace-pre-line text-[var(--admin-text)]">{{ $report->explanation ?: __('admin.participation.no_reason') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-w-64 flex-wrap gap-2 lg:justify-end">
                        <a href="{{ $record?->user ? route('users.show', ['user' => $record->user, 'tab' => 'participation', 'participation_tournament' => $record->tournament_id]) : '#' }}" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">
                            {{ __('admin.participation.actions.open_profile') }}
                        </a>
                        <button type="button" @click="resolveModal = true" class="rounded-lg border border-green-400/50 px-3 py-2 text-sm font-semibold text-green-300 hover:bg-green-500/10">
                            {{ __('admin.participation.actions.resolve_report') }}
                        </button>
                    </div>
                </div>

                <div x-show="resolveModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
                    <div class="w-full max-w-md rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-xl" @click.outside="resolveModal = false">
                        <h3 class="text-lg font-semibold">{{ __('admin.participation.dialogs.resolve_report_title') }}</h3>
                        <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.participation.dialogs.resolve_report_help') }}</p>
                        <form method="POST" action="{{ route('admin.participation-moderation.reports.resolve', $report) }}" class="js-report-resolve-form mt-4 space-y-4">
                            @csrf
                            <label class="flex items-center gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm">
                                <input type="checkbox" name="lock_user" value="1" x-model="lockUser" class="rounded border-[var(--admin-border)] bg-[var(--admin-surface)]">
                                <span>{{ __('admin.participation.fields.lock_reported_user') }}</span>
                            </label>
                            <label class="block space-y-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.resolution_note') }}</span>
                                <textarea name="resolution_note" rows="4" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="resolveModal = false" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.participation.actions.cancel') }}</button>
                                <button class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('admin.participation.actions.confirm') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]" data-reports-empty>{{ __('admin.participation.empty_reports') }}</div>
        @endforelse
        @if($reports->isNotEmpty())
            <div class="hidden px-4 py-10 text-center text-sm text-[var(--admin-muted)]" data-reports-empty>{{ __('admin.participation.empty_reports') }}</div>
        @endif
    </section>

    <section class="mb-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
        <div class="border-b border-[var(--admin-border)] px-4 py-3">
            <h2 class="font-semibold">{{ __('admin.participation.add_requests_title') }}</h2>
            <p class="mt-1 text-xs text-[var(--admin-muted)]">{{ __('admin.participation.add_requests_desc') }}</p>
        </div>

        @forelse($addRequests as $addRequest)
            @php
                $placement = $addRequest->placementRangeLabel();
                $stage = $addRequest->result_summary;
            @endphp
            <article class="border-b border-[var(--admin-border)] p-4 last:border-b-0" x-data="{ expanded: false, reviewModal: null }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 gap-3">
                        <img src="{{ $userAvatar($addRequest->user) }}" alt="{{ $archivedUserName($addRequest->user, $addRequest->user_id) }}" class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                {!! $renderUser($addRequest->user, $addRequest->user_id) !!}
                                <span class="text-sm text-[var(--admin-muted)]">{{ __('admin.participation.labels.requested_add') }}</span>
                                <span class="rounded bg-[var(--osu-pink)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-pink)]">{{ ucfirst($addRequest->review_status) }}</span>
                                @if($placement)
                                    <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-xs text-[var(--admin-muted)]">{{ $placement }}</span>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2 text-sm text-[var(--admin-muted)]">
                                <span>{{ $addRequest->created_at->format('M d, Y H:i') }}</span>
                                @if($addRequest->tournament)
                                    <span>/</span>
                                    <a href="{{ route('tournaments.show', $addRequest->tournament) }}" class="font-tournament font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $addRequest->tournament->title }}</a>
                                    <span class="font-mono">#{{ $addRequest->tournament->id }}</span>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                @if($addRequest->team_name)
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.team_name') }}:</span>
                                        <span>{{ $addRequest->team_name }}</span>
                                    </span>
                                @endif
                                @if($stage)
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.finished_stage') }}:</span>
                                        <span>{{ $stage }}</span>
                                    </span>
                                @endif
                                @if($addRequest->teammates->isNotEmpty())
                                    <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                        <span class="text-[var(--admin-muted)]">{{ __('admin.participation.labels.teammates') }}:</span>
                                        <span>{{ $addRequest->teammates->pluck('username')->join(', ') }}</span>
                                    </span>
                                @endif
                            </div>

                            <button type="button" class="mt-3 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2.5 py-1 text-xs font-semibold hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]" @click="expanded = ! expanded">
                                <span x-text="expanded ? '{{ __('admin.participation.actions.hide_details') }}' : '{{ __('admin.participation.actions.show_details') }}'"></span>
                            </button>

                            <div x-show="expanded" x-transition class="mt-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 text-sm" style="display: none;">
                                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.review_note') }}</div>
                                <p class="mt-2 whitespace-pre-line text-[var(--admin-text)]">{{ $addRequest->memo ?: __('admin.participation.no_reason') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-w-64 flex-wrap gap-2 lg:justify-end">
                        <button type="button" @click="reviewModal = 'approve'" class="rounded-lg border border-green-400/50 px-3 py-2 text-sm font-semibold text-green-300 hover:bg-green-500/10">
                            {{ __('admin.participation.actions.approve_add') }}
                        </button>
                        <button type="button" @click="reviewModal = 'reject'" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">
                            {{ __('admin.participation.actions.reject_add') }}
                        </button>
                    </div>
                </div>

                <div x-show="reviewModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
                    <div class="w-full max-w-md rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-xl" @click.outside="reviewModal = null">
                        <h3 class="text-lg font-semibold" x-text="reviewModal === 'approve' ? '{{ __('admin.participation.dialogs.approve_add_title') }}' : '{{ __('admin.participation.dialogs.reject_add_title') }}'"></h3>
                        <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.participation.dialogs.review_note_help') }}</p>
                        <form method="POST" :action="reviewModal === 'approve' ? '{{ route('admin.participation-moderation.add-requests.approve', $addRequest) }}' : '{{ route('admin.participation-moderation.add-requests.reject', $addRequest) }}'" class="mt-4 space-y-4">
                            @csrf
                            <label class="block space-y-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.review_note') }}</span>
                                <textarea name="review_note" rows="4" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="reviewModal = null" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.participation.actions.cancel') }}</button>
                                <button class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('admin.participation.actions.confirm') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">{{ __('admin.participation.empty_add_requests') }}</div>
        @endforelse
    </section>

    <section class="mb-6 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
        <div class="border-b border-[var(--admin-border)] px-4 py-3">
            <h2 class="font-semibold">{{ __('admin.participation.deletion_requests_title') }}</h2>
            <p class="mt-1 text-xs text-[var(--admin-muted)]">{{ __('admin.participation.deletion_requests_desc') }}</p>
        </div>

        @forelse($deletionRequests as $deletionRequest)
            @php
                $record = $deletionRequest->record;
                $placement = $record?->placementRangeLabel();
                $stage = $record?->result_summary;
            @endphp
            <article class="border-b border-[var(--admin-border)] p-4 last:border-b-0" x-data="{ expanded: false, reviewModal: null }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 gap-3">
                        <img src="{{ $userAvatar($deletionRequest->requester) }}" alt="{{ $archivedUserName($deletionRequest->requester, $deletionRequest->requested_by) }}" class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]">
                        <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            {!! $renderUser($deletionRequest->requester, $deletionRequest->requested_by) !!}
                            <span class="text-sm text-[var(--admin-muted)]">{{ __('admin.participation.labels.requested_delete') }}</span>
                            <span class="rounded bg-[var(--danger)]/10 px-2 py-1 text-xs font-semibold text-[var(--danger)]">{{ ucfirst($deletionRequest->status) }}</span>
                            @if($placement)
                                <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-xs text-[var(--admin-muted)]">{{ $placement }}</span>
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-[var(--admin-muted)]">
                            <span>{{ $deletionRequest->created_at->format('M d, Y H:i') }}</span>
                            @if($record?->tournament)
                                <span>/</span>
                                <a href="{{ route('tournaments.show', $record->tournament) }}" class="font-tournament font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $record->tournament->title }}</a>
                                <span class="font-mono">#{{ $record->tournament->id }}</span>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if($record?->team_name)
                                <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                    <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.team_name') }}:</span>
                                    <span>{{ $record->team_name }}</span>
                                </span>
                            @endif
                            @if($stage)
                                <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                    <span class="text-[var(--admin-muted)]">{{ __('admin.participation.fields.finished_stage') }}:</span>
                                    <span>{{ $stage }}</span>
                                </span>
                            @endif
                            @if($record?->teammates->isNotEmpty())
                                <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1">
                                    <span class="text-[var(--admin-muted)]">{{ __('admin.participation.labels.teammates') }}:</span>
                                    <span>{{ $record->teammates->pluck('username')->join(', ') }}</span>
                                </span>
                            @endif
                        </div>

                        <button type="button" class="mt-3 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2.5 py-1 text-xs font-semibold hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]" @click="expanded = ! expanded">
                            <span x-text="expanded ? '{{ __('admin.participation.actions.hide_details') }}' : '{{ __('admin.participation.actions.show_details') }}'"></span>
                        </button>

                        <div x-show="expanded" x-transition class="mt-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 text-sm" style="display: none;">
                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.reason') }}</div>
                            <p class="mt-2 whitespace-pre-line text-[var(--admin-text)]">{{ $deletionRequest->reason ?: __('admin.participation.no_reason') }}</p>
                        </div>
                        </div>
                    </div>

                    <div class="flex min-w-64 flex-wrap gap-2 lg:justify-end">
                        <button type="button" @click="reviewModal = 'approve'" class="rounded-lg border border-red-400/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10">
                            {{ __('admin.participation.actions.approve_delete') }}
                        </button>
                        <button type="button" @click="reviewModal = 'reject'" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">
                            {{ __('admin.participation.actions.reject_delete') }}
                        </button>
                    </div>
                </div>

                <div x-show="reviewModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
                    <div class="w-full max-w-md rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-xl" @click.outside="reviewModal = null">
                        <h3 class="text-lg font-semibold" x-text="reviewModal === 'approve' ? '{{ __('admin.participation.dialogs.approve_delete_title') }}' : '{{ __('admin.participation.dialogs.reject_delete_title') }}'"></h3>
                        <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.participation.dialogs.review_note_help') }}</p>
                        <form method="POST" :action="reviewModal === 'approve' ? '{{ route('admin.participation-moderation.deletion-requests.approve', $deletionRequest) }}' : '{{ route('admin.participation-moderation.deletion-requests.reject', $deletionRequest) }}'" class="mt-4 space-y-4">
                            @csrf
                            <label class="block space-y-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.review_note') }}</span>
                                <textarea name="review_note" rows="4" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="reviewModal = null" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.participation.actions.cancel') }}</button>
                                <button class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('admin.participation.actions.confirm') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">{{ __('admin.participation.empty_deletion_requests') }}</div>
        @endforelse
    </section>

    <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
        <div class="border-b border-[var(--admin-border)] px-4 py-3">
            <h2 class="font-semibold">{{ __('admin.participation.logs_title') }}</h2>
            <p class="mt-1 text-xs text-[var(--admin-muted)]">{{ __('admin.participation.logs_desc') }}</p>
        </div>

        @forelse($logs as $log)
            @php
                $changedFields = $log->changed_fields ?? [];
                $canRollbackTeammates = $log->record && is_array(data_get($changedFields, 'changes.teammates'));
                $changeRows = collect(data_get($changedFields, 'changes', []))
                    ->map(fn ($change, $field) => [
                        'field' => (string) $field,
                        'before' => is_array($change) ? ($change['old'] ?? null) : null,
                        'after' => is_array($change) ? ($change['new'] ?? $change) : $change,
                        'has_before' => true,
                    ]);

                if ($changeRows->isEmpty()) {
                    $changeRows = collect($changedFields)
                        ->reject(fn ($value, $field) => $field === 'changes')
                        ->map(fn ($value, $field) => [
                            'field' => (string) $field,
                            'before' => null,
                            'after' => $value,
                            'has_before' => false,
                        ]);
                }
                $performer = $log->actor ?? $log->user;
            @endphp
            <article class="border-b border-[var(--admin-border)] p-4 last:border-b-0 transition hover:border-[var(--osu-cyan)]/70" x-data="{ expanded: false, actionModal: null }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 gap-3">
                        <img src="{{ $userAvatar($performer) }}" alt="{{ $archivedUserName($performer, $log->actor_id ?? $log->user_id) }}" class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]">
                        <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            {!! $renderUser($performer, $log->actor_id ?? $log->user_id) !!}
                            <span class="text-sm text-[var(--admin-muted)]">{{ __('admin.participation.labels.performed') }}</span>
                            <span class="rounded bg-[var(--osu-pink)]/10 px-2 py-1 text-xs font-semibold text-[var(--osu-pink)]">{{ str($log->action)->replace('_', ' ')->headline() }}</span>
                            @if($log->actor && $log->actor_id !== $log->user_id)
                                <span class="text-sm text-[var(--admin-muted)]">for</span>
                                {!! $renderUser($log->user, $log->user_id, 'font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]') !!}
                            @endif
                            <span class="rounded border border-[var(--admin-border)] px-2 py-1 text-xs text-[var(--admin-muted)]">
                                {{ $log->record ? __('admin.participation.filters.entities.record') : __('admin.participation.filters.entities.user') }}
                            </span>
                            @if($log->flagged)
                                <span class="rounded border border-yellow-400/40 bg-yellow-500/10 px-2 py-1 text-xs font-semibold text-yellow-200">{{ $log->flag_reason }}</span>
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-[var(--admin-muted)]">
                            <span>{{ $log->created_at->format('M d, Y H:i') }}</span>
                            @if($log->record?->tournament)
                                <span>/</span>
                                <a href="{{ route('tournaments.show', $log->record->tournament) }}" class="font-tournament font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $log->record->tournament->title }}</a>
                                <span class="font-mono">#{{ $log->record->tournament->id }}</span>
                            @endif
                        </div>

                        <button type="button" class="mt-3 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2.5 py-1 text-xs font-semibold hover:border-[var(--osu-cyan)] hover:text-[var(--osu-cyan)]" @click="expanded = ! expanded">
                            <span x-text="expanded ? '{{ __('admin.participation.actions.hide_details') }}' : '{{ __('admin.participation.actions.show_details') }}'"></span>
                        </button>

                        <div x-show="expanded" x-transition class="mt-3 space-y-3" style="display: none;">
                            @forelse($changeRows as $row)
                                <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 text-sm">
                                    <div class="mb-3 font-semibold text-[var(--admin-text)]">{{ $fieldLabel($row['field']) }}</div>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        @if($row['has_before'])
                                            <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3">
                                                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.labels.before') }}</div>
                                                <div class="max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm text-[var(--admin-muted)]">{{ $formatValue($row['before']) }}</div>
                                            </div>
                                        @endif
                                        <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3 {{ $row['has_before'] ? '' : 'md:col-span-2' }}">
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ $row['has_before'] ? __('admin.participation.labels.after') : __('admin.participation.labels.updated_value') }}</div>
                                            <div class="max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm font-medium text-[var(--admin-text)]">{{ $formatValue($row['after']) }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 text-sm text-[var(--admin-muted)]">{{ __('admin.participation.empty_details') }}</div>
                            @endforelse
                        </div>
                        </div>
                    </div>

                    <div class="flex min-w-64 flex-wrap gap-2 lg:justify-end">
                        @if($log->record)
                            <button type="button" @click="actionModal = 'delete_input'" class="rounded-lg border border-red-400/50 px-3 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10">
                                {{ __('admin.participation.actions.delete_input') }}
                            </button>
                        @endif
                        @if($canRollbackTeammates)
                            <form method="POST" action="{{ route('admin.participation-moderation.logs.rollback-teammates', $log) }}" onsubmit="return window.confirm('Rollback the teammate change from this log?');">
                                @csrf
                                <button class="rounded-lg border border-[var(--osu-cyan)]/50 px-3 py-2 text-sm font-semibold text-[var(--osu-cyan)] hover:bg-[var(--osu-cyan)]/10">
                                    Rollback Teammates
                                </button>
                            </form>
                        @endif
                        <button type="button" @click="actionModal = 'lock'" class="rounded-lg border border-yellow-400/50 px-3 py-2 text-sm font-semibold text-yellow-200 hover:bg-yellow-500/10">
                            {{ __('admin.participation.actions.lock') }}
                        </button>
                    </div>
                </div>

                <div x-show="actionModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none;">
                    <div class="w-full max-w-md rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-xl" @click.outside="actionModal = null">
                        <h3 class="text-lg font-semibold" x-text="actionModal === 'delete_input' ? '{{ __('admin.participation.dialogs.delete_input_title') }}' : '{{ __('admin.participation.dialogs.lock_title') }}'"></h3>
                        <p class="mt-1 text-sm text-[var(--admin-muted)]" x-text="actionModal === 'delete_input' ? '{{ __('admin.participation.dialogs.delete_input_help') }}' : '{{ __('admin.participation.dialogs.lock_help') }}'"></p>
                        <form method="POST" :action="actionModal === 'delete_input' ? '{{ $log->record ? route('admin.participation-moderation.delete-input', $log->record) : '#' }}' : '{{ ($log->user && ! $log->user->trashed()) ? route('admin.participation-moderation.lock', $log->user) : '#' }}'" class="mt-4 space-y-4">
                            @csrf
                            <label class="block space-y-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[var(--admin-muted)]">{{ __('admin.participation.fields.reason') }}</span>
                                <textarea name="reason" rows="4" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)]"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="actionModal = null" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.participation.actions.cancel') }}</button>
                                <button class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('admin.participation.actions.confirm') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">{{ __('admin.participation.empty_logs') }}</div>
        @endforelse
    </section>

    @if($logs->hasPages())
        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-report-resolve-form')) {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"], button:not([type])');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to resolve report.');
            }

            const card = form.closest('[data-report-card]');
            if (card) {
                card.remove();
            }

            const section = document.querySelector('[data-reports-section]');
            if (section && !section.querySelector('[data-report-card]')) {
                section.querySelectorAll('[data-reports-empty]').forEach((emptyState) => {
                    emptyState.classList.remove('hidden');
                });
            }
        } catch (error) {
            window.alert(error.message || 'Unable to resolve report.');
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
</script>
@endpush
