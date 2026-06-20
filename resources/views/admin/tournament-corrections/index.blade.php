@extends('layouts.admin')

@section('title', __('admin.corrections.index.title'))

@section('content')
<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ __('admin.corrections.index.heading') }}</h1>
        <p class="mt-1 text-sm text-[var(--admin-muted)]">{{ __('admin.corrections.index.description') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.tournament-corrections.index') }}" class="mb-5 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
        <div class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.corrections.index.search_placeholder') }}" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)]">
            <select name="status" class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm text-[var(--admin-text)]">
                @foreach($counts as $value => $count)
                    <option value="{{ $value }}" @selected($status === $value)>{{ str($value)->replace('_', ' ')->headline() }} ({{ $count }})</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-[var(--osu-pink)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110">{{ __('admin.corrections.actions.filter') }}</button>
        </div>
    </form>

    <section class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
        @forelse($corrections as $correction)
            @php($changes = data_get($correction->payload, 'changes', []))
            <article class="border-b border-[var(--admin-border)] p-4 last:border-b-0">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold uppercase text-[var(--admin-muted)]">#{{ $correction->id }}</span>
                            @if($correction->isNewTournamentRequest())
                                <span class="rounded bg-cyan-500/15 px-2 py-1 text-xs font-bold uppercase text-cyan-300">New</span>
                            @endif
                            @if($correction->tournament)
                                <a href="{{ route('admin.tournaments.show', $correction->tournament) }}" class="font-tournament font-semibold text-[var(--admin-text)] hover:text-[var(--osu-cyan)]">{{ $correction->tournament->title }}</a>
                            @else
                                <span class="font-tournament font-semibold text-[var(--admin-text)]">{{ data_get($correction->payload, 'proposal.title', data_get($correction->payload, 'changes.metadata.title.new', 'New tournament')) }}</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-[var(--admin-muted)]">
                            <a href="{{ route('users.show', $correction->submitter) }}" class="font-semibold text-[var(--osu-cyan)] hover:underline">{{ $correction->submitter->username }}</a>
                            <span>{{ $correction->created_at->format('M d, Y H:i') }}</span>
                            <span>{{ __('admin.corrections.index.suggested_changes', ['count' => count($changes)]) }}</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.tournament-corrections.show', $correction) }}" class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold hover:bg-[var(--admin-bg)]">
                        {{ __('admin.corrections.actions.review') }}
                    </a>
                </div>
            </article>
        @empty
            <div class="px-4 py-10 text-center text-sm text-[var(--admin-muted)]">{{ __('admin.corrections.index.empty') }}</div>
        @endforelse
    </section>

    <div class="mt-6">{{ $corrections->links() }}</div>
</div>
@endsection
