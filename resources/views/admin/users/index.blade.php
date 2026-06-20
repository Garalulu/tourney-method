@extends('layouts.admin')

@section('title', __('admin.users.title'))

@section('content')
@php
    $statusLabels = [
        'logged_in' => __('admin.users.status.logged_in'),
        'auto' => __('admin.users.status.auto'),
        'manual' => __('admin.users.status.manual'),
    ];

    $modeLabels = [
        'osu' => 'osu!',
        'taiko' => 'osu!taiko',
        'catch' => 'osu!catch',
        'mania' => 'osu!mania',
    ];

    $localeLabels = [
        'en' => 'English',
        'es' => 'Español',
        'ko' => '한국어',
        'ru' => 'Русский',
        'zh-Hans' => '简体中文',
        'zh-Hant' => '繁體中文',
    ];
    $sortableHeadings = [
        'id' => true,
        'user' => true,
        'osu_id' => true,
        'country' => true,
        'mode' => true,
        'role' => true,
        'status' => true,
        'last_login' => true,
        'rank_updated' => true,
        'locale' => true,
        'actions' => false,
    ];
    $currentSort = request('sort', 'id');
    $currentDirection = request('direction') === 'asc' ? 'asc' : 'desc';
    $sortUrl = function (string $heading) use ($currentSort, $currentDirection): string {
        $nextDirection = $currentSort === $heading && $currentDirection === 'asc' ? 'desc' : 'asc';

        return route('admin.users.index', array_merge(request()->except('page'), [
            'sort' => $heading,
            'direction' => $nextDirection,
        ]));
    };
@endphp

