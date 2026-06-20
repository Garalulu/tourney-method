@forelse($sections as $section)
    <section>
        <div class="mb-3 flex items-center gap-2">
            <span class="text-sm font-bold uppercase tracking-wide text-slate-300">{{ $section['label'] }}</span>
            <div class="h-px flex-1 bg-gradient-to-r from-slate-600 to-transparent"></div>
        </div>
        <div class="space-y-2 pl-0 sm:pl-10">
            @foreach($section['teams'] as $team)
                @include('tournaments.partials.result-team', [
                    'team' => $team,
                    'size' => 'compact',
                    'includePlacementInHeading' => false,
                ])
            @endforeach
        </div>
    </section>
@empty
    <p class="rounded-lg border border-slate-700/50 bg-slate-900/40 px-4 py-3 text-sm text-slate-400">
        {{ __('tournaments.results.empty') }}
    </p>
@endforelse
