@extends('layouts.admin')

@section('title', __('admin.corrections.show.title', ['id' => $correction->id]))

@php($accepted = data_get($correction->admin_decisions, 'accepted', []))
@php($applyFailures = data_get($correction->admin_decisions, 'apply_failures', []))
@php($visibleChangeCount = $groupedChanges->sum(fn ($changes) => $changes->count()))

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('admin.tournament-corrections.index') }}" class="text-sm font-semibold text-[var(--osu-cyan)] hover:underline">{{ __('admin.corrections.actions.back_to_corrections') }}</a>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">{{ __('admin.corrections.show.heading', ['id' => $correction->id]) }}</h1>
            <p class="mt-1 text-sm text-[var(--admin-muted)]">
                @if($correction->isNewTournamentRequest())
                    <span class="mr-2 rounded bg-cyan-500/15 px-2 py-1 text-xs font-bold uppercase text-cyan-300">New</span>
                @endif
                @if($correction->tournament)
                    <a href="{{ route('admin.tournaments.show', $correction->tournament) }}" class="font-tournament text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $correction->tournament->title }}</a>
                @else
                    <span class="font-tournament text-[var(--admin-text)]">{{ data_get($correction->payload, 'proposal.title', 'New tournament request') }}</span>
                @endif
                {{ __('admin.corrections.show.by_submitter') }} <a href="{{ route('users.show', $correction->submitter) }}" class="text-[var(--osu-cyan)] hover:underline">{{ $correction->submitter->username }}</a>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tournament-corrections.show', $correction) }}" class="rounded-lg border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold text-[var(--osu-cyan)] hover:bg-[var(--admin-surface)]">
                Open discussion
            </a>
            <span class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 text-sm font-semibold uppercase text-[var(--admin-muted)]">
                {{ str($correction->status)->replace('_', ' ')->headline() }}
            </span>
        </div>
    </div>

    <form id="correction-review-form" method="POST" action="{{ route('admin.tournament-corrections.review', $correction) }}" class="space-y-5">
        @csrf

        @if(filled($correction->submitter_note))
            <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
                <div class="text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.corrections.show.submitter_note') }}</div>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[var(--admin-text)]">{{ $correction->submitter_note }}</p>
            </section>
        @endif

        @if(is_array($applyFailures) && $applyFailures !== [])
            <section class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4">
                <h2 class="font-semibold text-amber-100">Some accepted changes could not be applied</h2>
                <ul class="mt-3 space-y-2 text-sm text-amber-50">
                    @foreach($applyFailures as $failure)
                        <li class="rounded border border-amber-500/30 bg-[var(--admin-bg)] px-3 py-2">
                            <span class="font-semibold">{{ $failure['username'] ?? 'Unknown username' }}</span>
                            <span class="text-[var(--admin-muted)]">({{ $failure['domain'] ?? 'correction' }})</span>
                            <div class="mt-1 text-amber-100">{{ $failure['message'] ?? 'Unable to apply this change.' }}</div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @foreach($groupedChanges as $domain => $changes)
            <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
                <div class="flex flex-col gap-3 border-b border-[var(--admin-border)] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="font-semibold">{{ str($domain)->replace('_', ' ')->headline() }}</h2>
                    @if($correction->status === \App\Models\TournamentCorrection::STATUS_PENDING)
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--admin-muted)]">
                            <input
                                type="checkbox"
                                class="section-toggle rounded border-[var(--admin-border)] bg-[var(--admin-bg)]"
                                data-domain="{{ $domain }}"
                                checked>
                            {{ __('admin.corrections.show.accept_section') }}
                        </label>
                    @endif
                </div>
                <div class="divide-y divide-[var(--admin-border)]">
                    @foreach($changes as $key => $change)
                        <label class="block p-4 transition hover:bg-[var(--admin-bg)]/60" data-change-domain="{{ $domain }}" data-change-row data-change-label="{{ $change['label'] ?? $key }}">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    @if($correction->status === \App\Models\TournamentCorrection::STATUS_PENDING)
                                        <input type="checkbox" name="accepted_keys[]" value="{{ $key }}" class="change-toggle rounded border-[var(--admin-border)] bg-[var(--admin-bg)]" data-domain="{{ $domain }}" checked>
                                    @elseif(in_array($key, $accepted ?: [], true))
                                        <span class="rounded bg-green-500/10 px-2 py-1 text-xs font-semibold text-green-300">{{ __('admin.corrections.status.accepted') }}</span>
                                    @else
                                        <span class="rounded bg-red-500/10 px-2 py-1 text-xs font-semibold text-red-300">{{ __('admin.corrections.status.rejected') }}</span>
                                    @endif
                                    <span class="font-semibold text-[var(--admin-text)]">{{ $change['label'] ?? $key }}</span>
                                </div>
                                <span class="font-mono text-xs text-[var(--admin-muted)]">{{ $key }}</span>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                    <div class="mb-2 text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.corrections.show.old') }}</div>
                                    <x-tournaments.correction-diff-value :value="$change['old'] ?? null" :field="$change['field'] ?? null" :muted="true" :admin="true" />
                                </div>
                                <div class="min-w-0 rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
                                    <div class="mb-2 text-xs font-semibold uppercase text-[var(--admin-muted)]">{{ __('admin.corrections.show.new') }}</div>
                                    <x-tournaments.correction-diff-value :value="$change['new'] ?? null" :field="$change['field'] ?? null" :admin="true" />
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($correction->status === \App\Models\TournamentCorrection::STATUS_PENDING)
            <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
                <label class="block space-y-2">
                    <span class="text-sm font-semibold text-[var(--admin-muted)]">{{ __('admin.corrections.show.review_note') }}</span>
                    <textarea name="review_note" rows="4" class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)]"></textarea>
                </label>
                <div class="mt-4 flex justify-end gap-3">
                    <a href="{{ route('admin.tournament-corrections.index') }}" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.corrections.actions.cancel') }}</a>
                    <button type="button" name="review_action" value="reject_all" data-review-submit data-review-action="reject_all" class="rounded-lg border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/10">
                        {{ __('admin.corrections.actions.reject_all') }}
                    </button>
                    <button type="button" name="review_action" value="finalize_selected" data-review-submit data-review-action="finalize_selected" class="rounded-lg bg-[var(--osu-pink)] px-5 py-2 text-sm font-semibold text-white hover:brightness-110">
                        {{ __('admin.corrections.actions.finalize_selected') }} (<span data-selected-count>{{ $visibleChangeCount }}</span>)
                    </button>
                </div>
            </section>
        @endif

        <div data-review-confirm-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-2xl rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Confirm correction review</h2>
                        <p class="mt-1 text-sm text-[var(--admin-muted)]" data-review-confirm-summary></p>
                    </div>
                    <button type="button" data-review-confirm-cancel class="rounded border border-[var(--admin-border)] px-2 py-1 text-sm hover:bg-[var(--admin-bg)]">Close</button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded border border-green-500/30 bg-green-500/10 p-3">
                        <div class="text-sm font-semibold text-green-200">Approved</div>
                        <ul data-review-approved-list class="mt-2 max-h-56 space-y-1 overflow-auto text-sm text-[var(--admin-text)]"></ul>
                    </div>
                    <div class="rounded border border-red-500/30 bg-red-500/10 p-3">
                        <div class="text-sm font-semibold text-red-200">Rejected</div>
                        <ul data-review-rejected-list class="mt-2 max-h-56 space-y-1 overflow-auto text-sm text-[var(--admin-text)]"></ul>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-review-confirm-cancel class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">{{ __('admin.corrections.actions.cancel') }}</button>
                    <button type="button" data-review-confirm-submit class="rounded-lg bg-[var(--osu-pink)] px-5 py-2 text-sm font-semibold text-white hover:brightness-110">Confirm Review</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('correction-review-form');
        if (!form) {
            return;
        }

        const selectedCount = form.querySelector('[data-selected-count]');
        const updateSelectedCount = () => {
            const count = form.querySelectorAll('.change-toggle:checked').length;
            if (selectedCount) {
                selectedCount.textContent = count.toString();
            }

            form.querySelectorAll('.section-toggle').forEach((sectionToggle) => {
                const domain = sectionToggle.dataset.domain;
                const sectionChanges = Array.from(form.querySelectorAll(`.change-toggle[data-domain="${domain}"]`));
                const checkedChanges = sectionChanges.filter((input) => input.checked);
                sectionToggle.checked = sectionChanges.length > 0 && checkedChanges.length === sectionChanges.length;
                sectionToggle.indeterminate = checkedChanges.length > 0 && checkedChanges.length < sectionChanges.length;
            });
        };

        form.querySelectorAll('.section-toggle').forEach((sectionToggle) => {
            sectionToggle.addEventListener('change', () => {
                const domain = sectionToggle.dataset.domain;
                form.querySelectorAll(`.change-toggle[data-domain="${domain}"]`).forEach((input) => {
                    input.checked = sectionToggle.checked;
                });
                updateSelectedCount();
            });
        });

        form.querySelectorAll('.change-toggle').forEach((input) => {
            input.addEventListener('change', updateSelectedCount);
        });

        const modal = form.querySelector('[data-review-confirm-modal]');
        const summary = form.querySelector('[data-review-confirm-summary]');
        const approvedList = form.querySelector('[data-review-approved-list]');
        const rejectedList = form.querySelector('[data-review-rejected-list]');
        let pendingAction = null;

        const listItem = (label) => {
            const item = document.createElement('li');
            item.className = 'rounded border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1';
            item.textContent = label;

            return item;
        };

        const fillList = (list, labels, emptyText) => {
            list.textContent = '';
            if (labels.length === 0) {
                list.appendChild(listItem(emptyText));

                return;
            }

            labels.forEach((label) => list.appendChild(listItem(label)));
        };

        const openConfirmModal = (action) => {
            pendingAction = action;
            const rows = Array.from(form.querySelectorAll('[data-change-row]'));
            const checkedInputs = Array.from(form.querySelectorAll('.change-toggle:checked'));
            const acceptedLabels = action === 'reject_all'
                ? []
                : checkedInputs.map((input) => input.closest('[data-change-row]')?.dataset.changeLabel || input.value);
            const acceptedValues = new Set(action === 'reject_all' ? [] : checkedInputs.map((input) => input.value));
            const rejectedLabels = rows
                .filter((row) => action === 'reject_all' || !acceptedValues.has(row.querySelector('.change-toggle')?.value))
                .map((row) => row.dataset.changeLabel || 'Correction change');

            if (action === 'finalize_selected' && acceptedLabels.length === 0) {
                window.alert('Select at least one correction change to accept.');

                return;
            }

            summary.textContent = action === 'reject_all'
                ? `Reject all ${rows.length} correction changes.`
                : `Approve ${acceptedLabels.length} and reject ${rejectedLabels.length} correction changes.`;
            fillList(approvedList, acceptedLabels, 'No changes will be approved.');
            fillList(rejectedList, rejectedLabels, 'No changes will be rejected.');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const closeConfirmModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingAction = null;
        };

        form.querySelectorAll('[data-review-submit]').forEach((button) => {
            button.addEventListener('click', () => openConfirmModal(button.dataset.reviewAction));
        });

        form.querySelectorAll('[data-review-confirm-cancel]').forEach((button) => {
            button.addEventListener('click', closeConfirmModal);
        });

        form.querySelector('[data-review-confirm-submit]')?.addEventListener('click', () => {
            if (!pendingAction) {
                return;
            }

            let actionInput = form.querySelector('input[name="review_action"][type="hidden"]');
            if (!actionInput) {
                actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'review_action';
                form.appendChild(actionInput);
            }

            actionInput.value = pendingAction;
            form.submit();
        });

        updateSelectedCount();
    });
</script>
@endpush
