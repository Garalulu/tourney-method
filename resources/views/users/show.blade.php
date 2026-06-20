@extends('layouts.app')

@php
    $metaTitle = "{$user->username} - player info";
    $hasRecentStaffRoles = $user->hasRecentStaffRoles();
    $hasRecentParticipationRecords = $user->hasRecentParticipationRecords();
    $profileLabels = collect([
        $hasRecentStaffRoles ? 'Staff' : null,
        $hasRecentParticipationRecords ? 'Player' : null,
    ])->filter();
    $profileSummary = $profileLabels->implode('/');

    if ($user->formatted_main_mode) {
        $profileSummary = $profileSummary !== ''
            ? "{$profileSummary} ({$user->formatted_main_mode})"
            : $user->formatted_main_mode;
    }

    $badgeCount = $user->badges->count();
    $metaDescription = collect([
        $profileSummary,
        "{$badgeCount} " . Str::plural('badge', $badgeCount),
    ])->filter()->implode(' - ');
    $profileUrl = route('users.show', $user);
@endphp

@section('title', $metaTitle)

@section('meta')
    <meta name="description" content="{{ $metaDescription }}">

    <meta property="og:site_name" content="Tourney Method">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $user->avatar_url }}">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="{{ $profileUrl }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $user->avatar_url }}">

    <link rel="canonical" href="{{ $profileUrl }}">
@endsection

@section('content')
@php
    $isOwnProfile = auth()->id() === $user->id;
