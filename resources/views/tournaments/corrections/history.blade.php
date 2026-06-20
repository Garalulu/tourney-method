@extends('layouts.app')

@section('title', __('tournaments.corrections.history.title_with_tournament', ['title' => $tournament->title]))

@section('content')
<div class="min-h-screen bg-slate-950 py-10">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <a href="{{ route('tournaments.show', $tournament) }}" class="text-sm font-semibold text-pink-400 hover:text-pink-300">{{ __('tournaments.corrections.actions.back_to_tournament') }}</a>
        <h1 class="mt-3 text-3xl font-black text-white">{{ __('tournaments.corrections.history.heading', ['title' => $tournament->title]) }}</h1>

        <div class="mt-6 space-y-4">
            @forelse($corrections as $correction)
                @php
                    $changes = $orderedCorrectionChanges[$correction->id] ?? data_get($correction->payload, 'changes', []);
                    $staffGroups = $groupedStaffHistoryChanges[$correction->id] ?? [];
                    $podiumGroups = $groupedPodiumHistoryChanges[$correction->id] ?? [];
                    $accepted = data_get($correction->admin_decisions, 'accepted', []);
                    $applyFailures = data_get($correction->admin_decisions, 'apply_failures', []);
                    $visibleAccepted = array_values(array_intersect($accepted ?: [], array_keys($changes)));
                    $staffGroupsRendered = false;
                    $podiumGroupsRendered = false;
                @endphp
                <article class="rounded-lg border border-slate-700 bg-slate-900/70 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('tournament-corrections.show', $correction) }}" class="font-mono text-sm font-semibold text-pink-400 hover:underline">#{{ $correction->id }}</a>
                                <span class="rounded bg-slate-800 px-2 py-1 text-xs font-semibold uppercase text-slate-300">{{ str($correction->status)->replace('_', ' ')->headline() }}</span>
                            </div>
                            <div class="mt-2 space-y-1 text-sm text-slate-400">
                                <p>
                                    {{ __('tournaments.corrections.history.submitted_at', ['time' => $correction->created_at->format('M d, Y H:i')]) }}
                                    {{ __('tournaments.corrections.history.by') }} <a href="{{ route('users.show', $correction->submitter) }}" class="font-semibold text-pink-400 hover:underline">{{ $correction->submitter->username }}</a>
                                </p>
                                @if($correction->reviewed_at)
                                    <p>
                                        {{ __('tournaments.corrections.history.reviewed_at', ['time' => $correction->reviewed_at->format('M d, Y H:i')]) }}
                                        @if($correction->reviewer)
                                            {{ __('tournaments.corrections.history.by') }} <a href="{{ route('users.show', $correction->reviewer) }}" class="font-semibold text-pink-400 hover:underline">{{ $correction->reviewer->username }}</a>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm text-slate-400">{{ __('tournaments.corrections.history.approved_summary', ['approved' => count($visibleAccepted), 'suggested' => count($changes)]) }}</div>
                    </div>

                    @if(filled($correction->submitter_note) || filled($correction->review_note))
                        <div class="mt-4 space-y-3">
                            @if(filled($correction->submitter_note))
                                <div class="rounded-lg border border-pink-500/20 bg-pink-500/10 p-3">
                                    <div class="text-xs font-semibold uppercase text-pink-200">{{ __('tournaments.corrections.history.submitter_reply') }}</div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-100">{{ $correction->submitter_note }}</p>
                                </div>
                            @endif
                            @if(filled($correction->review_note))
                                <div class="rounded-lg border border-cyan-500/20 bg-cyan-500/10 p-3">
                                    <div class="text-xs font-semibold uppercase text-cyan-200">{{ __('tournaments.corrections.history.admin_reply') }}</div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-100">{{ $correction->review_note }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        @foreach($changes as $key => $change)
                            @if(($change['domain'] ?? null) === 'staff')
                                @if(! $staffGroupsRendered)
                                    @php($staffGroupsRendered = true)
                                    <x-tournaments.correction-staff-groups
                                        :groups="$staffGroups"
                                        :accepted="$accepted"
                                        :failures="$applyFailures"
                                        :status="$correction->status" />
                                @endif
                            @elseif(($change['domain'] ?? null) === 'podium')
                                @if(! $podiumGroupsRendered)
                                    @php($podiumGroupsRendered = true)
                                    <x-tournaments.correction-podium-groups
                                        :groups="$podiumGroups"
                                        :accepted="$accepted"
                                        :failures="$applyFailures"
                                        :status="$correction->status" />
                                @endif
                            @else
                                <div class="rounded border border-slate-800 bg-slate-950 p-3 text-sm">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-white">{{ $change['label'] ?? $key }}</span>
                                        @if(in_array($key, $accepted ?: [], true))
                                            <span class="rounded bg-green-500/10 px-2 py-0.5 text-xs font-semibold text-green-300">{{ __('tournaments.corrections.status.accepted') }}</span>
                                        @elseif($correction->status !== \App\Models\TournamentCorrection::STATUS_PENDING)
                                            <span class="rounded bg-red-500/10 px-2 py-0.5 text-xs font-semibold text-red-300">{{ __('tournaments.corrections.status.rejected') }}</span>
                                        @endif
                                    </div>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div class="rounded bg-slate-900 p-2">
                                            <x-tournaments.correction-diff-value :value="$change['old'] ?? null" :field="$change['field'] ?? null" :muted="true" />
                                        </div>
                                        <div class="rounded bg-slate-900 p-2">
                                            <x-tournaments.correction-diff-value :value="$change['new'] ?? null" :field="$change['field'] ?? null" />
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-10 text-center text-slate-400">{{ __('tournaments.corrections.history.empty') }}</div>
            @endforelse
        </div>

        <div class="mt-6">{{ $corrections->links() }}</div>
    </div>
</div>
@endsection
