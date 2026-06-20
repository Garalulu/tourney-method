@props([
    'tournament',
    'progressionSteps',
    'section' => 'details',
])

@php
    $summaryCell = 'rounded-xl border border-slate-700/60 bg-slate-900/45 p-3 lg:rounded-none lg:border-x-0 lg:border-t-0 lg:bg-transparent lg:px-0 lg:pt-0 lg:pb-4';
    $fullWidthCell = 'col-span-2 '.$summaryCell;
@endphp

<div
    data-tournament-section="{{ $section }}"
    class="rounded-2xl border-2 border-slate-700/50 bg-slate-800/50 p-4 sm:p-6"
>
    <div class="grid grid-cols-2 gap-3 lg:block lg:space-y-4">
        <h3 class="col-span-2 flex items-center gap-3 text-xl font-bold text-white lg:mb-4">
            <div class="h-6 w-1 rounded-full bg-gradient-to-b from-pink-500 to-purple-500"></div>
            {{ __('tournaments.details.title') }}
        </h3>

        @if($tournament->registration_start && $tournament->registration_end)
            <div class="{{ $fullWidthCell }}">
                <div class="mb-2 text-sm text-slate-400 lg:mb-1">{{ __('tournaments.details.reg_period') }}</div>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 font-bold text-white lg:block">
                    <x-localized-datetime :value="$tournament->registration_start" />
                    <span class="text-sm text-pink-400 lg:block">to</span>
                    <x-localized-datetime :value="$tournament->registration_end" />
                </div>
            </div>
        @endif

        @if($tournament->tournament_start)
            <div class="{{ $fullWidthCell }}">
                <div class="mb-2 text-sm text-slate-400 lg:mb-1">{{ __('tournaments.details.tournament_period') }}</div>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 font-bold text-white lg:block">
                    <x-localized-datetime :value="$tournament->tournament_start" />
                    @if($tournament->tournament_end)
                        <span class="text-sm text-pink-400 lg:block">to</span>
                        <x-localized-datetime :value="$tournament->tournament_end" />
                    @endif
                </div>
            </div>
        @endif

        @if($progressionSteps->isNotEmpty())
            <div class="{{ $fullWidthCell }}">
                <div class="mb-2 text-sm text-slate-400">{{ __('tournaments.details.progression') }}</div>
                <div class="flex flex-wrap items-center gap-2 lg:block lg:space-y-2">
                    @foreach($progressionSteps as $progressionStep)
                        <div class="font-bold text-white">{{ $progressionStep }}</div>
                        @unless($loop->last)
                            <div class="text-sm font-bold leading-none text-pink-400 lg:my-2">
                                <span class="lg:hidden">&rarr;</span>
                                <span class="hidden lg:inline">&darr;</span>
                            </div>
                        @endunless
                    @endforeach
                </div>
            </div>
        @endif

        @if($tournament->team_formation_style !== \App\Models\Tournament::TEAM_FORMATION_STANDARD)
            <div class="{{ $summaryCell }}">
                <div class="mb-1 text-xs text-slate-400 sm:text-sm">{{ __('tournaments.details.team_formation') }}</div>
                <div class="font-bold text-white">{{ $tournament->team_formation_style_label }}</div>
            </div>
        @endif

        <div class="{{ $summaryCell }}">
            <div class="mb-1 text-xs text-slate-400 sm:text-sm">{{ __('tournaments.details.rank_range') }}</div>
            <div class="font-bold text-white">
                {{ $tournament->rank_range }}
                @if($tournament->is_bws)
                    <div class="text-sm text-pink-400">BWS</div>
                @endif
            </div>
            @foreach($tournament->modes_with_details as $modeDetail)
                @if($modeDetail['mode'] === 'mania' && $modeDetail['key_count'])
                    <div class="mt-1 text-sm text-slate-500">
                        &bull; osu!mania ({{ $modeDetail['key_count'] }}K)
                    </div>
                @endif
            @endforeach
        </div>

        @if($tournament->vs_size || $tournament->team_size_display)
            <div class="{{ $summaryCell }}">
                <div class="mb-1 text-xs text-slate-400 sm:text-sm">{{ __('tournaments.details.format') }}</div>
                <div class="font-bold text-white">
                    @if($tournament->vs_size)
                        {{ $tournament->vs_size }}v{{ $tournament->vs_size }}
                        @if($tournament->team_size_min && $tournament->team_size_max)
                            @if($tournament->team_size_min === $tournament->team_size_max)
                                / TS{{ $tournament->team_size_min }}
                            @else
                                / TS{{ $tournament->team_size_min }}~{{ $tournament->team_size_max }}
                            @endif
                        @endif
                    @else
                        {{ $tournament->team_size_display }}
                    @endif
                </div>
            </div>
        @endif

        @if($tournament->star_rating_display)
            <div class="{{ $summaryCell }}">
                <div class="mb-1 text-xs text-slate-400 sm:text-sm">{{ __('tournaments.details.star_rating') }}</div>
                <div class="font-bold text-yellow-400">{{ $tournament->star_rating_display }}</div>
            </div>
        @endif

        @if($tournament->is_badge && $tournament->isEnded() && $tournament->badge_status)
            <div class="{{ $summaryCell }}">
                <div class="mb-1 text-xs text-slate-400 sm:text-sm">{{ __('tournaments.details.badge_approval') }}</div>
                <div class="font-bold @if($tournament->badge_status === 'approved') text-green-400 @elseif($tournament->badge_status === 'rejected') text-red-400 @else text-yellow-400 @endif">
                    @if($tournament->badge_status === 'approved')
                        {{ __('tournaments.details.badge_approved') }}
                    @elseif($tournament->badge_status === 'rejected')
                        {{ __('tournaments.details.badge_rejected') }}
                    @else
                        {{ __('tournaments.details.badge_pending') }}
                    @endif
                </div>
            </div>
        @endif

        @if($tournament->is_bws)
            <div class="col-span-2 rounded-lg border border-purple-500/30 bg-purple-500/10 p-4 lg:mb-4">
                <div class="mb-2 text-sm font-bold text-purple-400">{{ __('tournaments.details.bws') }}</div>
                <div class="grid grid-cols-3 gap-2 text-xs text-slate-400 lg:block lg:space-y-1">
                    <div>Base Exponent: <span class="block text-white lg:inline">{{ $tournament->bws_base_exponent ?? '0.9937' }}</span></div>
                    <div>Badge Power: <span class="block text-white lg:inline">{{ $tournament->bws_badge_power ?? '2.0' }}</span></div>
                    <div>Divisor: <span class="block text-white lg:inline">{{ $tournament->bws_divisor ?? '1.0' }}</span></div>
                </div>
            </div>
        @endif
    </div>
</div>