<div class="px-4 py-5 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 border-b border-[var(--admin-border)] pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('admin.users.title') }}</h1>
            <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.users.description') }}</p>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
            <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2">
                <div class="text-xs uppercase text-[var(--admin-muted)]">{{ __('admin.users.total_users') }}</div>
                <div class="text-lg font-bold text-[var(--osu-cyan)]">{{ $users->total() }}</div>
            </div>
            <button type="button" data-action="cleanup-orphans" class="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                {{ __('admin.users.actions.cleanup_orphans') }}
            </button>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-5 grid gap-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 md:grid-cols-6">
        <label class="md:col-span-2">
            <span class="mb-1 block text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.users.filters.search') }}</span>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="{{ __('admin.users.filters.search_placeholder') }}"
                class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-[var(--osu-pink)] focus:outline-none"
            >
        </label>

        <label>
            <span class="mb-1 block text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.users.filters.mode') }}</span>
            <select name="mode" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-[var(--osu-pink)] focus:outline-none">
                <option value="">{{ __('admin.users.filters.all') }}</option>
                @foreach($options['modes'] as $mode)
                    <option value="{{ $mode }}" @selected(request('mode') === $mode)>{{ $modeLabels[$mode] }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mb-1 block text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.users.filters.status') }}</span>
            <select name="status" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-[var(--osu-pink)] focus:outline-none">
                <option value="">{{ __('admin.users.filters.all') }}</option>
                @foreach($options['statuses'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mb-1 block text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.users.filters.locale') }}</span>
            <select name="locale" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-[var(--osu-pink)] focus:outline-none">
                <option value="">{{ __('admin.users.filters.all') }}</option>
                @foreach($options['locales'] as $locale)
                    <option value="{{ $locale }}" @selected(request('locale') === $locale)>{{ $localeLabels[$locale] }}</option>
                @endforeach
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-[var(--osu-pink)] px-3 py-2 text-sm font-semibold text-white transition hover:brightness-110">
                {{ __('admin.users.filters.apply') }}
            </button>
            <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm text-[var(--admin-muted)] transition hover:text-[var(--admin-text)]">
                {{ __('admin.users.filters.reset') }}
            </a>
        </div>
    </form>

    <div class="mt-4 flex flex-col gap-3 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-[var(--admin-muted)]">
            <span data-selected-count>0</span> {{ __('admin.users.selected') }}
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="button" data-action="sync-selected" class="rounded-lg border border-[var(--osu-cyan)] px-3 py-2 text-sm font-semibold text-[var(--osu-cyan)] transition hover:bg-[var(--osu-cyan)]/10 disabled:cursor-not-allowed disabled:opacity-40" disabled>
                {{ __('admin.users.actions.sync_selected') }}
            </button>
            <button type="button" data-action="delete-selected" class="rounded-lg border border-red-400/50 px-3 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-40" disabled>
                {{ __('admin.users.actions.delete_selected') }}
            </button>
        </div>
    </div>

    @if($users->isEmpty())
        <div class="mt-6 flex min-h-64 flex-col items-center justify-center rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-8 text-center">
            <h2 class="text-xl font-bold">{{ __('admin.users.empty.title') }}</h2>
            <p class="mt-2 max-w-md text-sm text-[var(--admin-muted)]">{{ __('admin.users.empty.description') }}</p>
        </div>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] lg:block">
            <table class="w-full min-w-[1180px]">
                <thead class="border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input type="checkbox" data-select-all class="rounded border-[var(--admin-border)] bg-[var(--admin-bg)]">
                        </th>
                        @foreach($sortableHeadings as $heading => $sortable)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--admin-muted)]">
                                @if($sortable)
                                    <a href="{{ $sortUrl($heading) }}" class="inline-flex items-center gap-1.5 transition hover:text-[var(--admin-text)]">
                                        <span>{{ __('admin.users.table.'.$heading) }}</span>
                                        @if($currentSort === $heading)
                                            <span class="text-[var(--osu-cyan)]" aria-hidden="true">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</span>
                                        @else
                                            <span class="text-[var(--admin-border)]" aria-hidden="true">↕</span>
                                        @endif
                                    </a>
                                @else
                                    {{ __('admin.users.table.'.$heading) }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--admin-border)]">
                    @foreach($users as $user)
                        @php
                            $status = match ($user->main_mode_source) {
                                'oauth_setup' => 'logged_in',
                                'manual_update' => 'manual',
                                default => 'auto',
                            };
                            $latestRank = $user->latest_rank_recorded_at ? \Carbon\Carbon::parse($user->latest_rank_recorded_at) : null;
                        @endphp
                        <tr data-user-row="{{ $user->id }}" class="transition hover:bg-[var(--admin-bg)]/70">
                            <td class="px-4 py-3"><input type="checkbox" data-user-checkbox value="{{ $user->id }}" class="rounded border-[var(--admin-border)] bg-[var(--admin-bg)]"></td>
                            <td class="px-4 py-3 font-mono text-sm text-[var(--admin-muted)]">#{{ $user->id }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->username }}" class="h-10 w-10 rounded-full border border-[var(--admin-border)] object-cover">
                                    <a href="{{ route('users.show', $user) }}" target="_blank" class="font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $user->username }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-[var(--admin-muted)]">{{ $user->osu_id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($user->country_code)
                                    <span class="text-lg">{{ country_flag($user->country_code) }}</span>
                                    <span class="ml-1 text-[var(--admin-muted)]">{{ $user->country_code }}</span>
                                @else
                                    <span class="text-[var(--admin-muted)]">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">@include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'main_mode', 'value' => $user->main_mode, 'options' => $modeLabels])</td>
                            <td class="px-4 py-3">@include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'role', 'value' => $user->role, 'options' => array_combine($options['roles'], array_map(fn ($role) => __('admin.users.roles.'.$role), $options['roles']))])</td>
                            <td class="px-4 py-3">
                                <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold text-[var(--admin-text)]">{{ $statusLabels[$status] }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--admin-muted)]">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--admin-muted)]">{{ $latestRank?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                            <td class="px-4 py-3">@include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'locale', 'value' => $user->locale, 'options' => $localeLabels])</td>
                            <td class="px-4 py-3">
                                <button type="button" data-action="delete-one" data-user-id="{{ $user->id }}" class="rounded-lg border border-red-400/50 px-3 py-1.5 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                                    {{ __('admin.users.actions.delete') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 grid gap-3 lg:hidden">
            @foreach($users as $user)
                @php
                    $status = match ($user->main_mode_source) {
                        'oauth_setup' => 'logged_in',
                        'manual_update' => 'manual',
                        default => 'auto',
                    };
                    $latestRank = $user->latest_rank_recorded_at ? \Carbon\Carbon::parse($user->latest_rank_recorded_at) : null;
                @endphp
                <div data-user-row="{{ $user->id }}" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" data-user-checkbox value="{{ $user->id }}" class="mt-1 rounded border-[var(--admin-border)] bg-[var(--admin-bg)]">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->username }}" class="h-12 w-12 rounded-full border border-[var(--admin-border)] object-cover">
                            <div>
                                <a href="{{ route('users.show', $user) }}" target="_blank" class="font-semibold text-[var(--admin-text)]">{{ $user->username }}</a>
                                <div class="text-xs font-mono text-[var(--admin-muted)]">ID #{{ $user->id }} / osu {{ $user->osu_id }}</div>
                            </div>
                        </div>
                        <span class="rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold">{{ $statusLabels[$status] }}</span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase text-[var(--admin-muted)]">{{ __('admin.users.table.country') }}</dt>
                            <dd>{{ $user->country_code ? country_flag($user->country_code).' '.$user->country_code : 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-[var(--admin-muted)]">{{ __('admin.users.table.last_login') }}</dt>
                            <dd class="text-[var(--admin-muted)]">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-[var(--admin-muted)]">{{ __('admin.users.table.rank_updated') }}</dt>
                            <dd class="text-[var(--admin-muted)]">{{ $latestRank?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 grid gap-3">
                        @include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'main_mode', 'value' => $user->main_mode, 'options' => $modeLabels, 'label' => __('admin.users.table.mode')])
                        @include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'role', 'value' => $user->role, 'options' => array_combine($options['roles'], array_map(fn ($role) => __('admin.users.roles.'.$role), $options['roles'])), 'label' => __('admin.users.table.role')])
                        @include('admin.users.partials.inline-select', ['user' => $user, 'field' => 'locale', 'value' => $user->locale, 'options' => $localeLabels, 'label' => __('admin.users.table.locale')])
                    </div>

                    <button type="button" data-action="delete-one" data-user-id="{{ $user->id }}" class="mt-4 w-full rounded-lg border border-red-400/50 px-3 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                        {{ __('admin.users.actions.delete') }}
                    </button>
                </div>
            @endforeach
        </div>

        @if($users->hasPages())
            <div class="mt-5">{{ $users->links() }}</div>
        @endif
    @endif