@endphp
<div class="min-h-screen bg-slate-900"
     x-data="profileTabs('{{ request()->query('tab', $isOwnProfile ? 'participation' : 'history') }}')">

    <!-- Breadcrumb -->
    <nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="/" class="text-slate-400 hover:text-pink-400 transition-colors">
                    <x-icon name="lucide-home" class="w-4 h-4" />
                </a>
            </li>
            <li class="text-slate-600">/</li>
            <li>
                <!-- a href="{{ route('tournaments.index') }}" class="text-slate-400 hover:text-pink-400 transition-colors">Users</a-->
                Users
            </li>
            <li class="text-slate-600">/</li>
            <li class="text-pink-400 font-medium">{{ $user->username }}</li>
        </ol>
    </nav>

    <!-- Profile Header Card -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-ui.panel variant="muted" class="relative rounded-2xl">
            <div class="relative px-6 py-8 sm:px-8 sm:py-10">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <!-- Avatar -->
                    <div class="relative flex-shrink-0">
                        <a href="https://osu.ppy.sh/users/{{ $user->osu_id }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="group block">
                            <div class="w-48 h-48 rounded-full border-4 border-pink-500/20 shadow-2xl shadow-pink-500/20 overflow-hidden bg-slate-700 transition-all duration-300 group-hover:border-pink-400 group-hover:shadow-pink-400/50">
                                    <img src="https://a.ppy.sh/{{ $user->osu_id }}" alt="{{ $user->username }}"
                                         class="w-full h-full object-cover">
                            </div>
                        </a>
                    </div>

                    <!-- User Info -->
                    <div class="flex-1 text-center sm:text-left">
                        <div class="flex flex-col sm:flex-row items-center gap-3 mb-3">
                            <a href="https://osu.ppy.sh/users/{{ $user->osu_id }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="text-3xl sm:text-4xl font-bold text-white font-tournament hover:text-pink-400 transition-colors duration-200 inline-block">
                                {{ $user->username }}
                            </a>
                            <!-- Country Flag -->
                            @if($user->country_code)
                                <img src="https://flagcdn.com/w40/{{ strtolower($user->country_code) }}.png"
                                     alt="{{ $user->country_code }}"
                                     class="w-8 h-auto rounded shadow-lg">
                            @endif
                        </div>

                        <!-- Role & Tournament badges -->
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-4">
                            {{-- Global Role Badge (hide if 'player') --}}
                            @if($user->role !== 'player')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide
                                    {{ $user->role === 'admin' ? 'bg-red-500/20 text-red-400' :
                                       ($user->role === 'master' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-slate-700/50 text-slate-400') }}">
                                    {{ $user->role }}
                                </span>
                            @endif

                            {{-- STAFF Badge (green) - shows if user has recent staff roles --}}
                            @if($hasRecentStaffRoles)
                                <span class="px-3 py-1 rounded-full bg-green-500/20 text-green-400 text-xs font-semibold uppercase tracking-wide" title="Tournament Staff (last 12 months)">
                                    STAFF
                                </span>
                            @endif

                            {{-- PLAYER Badge (blue) - shows if user has recent participation records --}}
                            @if($hasRecentParticipationRecords)
                                <span class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-400 text-xs font-semibold uppercase tracking-wide" title="Tournament participation (last 12 months)">
                                    PLAYER
                                </span>
                            @endif

                            {{-- Game Mode Badge --}}
                            @if($user->main_mode)
                                <x-gamemode-badge :mode="$user->main_mode" :label="$user->formatted_main_mode" size="pill" />
                            @endif
                        </div>

                        <!-- Stats Summary -->
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-6 text-sm text-slate-400">
                            @isset($ranks[$user->main_mode ?? 'osu'])
                            @php
                                $modeRanks = $ranks[$user->main_mode ?? 'osu'] ?? [];
                                $globalRank = $modeRanks['global_rank'] ?? null;
                                $pp = $modeRanks['pp'] ?? null;
                                $maniaVariantRanks = $user->main_mode === 'mania'
                                    ? collect(['4k', '7k'])
                                        ->mapWithKeys(fn ($variant) => [$variant => $ranks[$variant] ?? []])
                                        ->filter(fn ($variantRanks) => ($variantRanks['global_rank'] ?? 0) > 0)
                                        ->sortBy('global_rank')
                                    : collect();
                            @endphp

                            {{-- Mania variants (4K, 7K) --}}
                            @foreach($maniaVariantRanks as $variant => $variantRanks)
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-trending-up" class="w-4 h-4" />
                                    <span>{{ strtoupper($variant) }}: #{{ number_format($variantRanks['global_rank']) }}</span>
                                </div>
                                @if(($variantRanks['pp'] ?? null) !== null)
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-chart-column" class="w-4 h-4" />
                                    <span>{{ number_format($variantRanks['pp'], 2) }} pp</span>
                                </div>
                                @endif
                            @endforeach

                            {{-- Main mode stats --}}
                            @if($globalRank !== null)
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-trending-up" class="w-4 h-4" />
                                    <span>{{ __('users.stats.global_rank') }}: #{{ number_format($globalRank) }}</span>
                                </div>
                            @endif
                            @if($pp !== null)
                                <div class="flex items-center gap-2">
                                    <x-icon name="lucide-chart-column" class="w-4 h-4" />
                                    <span>{{ number_format($pp, 2) }} pp</span>
                                </div>
                            @endif
                            @endisset
                            <div class="flex items-center gap-2">
                                <x-icon name="lucide-trophy" class="w-4 h-4" />
                                <span>{{ $user->badges->count() }} Badges</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.panel>
    </div>

    <!-- Tabs Navigation -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <x-ui.panel variant="muted" padding="p-2" class="flex flex-wrap gap-2">
            @if($isOwnProfile)
                @include('users.partials.participation-tab-button')
            @endif
            <button @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
                <span class="flex items-center justify-center gap-2">
                    <x-icon name="lucide-building-2" class="w-4 h-4" />
                    {{ __('users.tabs.history') }}
                </span>
            </button>
            @unless($isOwnProfile)
                @include('users.partials.participation-tab-button')
            @endunless
            <button @click="activeTab = 'badges'"
                    :class="activeTab === 'badges' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
                <span class="flex items-center justify-center gap-2">
                    <x-icon name="lucide-trophy" class="w-4 h-4" />
                    {{ __('users.tabs.badges') }}
                </span>
            </button>
            <button @click="activeTab = 'contributions'"
                    :class="activeTab === 'contributions' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
                <span class="flex items-center justify-center gap-2">
                    <x-icon name="lucide-pencil" class="w-4 h-4" />
                    {{ __('users.tabs.contributions') }}
                </span>
            </button>
            {{-- Match Statistics Button --}}
            {{-- <button @click="activeTab = 'matches'"
                    :class="activeTab === 'matches' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
                <span class="flex items-center justify-center gap-2">
                    <x-icon name="lucide-chart-column" class="w-4 h-4" />
                    {{ __('users.tabs.matches') }}
                </span>
            </button> --}}
            {{-- Year Recap Button --}}
            {{-- <button @click="activeTab = 'recap'"
                    :class="activeTab === 'recap' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
                    class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
                <span class="flex items-center justify-center gap-2">
                    <x-icon name="lucide-image" class="w-4 h-4" />
                    {{ __('users.tabs.recap') }}
                </span>
            </button> --}}
        </x-ui.panel>
    </div>

    <!-- Tab Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="relative">
            <!-- Participation -->
            <div x-show="activeTab === 'participation'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="contents">
                @include('users.partials.participation', [
                    'user' => $user,
                    'records' => $participationRecords,
                    'stats' => $participationStats,
                    'availableYears' => $participationAvailableYears,
                    'availableModes' => $participationAvailableModes,
                    'stageOptions' => $participationStageOptions,
                    'participationIndices' => $participationIndices,
                    'displayTeammatesByRecord' => $displayTeammatesByRecord,
                    'displayTeammateBwsRanksByRecord' => $displayTeammateBwsRanksByRecord,
                    'tournamentOptions' => $participationTournamentOptions,
                    'createStageOptions' => $participationCreateStageOptions,
                    'tournamentDetails' => $participationTournamentDetails,
                    'isOwnProfile' => $isOwnProfile,
                    'canManageParticipation' => $canManageParticipation,
                    'recordsUrl' => $participationRecordsUrl,
                    'recordsNextOffset' => $participationRecordsNextOffset,
                    'recordsHasMore' => $participationRecordsHasMore,
                ])
            </div>

            <!-- Tournament History -->
            <div x-show="activeTab === 'history'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="contents">
                @include('users.partials.tournament-history', ['user' => $user, 'staffRoles' => $staffRoles])
            </div>

            <!-- Badges -->
            <div x-show="activeTab === 'badges'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="contents">
                @include('users.partials.badges', ['user' => $user])
            </div>

            <!-- Contributions -->
            <div x-show="activeTab === 'contributions'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                class="contents">
                @include('users.partials.contributions', [
                    'contributionGroups' => $contributionGroups,
                    'contributionCount' => $contributionCount,
                    'contributionGroupsHasMore' => $contributionGroupsHasMore,
                    'contributionGroupsNextOffset' => $contributionGroupsNextOffset,
                    'contributionGroupsUrl' => $contributionGroupsUrl,
                ])
            </div>

            {{-- Match Statistics Content --}}
            {{-- <div x-show="activeTab === 'matches'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="contents">
                @include('users.partials.match-records')
            </div> --}}

            {{-- Year Recap Content --}}
            {{-- <div x-show="activeTab === 'recap'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="contents">
                @include('users.partials.year-recap', ['user' => $user])
            </div> --}}
        </div>
    </div>
</div>

<script>
function profileTabs(initialTab) {
    return {
        activeTab: initialTab,
        init() {
            // Update URL on tab change without full reload
            this.$watch('activeTab', (value) => {
                const url = new URL(window.location);
                url.searchParams.set('tab', value);
                window.history.pushState({}, '', url);
            });
        }
    }
}
</script>
@endsection
