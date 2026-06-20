@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8 animate-fade-in">
    {{-- Welcome Banner --}}
    <section class="relative overflow-hidden rounded-2xl bg-dark-850 border border-dark-700">
        <div class="relative p-8 md:p-10 flex flex-col md:flex-row items-center gap-6">
            {{-- Avatar with Status Ring --}}
            <div class="relative group">
                <a href="{{ route('users.show', $user->id) }}" class="relative block transition-all hover:opacity-80">
                    <img
                        src="https://a.ppy.sh/{{ $user->osu_id }}"
                        alt="{{ $user->username }}"
                        class="relative w-24 h-24 md:w-28 md:h-28 rounded-full border-4 border-dark-800 hover:border-osu-pink object-cover transition-all"
                    >
                </a>
            </div>

            {{-- Welcome Text --}}
            <div class="text-center md:text-left flex-1">

                <h1 class="text-3xl md:text-4xl font-tournament font-bold text-white mb-2">
                    <a href="{{ route('users.show', $user->id) }}" class="hover:text-osu-pink transition-colors">
                        {{ $user->username }}
                    </a>
                    @if($user->country_code)
                        <img src="https://flagcdn.com/w40/{{ strtolower($user->country_code) }}.png"
                             alt="{{ $user->country_code }}"
                             class="inline-block w-6 h-4 ml-2 rounded shadow-sm"
                             title="{{ $user->country_code }}">
                    @endif
                </h1>
                <div class="flex flex-wrap justify-center md:justify-start gap-2 mt-3">
                    @if($user->role !== 'player')
                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide
                            {{ $user->role === 'admin' ? 'bg-red-500/20 text-red-400' :
                               ($user->role === 'master' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-slate-700/50 text-slate-400') }}">
                            {{ $user->role }}
                        </span>
                    @endif
                    <x-gamemode-badge :mode="$user->main_mode" :label="$user->formatted_main_mode" size="pill" />
                </div>
            </div>

            {{-- Quick Actions --}}
            {{-- <div class="flex flex-col gap-2">
                <a href="{{ route('tournaments.index', ['eligible_only' => true]) }}" class="btn-primary text-center text-sm">
                    Find Tournaments
                </a>
                <a href="{{ route('matches.add') }}" class="btn-secondary text-center text-sm">
                    Add Match
                </a>
                <a href="{{ route('users.show', auth()->user()) }}" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-lg text-sm transition-all duration-200 shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 text-center py-2.5 px-4">
                    View My Profile
                </a>
            </div> --}}
        </div>
    </section>

    {{-- Stats Grid --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Current Rank --}}
        <div class="card group hover:border-osu-pink/50 transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">
                        {{ __('dashboard.stats.global_rank') }}
                    </p>
                    <p class="text-2xl md:text-3xl font-display font-bold text-white">
                        @if($user->main_mode === 'mania')
                            {{-- For mania: show 4K and 7K as main text --}}
                            @if($mania4kRank && $mania7kRank)
                                4K: #{{ number_format($mania4kRank) }}<br>7K: #{{ number_format($mania7kRank) }}
                            @else
                                <span class="text-gray-600">--</span>
                            @endif
                        @elseif($rankHistory?->rank)
                            #{{ number_format($rankHistory->rank) }}
                        @else
                            <span class="text-gray-600">--</span>
                        @endif
                    </p>
                </div>
                <div class="p-2 bg-osu-pink/10 rounded-lg text-osu-pink group-hover:bg-osu-pink/20 transition-colors">
                    <x-icon name="lucide-globe" class="w-6 h-6" />
                </div>
            </div>
            @if($user->main_mode === 'mania' && $rankHistory?->rank)
                {{-- For mania: show main mania rank as secondary text --}}
                <p class="text-xs text-gray-500 mt-2">
                    {{ __('dashboard.stats.maina_global_rank') }}: #{{ number_format($rankHistory->rank) }}
                </p>
            @elseif($rankHistory?->country_rank && $user->main_mode !== 'mania')
                {{-- For other modes: show country rank --}}
                <p class="text-xs text-gray-500 mt-2">
                    {{ __('dashboard.stats.country_rank') }}: #{{ number_format($rankHistory->country_rank) }}
                </p>
            @endif
        </div>

        {{-- PP --}}
        <div class="card group hover:border-osu-cyan/50 transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">{{ __('dashboard.stats.performance') }}</p>
                    <p class="text-2xl md:text-3xl font-display font-bold text-white">
                        @if($user->main_mode === 'mania')
                            {{-- For mania: show 4K and 7K PP as main text --}}
                            @if($mania4kPP && $mania7kPP)
                                4K: {{ number_format($mania4kPP) }}<span class="text-lg text-osu-cyan">pp</span><br>7K: {{ number_format($mania7kPP) }}<span class="text-lg text-osu-cyan">pp</span>
                            @elseif($rankHistory?->pp)
                                {{-- Fallback to main mania PP if variants not available --}}
                                {{ number_format($rankHistory->pp) }}<span class="text-lg text-osu-cyan">pp</span>
                            @else
                                <span class="text-gray-600">--</span>
                            @endif
                        @elseif($rankHistory?->pp)
                            {{ number_format($rankHistory->pp) }}<span class="text-lg text-osu-cyan">pp</span>
                        @else
                            <span class="text-gray-600">--</span>
                        @endif
                    </p>
                </div>
                <div class="p-2 bg-osu-cyan/10 rounded-lg text-osu-cyan group-hover:bg-osu-cyan/20 transition-colors">
                    <x-icon name="lucide-trending-up" class="w-6 h-6" />
                </div>
            </div>
            @if($user->main_mode === 'mania' && $rankHistory?->pp)
                {{-- For mania: show main mania PP as secondary text --}}
                <p class="text-xs text-gray-500 mt-2">
                    {{ __('dashboard.stats.maina_global_rank') }}: {{ number_format($rankHistory->pp) }}pp
                </p>
            @endif
        </div>

        {{-- Badges This Year --}}
        <div class="card group hover:border-yellow-500/50 transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">{{ __('dashboard.stats.badges_this_year', ['year' => now()->year]) }}</p>
                    <p class="text-2xl md:text-3xl font-display font-bold text-white">
                        {{ $badgeCountThisYear }}
                    </p>
                </div>
                <div class="p-2 bg-yellow-500/10 rounded-lg text-yellow-400 group-hover:bg-yellow-500/20 transition-colors">
                    <x-icon name="lucide-star" class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Total Badges --}}
        <div class="card group hover:border-purple-500/50 transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">{{ __('dashboard.stats.bws_badges') }}</p>
                    <p class="text-2xl md:text-3xl font-display font-bold text-white">
                        {{ $bwsBadgeCount }}
                    </p>
                </div>
                <div class="p-2 bg-purple-500/10 rounded-lg text-purple-400 group-hover:bg-purple-500/20 transition-colors">
                    <x-icon name="lucide-trophy" class="w-6 h-6" />
                </div>
            </div>
        </div>
    </section>

    {{-- Rank Milestones --}}
    @if($rankMilestones)
    <section class="card">
        <div class="flex items-center gap-3 mb-6">
            <div>
                <h2 class="text-lg font-display font-bold text-white">{{ __('dashboard.dashboard.rank_milestones') }}</h2>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            {{-- Rank 100 --}}
            <div class="relative p-4 rounded-xl bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border border-yellow-500/20 group hover:border-yellow-500/40 transition-all">
                <div class="absolute top-2 right-2 opacity-20 group-hover:opacity-40 transition-opacity">
                    <x-icon name="lucide-award" class="w-8 h-8 text-yellow-400" />
                </div>
                <p class="text-yellow-400/80 text-xs font-semibold uppercase tracking-wider mb-1">Top 100</p>
                <p class="text-2xl font-display font-bold text-white">
                    @if($rankMilestones['pp_at_100'] ?? null)
                        {{ number_format($rankMilestones['pp_at_100']) }}<span class="text-sm text-yellow-400/60 ml-1">pp</span>
                    @else
                        <span class="text-gray-600 text-lg">{{ __('dashboard.dashboard.loading') }}</span>
                    @endif
                </p>
            </div>

            {{-- Rank 1000 --}}
            <div class="relative p-4 rounded-xl bg-gradient-to-br from-slate-400/10 to-slate-500/10 border border-slate-400/20 group hover:border-slate-400/40 transition-all">
                <div class="absolute top-2 right-2 opacity-20 group-hover:opacity-40 transition-opacity">
                    <x-icon name="lucide-award" class="w-8 h-8 text-slate-400" />
                </div>
                <p class="text-slate-400/80 text-xs font-semibold uppercase tracking-wider mb-1">Top 1,000</p>
                <p class="text-2xl font-display font-bold text-white">
                    @if($rankMilestones['pp_at_1000'] ?? null)
                        {{ number_format($rankMilestones['pp_at_1000']) }}<span class="text-sm text-slate-400/60 ml-1">pp</span>
                    @else
                        <span class="text-gray-600 text-lg">{{ __('dashboard.dashboard.loading') }}</span>
                    @endif
                </p>
            </div>

            {{-- Rank 10000 --}}
            <div class="relative p-4 rounded-xl bg-gradient-to-br from-amber-700/10 to-orange-700/10 border border-amber-700/20 group hover:border-amber-700/40 transition-all">
                <div class="absolute top-2 right-2 opacity-20 group-hover:opacity-40 transition-opacity">
                    <x-icon name="lucide-award" class="w-8 h-8 text-amber-600" />
                </div>
                <p class="text-amber-600/80 text-xs font-semibold uppercase tracking-wider mb-1">Top 10,000</p>
                <p class="text-2xl font-display font-bold text-white">
                    @if($rankMilestones['pp_at_10000'] ?? null)
                        {{ number_format($rankMilestones['pp_at_10000']) }}<span class="text-sm text-amber-600/60 ml-1">pp</span>
                    @else
                        <span class="text-gray-600 text-lg">{{ __('dashboard.dashboard.loading') }}</span>
                    @endif
                </p>
            </div>
        </div>
    </section>
    @endif

    {{-- Currently Running Tournaments --}}
    <section>
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="p-1.5 bg-green-500/20 rounded-lg">
                    <x-icon name="lucide-sport-shoe" class="w-5 h-5 text-green-500" />
                </div>
                <h2 class="text-xl font-display font-bold text-white">{{ __('dashboard.dashboard.currently_running') }}</h2>
            </div>
            <a href="{{ route('tournaments.index', ['status' => 'active']) }}" class="text-sm text-osu-pink hover:text-osu-pink/80 font-medium transition-colors">
                {{ __('dashboard.dashboard.view_all') }}
                <x-icon name="lucide-chevron-right" class="inline w-4 h-4 ml-1" />
            </a>
        </div>

        @if($currentlyRunning->count() > 0)
            <x-tournament-carousel :tournaments="$currentlyRunning" id="currently-running" />
        @else
            <div class="card text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-dark-700 flex items-center justify-center">
                    <x-icon name="lucide-inbox" class="w-8 h-8 text-gray-500" />
                </div>
                <h3 class="text-lg font-display font-semibold text-white mb-2">{{ __('dashboard.dashboard.no_running_tournaments') }}</h3>
            </div>
        @endif
    </section>

    {{-- Registration Open Tournaments --}}
    <section>
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="p-1.5 bg-osu-cyan/20 rounded-lg">
                    <x-icon name="lucide-pencil" class="w-5 h-5 text-osu-cyan" />
                </div>
                <h2 class="text-xl font-display font-bold text-white">{{ __('dashboard.dashboard.registration_open') }}</h2>
            </div>
            <a href="{{ route('tournaments.index', ['mode' => $user->main_mode, 'eligible' => true, 'reg_open' => true]) }}" class="text-sm text-osu-cyan hover:text-osu-cyan/80 font-medium transition-colors">
                {{ __('dashboard.dashboard.view_all') }}
                <x-icon name="lucide-chevron-right" class="inline w-4 h-4 ml-1" />
            </a>
        </div>

        @if($registrationOpen->count() > 0)
            <x-tournament-carousel :tournaments="$registrationOpen" id="registration-open" />
        @else
            <div class="card text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-dark-700 flex items-center justify-center">
                    <x-icon name="lucide-inbox" class="w-8 h-8 text-gray-500" />
                </div>
                <h3 class="text-lg font-display font-semibold text-white mb-2">{{ __('dashboard.dashboard.no_open_registrations') }}</h3>
            </div>
        @endif
    </section>

    {{-- Last Week's Results --}}
    <x-last-week-results
        :tournaments="$lastWeekResults"
        :startDate="$lastWeekStart"
        :endDate="$lastWeekEnd"
    />
</div>
@endsection