</div>

@push('scripts')
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const selectedCount = document.querySelector('[data-selected-count]');
    const bulkButtons = document.querySelectorAll('[data-action="sync-selected"], [data-action="delete-selected"]');
    const checkboxes = () => Array.from(document.querySelectorAll('[data-user-checkbox]'));
    const selectedIds = () => checkboxes().filter((box) => box.checked).map((box) => Number(box.value));

    function updateBulkState() {
        const count = selectedIds().length;
        selectedCount.textContent = count;
        bulkButtons.forEach((button) => button.disabled = count === 0);
    }

    async function requestJson(url, method, payload = null) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: payload ? JSON.stringify(payload) : null,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || '{{ __('admin.users.messages.request_failed') }}');
            error.status = response.status;
            error.data = data;
            throw error;
        }

        return data;
    }

    async function requestJsonWithConfirmation(url, method, payload = null) {
        try {
            return await requestJson(url, method, payload);
        } catch (error) {
            if (error.status === 409 && error.data?.requires_confirmation && confirm(error.message)) {
                return await requestJson(url, method, { ...(payload || {}), confirmed: true });
            }

            throw error;
        }
    }

    function notify(message) {
        const element = document.createElement('div');
        element.className = 'fixed bottom-4 right-4 z-[70] max-w-sm rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-4 py-3 text-sm font-semibold text-white shadow-xl';
        element.textContent = message;
        document.body.appendChild(element);
        setTimeout(() => element.remove(), 2400);
    }

    document.addEventListener('change', async (event) => {
        const target = event.target;

        if (target.matches('[data-select-all]')) {
            checkboxes().forEach((box) => box.checked = target.checked);
            updateBulkState();
            return;
        }

        if (target.matches('[data-user-checkbox]')) {
            updateBulkState();
            return;
        }

        if (target.matches('[data-inline-field]')) {
            const userId = target.dataset.userId;
            const field = target.dataset.inlineField;
            const originalValue = target.dataset.originalValue;

            try {
                await requestJson(`/admin/users/${userId}`, 'PATCH', { [field]: target.value || null });
                target.dataset.originalValue = target.value;
                notify('{{ __('admin.users.messages.updated') }}');
            } catch (error) {
                target.value = originalValue;
                alert(error.message);
            }
        }
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;

        try {
            if (action === 'delete-one') {
                if (!confirm('{{ __('admin.users.confirm.delete_one') }}')) return;
                await requestJsonWithConfirmation(`/admin/users/${button.dataset.userId}`, 'DELETE');
                document.querySelectorAll(`[data-user-row="${button.dataset.userId}"]`).forEach((row) => row.remove());
                updateBulkState();
                notify('{{ __('admin.users.messages.deleted') }}');
            }

            if (action === 'delete-selected') {
                const ids = selectedIds();
                if (!confirm('{{ __('admin.users.confirm.delete_selected') }}'.replace(':count', ids.length))) return;
                await requestJsonWithConfirmation('{{ route('admin.users.bulk-destroy') }}', 'DELETE', { user_ids: ids });
                ids.forEach((id) => document.querySelectorAll(`[data-user-row="${id}"]`).forEach((row) => row.remove()));
                updateBulkState();
                notify('{{ __('admin.users.messages.deleted') }}');
            }

            if (action === 'sync-selected') {
                const ids = selectedIds();
                await requestJson('{{ route('admin.users.sync-selected') }}', 'POST', { user_ids: ids });
                notify('{{ __('admin.users.messages.sync_queued') }}');
            }

            if (action === 'cleanup-orphans') {
                if (!confirm('{{ __('admin.users.confirm.cleanup_orphans') }}')) return;
                const result = await requestJson('{{ route('admin.users.cleanup-orphans') }}', 'POST');
                notify('{{ __('admin.users.messages.orphans_deleted') }}'.replace(':count', result.deleted));
                if (result.deleted > 0) {
                    window.location.reload();
                }
            }
        } catch (error) {
            alert(error.message);
        }
    });

    updateBulkState();
})();
</script>
@endpush
@endsection
