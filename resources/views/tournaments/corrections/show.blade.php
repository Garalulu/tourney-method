@extends('layouts.app')

@section('title', 'Correction #'.$correction->id)

@php($applyFailures = data_get($correction->admin_decisions, 'apply_failures', []))
@php($accepted = data_get($correction->admin_decisions, 'accepted', []))

@section('content')
<div class="min-h-screen bg-slate-950 py-10">
    <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if($correction->tournament)
                    <a href="{{ route('tournaments.corrections.history', $correction->tournament) }}" class="text-sm font-semibold text-pink-400 hover:underline">Back to correction history</a>
                @endif
                <h1 class="mt-2 text-3xl font-black text-white">Correction #{{ $correction->id }}</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Submitted by <a href="{{ route('users.show', $correction->submitter) }}" class="font-semibold text-pink-400 hover:underline">{{ $correction->submitter->username }}</a>
                    @if($correction->tournament)
                        for <a href="{{ route('tournaments.show', $correction->tournament) }}" class="font-semibold text-white hover:underline">{{ $correction->tournament->title }}</a>
                    @endif
                </p>
            </div>
            <span class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold uppercase text-slate-300" data-correction-status>
                {{ str($correction->status)->replace('_', ' ')->headline() }}
            </span>
        </header>

        @if($correction->status === \App\Models\TournamentCorrection::STATUS_PROCESSING)
            <section class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-4" data-processing-panel data-status-url="{{ route('tournament-corrections.status', $correction) }}">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-cyan-100">Applying staff and podium changes</h2>
                        <p class="mt-1 text-sm text-cyan-50/80">You can leave this page. Processing continues in the background.</p>
                    </div>
                    <span class="font-mono text-sm text-cyan-100" data-processing-count>{{ $correction->processing_completed }}/{{ $correction->processing_total }}</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded bg-slate-800">
                    <div class="h-full bg-cyan-400 transition-all" data-processing-bar style="width: {{ $correction->processingPercent() }}%"></div>
                </div>
            </section>
        @endif

        @if($groupedFailures !== [])
            <section class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4">
                <h2 class="font-semibold text-amber-100">Some accepted changes could not be applied</h2>
                <ul class="mt-3 space-y-2 text-sm text-amber-50">
                    @foreach($groupedFailures as $failure)
                        <li class="rounded border border-amber-500/30 bg-slate-950 px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ str($failure['domain'])->headline() }}</span>
                                @if($failure['usernames'] !== [])
                                    <span class="text-slate-400">{{ count($failure['usernames']) }} affected</span>
                                @endif
                            </div>
                            <div class="mt-1 text-amber-100">{{ $failure['message'] }}</div>
                            @if($failure['usernames'] !== [])
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($failure['usernames'] as $username)
                                        <span class="rounded border border-amber-500/30 bg-amber-500/5 px-2 py-1 text-xs">{{ $username }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(filled($correction->submitter_note) || filled($correction->review_note))
            <section class="grid gap-3 md:grid-cols-2">
                @if(filled($correction->submitter_note))
                    <div class="rounded-lg border border-pink-500/20 bg-pink-500/10 p-4">
                        <div class="text-xs font-semibold uppercase text-pink-200">User note</div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-100">{{ $correction->submitter_note }}</p>
                    </div>
                @endif
                @if(filled($correction->review_note))
                    <div class="rounded-lg border border-cyan-500/20 bg-cyan-500/10 p-4">
                        <div class="text-xs font-semibold uppercase text-cyan-200">Admin review description</div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-100">{{ $correction->review_note }}</p>
                    </div>
                @endif
            </section>
        @endif

        @foreach($groupedChanges as $domain => $changes)
            <section class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900/70">
                <h2 class="border-b border-slate-700 px-4 py-3 font-semibold text-white">{{ str($domain)->replace('_', ' ')->headline() }}</h2>
                <div class="p-4">
                    @if($domain === 'staff')
                        <x-tournaments.correction-staff-groups
                            :groups="$staffGroups"
                            :accepted="$accepted"
                            :failures="$applyFailures"
                            :status="$correction->status" />
                    @elseif($domain === 'podium')
                        <x-tournaments.correction-podium-groups
                            :groups="$podiumGroups"
                            :accepted="$accepted"
                            :failures="$applyFailures"
                            :status="$correction->status" />
                    @else
                        <div class="divide-y divide-slate-800">
                            @foreach($changes as $key => $change)
                                <div class="py-4 first:pt-0 last:pb-0">
                                    <div class="mb-3 flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-white">{{ $change['label'] ?? $key }}</span>
                                        @if(in_array($key, $accepted ?: [], true))
                                            <span class="rounded bg-green-500/10 px-2 py-0.5 text-xs font-semibold text-green-300">Accepted</span>
                                        @elseif($correction->status !== \App\Models\TournamentCorrection::STATUS_PENDING)
                                            <span class="rounded bg-red-500/10 px-2 py-0.5 text-xs font-semibold text-red-300">Rejected</span>
                                        @endif
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="rounded bg-slate-950 p-3"><x-tournaments.correction-diff-value :value="$change['old'] ?? null" :field="$change['field'] ?? null" :muted="true" /></div>
                                        <div class="rounded bg-slate-950 p-3"><x-tournaments.correction-diff-value :value="$change['new'] ?? null" :field="$change['field'] ?? null" /></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endforeach

        <section class="rounded-lg border border-slate-700 bg-slate-900/70 p-5">
            <h2 class="text-xl font-bold text-white">Discussion</h2>
            <div class="mt-4 space-y-3">
                @forelse($correction->comments as $comment)
                    <article class="rounded-lg border border-slate-800 bg-slate-950 p-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <a href="{{ route('users.show', $comment->author) }}" class="font-semibold text-pink-400 hover:underline">{{ $comment->author->username }}</a>
                            <time class="text-slate-500">{{ $comment->created_at->format('M d, Y H:i') }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-100">{{ $comment->body }}</p>
                    </article>
                @empty
                    <p class="text-sm text-slate-400">No follow-up messages yet.</p>
                @endforelse
            </div>

            @if($canComment)
                <form method="POST" action="{{ route('tournament-corrections.comments.store', $correction) }}" class="mt-5">
                    @csrf
                    <label for="correction-comment" class="text-sm font-semibold text-slate-200">Post a reply</label>
                    <textarea id="correction-comment" name="body" rows="4" maxlength="2000" required class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    <div class="mt-3 flex justify-end">
                        <button class="rounded-lg bg-pink-500 px-5 py-2 text-sm font-semibold text-white hover:bg-pink-400">Post reply</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection

@if($correction->status === \App\Models\TournamentCorrection::STATUS_PROCESSING)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const panel = document.querySelector('[data-processing-panel]');
        if (!panel) return;

        const poll = async () => {
            try {
                const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                document.querySelector('[data-processing-count]').textContent = `${data.processing_completed}/${data.processing_total}`;
                document.querySelector('[data-processing-bar]').style.width = `${data.processing_percent}%`;
                document.querySelector('[data-correction-status]').textContent = data.status.replaceAll('_', ' ');
                if (data.finished) {
                    window.location.reload();
                    return;
                }
            } catch (error) {
                console.debug('Correction status polling failed', error);
            }
            window.setTimeout(poll, 2000);
        };

        window.setTimeout(poll, 1000);
    });
</script>
@endpush
@endif
