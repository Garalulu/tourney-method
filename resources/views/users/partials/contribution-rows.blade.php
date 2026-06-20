@foreach($contributionGroups as $group)
    @php($tournament = $group['tournament'])
    <a href="{{ route('tournaments.show', $tournament->id) }}"
       class="block rounded-lg border border-slate-700 bg-slate-900/70 px-4 py-3 font-tournament font-semibold text-pink-400 transition hover:border-pink-500/60 hover:bg-slate-900 hover:text-pink-300">
        {{ $tournament->title }}
    </a>
@endforeach
