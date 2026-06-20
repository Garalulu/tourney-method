@props(['tournaments', 'startDate', 'endDate'])

<section>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="p-1.5 bg-yellow-500/20 rounded-lg">
                <x-icon name="lucide-star" class="w-5 h-5 text-yellow-400" />
            </div>
            <div>
                <h2 class="text-xl font-display font-bold text-white">{{ __('dashboard.dashboard.last_week') }}</h2>
                <p class="text-sm text-gray-500">
                    {{ $startDate->format('M j') }} – {{ $endDate->format('M j') }}
                </p>
            </div>
        </div>
        <a href="{{ route('tournaments.index', ['mode' => auth()->user()->main_mode, 'tab' => 'ended']) }}" class="text-sm text-yellow-400 hover:text-yellow-400/80 font-medium transition-colors">
            {{ __('dashboard.dashboard.view_all') }}
            <x-icon name="lucide-chevron-right" class="inline w-4 h-4 ml-1" />
        </a>
    </div>

    @if($tournaments->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
            @foreach($tournaments as $tournament)
                @php
                    $metadata = array_filter([
                        $tournament->rank_range,
                        $tournament->team_size_display ?: null,
                        $tournament->isRegionRestricted() ? __('users.participation.labels.regional') : __('users.participation.labels.global'),
                        $tournament->is_badge ? __('users.history.badged') : null,
                    ]);
                @endphp

                <div class="card group hover:border-yellow-500/50 transition-all">
                    {{-- Tournament Header --}}
                    <div class="flex items-start gap-3 mb-3">
                        <img src="{{ $tournament->cached_banner_url }}"
                             alt="{{ $tournament->title }}"
                             class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                             loading="lazy"
                             decoding="async">
                        <div class="flex-1 min-w-0">
                            {{-- Title + SR Range --}}
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <a href="{{ route('tournaments.show', $tournament) }}"
                                   class="font-tournament font-bold text-white group-hover:text-yellow-400 hover:text-yellow-400 transition-colors truncate flex-1">
                                    {{ $tournament->title }}
                                </a>
                                @if($tournament->star_rating_display)
                                    <span class="text-xs text-yellow-500 flex-shrink-0">
                                        {{ $tournament->star_rating_display }}
                                    </span>
                                @endif
                            </div>

                            {{-- Tournament metadata --}}
                            <div class="text-xs text-gray-500">
                                {{ implode(' · ', $metadata) }}
                            </div>
                        </div>
                    </div>

                    {{-- Podium Display - Horizontal Layout --}}
                    @if($tournament->winners->count() > 0)
                        <div class="flex gap-2">
                            @foreach([1, 2, 3] as $placement)
                                @php
                                    $placementWinners = $tournament->winners->where('placement', $placement);
                                    $placementColor = match($placement) {
                                        1 => 'text-yellow-400',
                                        2 => 'text-slate-300',
                                        3 => 'text-orange-400',
                                    };
                                    $placementBg = match($placement) {
                                        1 => 'bg-yellow-500/10',
                                        2 => 'bg-gray-500/10',
                                        3 => 'bg-orange-500/10',
                                    };
                                @endphp

                                <div class="flex-1 {{ $placementBg }} rounded-lg p-2 min-w-0">
                                    {{-- Placement number --}}
                                    <div class="{{ $placementColor }} text-xs font-semibold mb-1">
                                        {{ $placement }}{{ $placement === 1 ? 'st' : ($placement === 2 ? 'nd' : 'rd') }}
                                    </div>

                                    {{-- Winners list (stacked vertically) --}}
                                    @if($placementWinners->count() > 0)
                                        <div class="space-y-1">
                                            @foreach($placementWinners as $winner)
                                                @if($winner->user_id)
                                                    <a href="{{ route('users.show', $winner->user_id) }}"
                                                       class="flex items-center gap-1.5 group/winner hover:opacity-80">
                                                        @if($winner->user?->country_code)
                                                            <span class="text-xs leading-none flex-shrink-0"
                                                                  title="{{ $winner->user->country_code }}">
                                                                {{ country_flag($winner->user->country_code) }}
                                                            </span>
                                                        @endif
                                                        <img src="https://a.ppy.sh/{{ $winner->display_osu_id }}"
                                                             alt="{{ $winner->display_username }}"
                                                             class="w-4 h-4 rounded-full flex-shrink-0">
                                                        <span class="{{ $placementColor }} text-sm font-medium truncate group-hover/winner:underline">
                                                            {{ $winner->display_username }}
                                                        </span>
                                                    </a>
                                                @else
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="w-4 h-4 rounded-full bg-slate-700 flex-shrink-0"></div>
                                                        <span class="{{ $placementColor }} text-sm truncate">
                                                            {{ $winner->display_username }}
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- No podium information --}}
                        <div class="text-center py-4 px-3 rounded-lg bg-slate-800/50">
                            <p class="text-sm text-gray-500 italic">{{ __('dashboard.dashboard.no_podium_informations') }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- No tournaments ended last week --}}
        <div class="card text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-dark-700 flex items-center justify-center">
                <x-icon name="lucide-circle-check" class="w-8 h-8 text-gray-500" />
            </div>
            <h3 class="text-lg font-display font-semibold text-white mb-2">{{ __('dashboard.dashboard.no_ended_touranments') }}</h3>
        </div>
    @endif
</section>
