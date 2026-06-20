@extends('layouts.app')

@php
    $bannerUrl = $tournament->cached_banner_url;
    $bannerHost = $bannerUrl ? parse_url($bannerUrl, PHP_URL_HOST) : null;
    $bannerScheme = $bannerUrl ? parse_url($bannerUrl, PHP_URL_SCHEME) : null;
    $isExternalBanner = $bannerHost && in_array($bannerScheme, ['http', 'https'], true) && $bannerHost !== request()->getHost();
    $bannerOrigin = $isExternalBanner ? "{$bannerScheme}://{$bannerHost}" : null;
    $metaTitle = "{$tournament->title} - tournament info";
    $tournamentYear = $tournament->tournament_end?->year ?? $tournament->tournament_start?->year;
    $formattedModes = $tournament->formatted_modes->filter()->implode(', ');
    $hostSummary = $tournament->host_username ? "Hosted by {$tournament->host_username}" : null;

    if ($hostSummary && $formattedModes !== '') {
        $hostSummary .= " ({$formattedModes})";
    } elseif (! $hostSummary && $formattedModes !== '') {
        $hostSummary = $formattedModes;
    }

    $metaDescription = collect([
        $tournamentYear,
        $hostSummary,
    ])->filter(fn ($value) => $value !== null && $value !== '')->implode(' · ');
    $progressionSteps = $tournament->progression_steps;

    $registrationClosesIn = null;
    if ($tournament->registration_end && $tournament->registration_end->isFuture()) {
        $diff = now()->diff($tournament->registration_end);
        if ($diff->days <= 7) {
            if ($diff->days > 0) {
                $registrationClosesIn = "{$diff->days}d {$diff->h}h";
            } elseif ($diff->h > 0) {
                $registrationClosesIn = "{$diff->h}h {$diff->i}m";
            } else {
                $registrationClosesIn = "{$diff->i}m";
            }
        }
    }

    $isRegistrationOpen = $tournament->registration_start &&
                          $tournament->registration_end &&
                          now()->between($tournament->registration_start, $tournament->registration_end);

    $isRegistrationFuture = $tournament->registration_start && $tournament->registration_start->isFuture();
    $registrationOpensIn = null;
    if ($isRegistrationFuture) {
        $diff = now()->diff($tournament->registration_start);
        if ($diff->days > 0) {
            $registrationOpensIn = "{$diff->days}d";
        } elseif ($diff->h > 0) {
            $registrationOpensIn = "{$diff->h}h";
        } else {
            $registrationOpensIn = "{$diff->i}m";
        }
    }
@endphp

@section('title', $metaTitle)

@section('meta')
    @if($bannerUrl)
        @if($bannerOrigin)
            <link rel="preconnect" href="{{ $bannerOrigin }}">
            <link rel="dns-prefetch" href="{{ $bannerOrigin }}">
        @endif
        <link rel="preload" as="image" href="{{ $bannerUrl }}">
    @endif

    {{-- SEO Meta Tags --}}
    <meta name="description" content="{{ $metaDescription }}">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="Tourney Method">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $bannerUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('tournaments.show', $tournament) }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $bannerUrl }}">

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ route('tournaments.show', $tournament) }}">
@endsection

@section('content')
<div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950">
    {{-- Hero Banner --}}
    <div class="relative h-96 overflow-hidden border-b-4 border-pink-500/50 shadow-2xl shadow-pink-500/20">
        @if($tournament->banner_url)
            <img
                src="{{ $bannerUrl }}"
                alt="{{ $tournament->title }}"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                class="w-full h-full object-cover">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-pink-500/30 via-purple-500/30 to-cyan-500/30"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent"></div>

        {{-- Title Overlay --}}
        <div class="absolute bottom-0 left-0 right-0 p-8">
            <div class="container">
                <div class="tournament-detail-hero-badges mb-4">
                    @foreach($tournament->modes_with_details as $modeDetail)
                        <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" />
                    @endforeach

                    @if($tournament->is_badge)
                        <span class="tournament-card-status-badge">
                            {{ __('tournaments.status.badged') }}
                        </span>
                    @endif

                    @if($registrationClosesIn)
                        <span class="tournament-card-status-badge-closing">
                            {{ __('tournaments.status.closes_in', ['time' => $registrationClosesIn]) }}
                        </span>
                    @elseif($isRegistrationOpen)
                        <span class="tournament-card-status-badge-open">
                            {{ __('tournaments.status.registration_open') }}
                        </span>
                    @elseif($tournament->is_ongoing)
                        <span class="tournament-card-status-badge-ongoing">
                            {{ __('tournaments.status.ongoing') }}
                        </span>
                    @endif

                    @if($tournament->registration_closed_but_not_started)
                        <span class="tournament-card-status-badge-closed">
                            {{ __('tournaments.status.registration_closed') }}
                        </span>
                    @endif

                    @if($isRegistrationFuture && $registrationOpensIn)
                        <span class="tournament-card-status-badge-future">
                            {{ __('tournaments.status.opens_in', ['time' => $registrationOpensIn]) }}
                        </span>
                    @endif
                </div>

                <h1 class="font-tournament text-4xl md:text-5xl lg:text-6xl font-black text-white mb-2 tracking-tight">
                    {{ $tournament->title }}
                </h1>

                @if($tournament->host && $tournament->host->exists)
                    <p class="text-lg text-slate-300 font-medium">
                        {{ __('tournaments.contents.hosted') }}
                        <a href="{{ route('users.show', $tournament->host->id) }}"
                           class="text-pink-400 font-bold hover:text-pink-300 hover:underline transition-all">
                            {{ $tournament->host_username }}
                        </a>
                    </p>
                @elseif($tournament->host_username)
                    <p class="text-lg text-slate-300 font-medium">
                        Hosted by <span class="text-pink-400 font-bold">{{ $tournament->host_username }}</span>
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="container py-12">
        {{-- Back Navigation --}}
        <div class="-mt-4 mb-8 flex justify-between items-center">
            <a id="back-to-tournaments-btn"
               href="{{ session('tournaments_referrer_url', route('tournaments.index')) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
                      bg-slate-800/50 backdrop-blur-sm border-2 border-slate-700/50
                      text-slate-300 font-bold
                      hover:bg-slate-700/50 hover:border-pink-500/50 hover:text-pink-400
                      transition-all duration-300 group">
                <x-icon name="lucide-arrow-left" class="w-5 h-5 group-hover:-translate-x-1 transition-transform" />
                <span class="hidden sm:inline">{{ __('tournaments.contents.back_full') }}</span>
                <span class="sm:hidden">{{ __('tournaments.contents.back') }}</span>
            </a>

            <div data-tournament-actions class="flex items-center gap-2">
                @if($tournament->registration_status === 'open')
                    <x-watch-button :tournament="$tournament" :show-watching="true" />
                @elseif($tournament->ongoing_status === 'ongoing')
                    <x-watch-button :tournament="$tournament" :show-watching="false" />
                @endif

                @auth
                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'master')
                        <a href="{{ route('admin.tournaments.show', $tournament->id) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
                                  bg-pink-500/20 backdrop-blur-sm border-2 border-pink-500/50
                                  text-pink-400 font-bold
                                  hover:bg-pink-500/30 hover:border-pink-400 hover:text-pink-300
                                  transition-all duration-300 group">
                            <x-icon name="lucide-code-2" class="w-5 h-5 group-hover:scale-110 transition-transform" />
                            {{ __('tournaments.contents.admin') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>

        {{-- Winners Section --}}
        @if($tournament->isEnded() && ($podiumSections->isNotEmpty() || $currentUserResultTeam))
        <div class="mb-6 sm:mb-8" data-tournament-section="results">
            <x-tournament-podium
                :tournament="$tournament"
                :podium-sections="$podiumSections"
                :current-user-result-team="$currentUserResultTeam"
            />
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="space-y-6 lg:col-span-2 lg:space-y-8">
                {{-- Eligibility Banner - Hide after registration ends --}}
                @auth
                    @if(!$tournament->isRegistrationEnded() && $userEligible !== null)
                        <div class="rounded-2xl p-6 border-2 {{ $userEligible ? 'bg-green-500/10 border-green-500' : 'bg-red-500/10 border-red-500' }}">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full {{ $userEligible ? 'bg-green-500' : 'bg-red-500' }} flex items-center justify-center flex-shrink-0">
                                    @if($userEligible)
                                        <x-icon name="lucide-check" class="w-6 h-6 text-white" />
                                    @else
                                        <x-icon name="lucide-circle-x" class="w-6 h-6 text-white" />
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold {{ $userEligible ? 'text-green-400' : 'text-red-400' }}">
                                        @if($userEligible)
                                            {{ __('tournaments.eligibility.eligible') }}
                                        @elseif($ineligibleReason === 'country')
                                            {{ __('tournaments.eligibility.region_restricted') }}
                                        @else
                                            {{ __('tournaments.eligibility.not_eligible') }}
                                        @endif
                                    </h3>
                                    <p class="text-sm {{ $userEligible ? 'text-green-300' : 'text-red-300' }}">
                                        @if($userEligible)
                                            @if($userRankValue)
                                                {{ __('tournaments.eligibility.rankvalue', ['rank' => $tournament->is_bws && $userBwsRank ? 'BWS #' . number_format($userBwsRank, 2) . ' (raw: #' . number_format($userRawRank) . ')' : '#' . number_format($userRankValue)]) }}
                                            @endif
                                        @elseif($ineligibleReason === 'country')
                                            {!! __('tournaments.eligibility.reason_country', ['countries' => '<span class="font-bold">' . e($tournament->restricted_country_names->join(', ')) . '</span>' ]) !!}
                                        @elseif($ineligibleReason === 'rank')
                                            @if($userRankValue)
                                                {{ __('tournaments.eligibility.reason_rank', ['rank' => $tournament->is_bws && $userBwsRank ? 'BWS #' . number_format($userBwsRank, 2) . ' (raw: #' . number_format($userRawRank) . ')' : '#' . number_format($userRankValue), 'range' => $tournament->rank_range]) }}
                                            @else
                                                {{ __('tournaments.eligibility.reason_other') }}
                                            @endif
                                        @else
                                            {{ __('tournaments.eligibility.reason_other') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endauth

                {{-- External Links --}}
                @if($tournament->discord_url || $tournament->twitch_url || $tournament->spreadsheet_url || $tournament->bracket_url || $tournament->registration_url || $tournament->forum_post_url || $tournament->tcomm_url)
                    <div data-tournament-section="links" class="rounded-2xl border-2 border-slate-700/50 bg-slate-800/50 p-4 sm:p-8">
                        <h2 class="mb-4 flex items-center gap-3 text-xl font-bold text-white sm:mb-6 sm:text-2xl">
                            <div class="w-1 h-8 bg-gradient-to-b from-pink-500 to-purple-500 rounded-full"></div>
                            {{ __('tournaments.externals.title') }}
                        </h2>
                        <div data-mobile-link-grid class="grid grid-cols-4 gap-2 sm:grid-cols-2 sm:gap-4">
                            @if($tournament->forum_post_url)
                                <a href="{{ $tournament->forum_post_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.forum') }}"
                                    title="{{ __('tournaments.externals.forum') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-blue-500/50 bg-blue-500/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-blue-500/20 hover:border-blue-500 hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="lucide-message-circle" class="w-6 h-6 text-blue-400" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-blue-400 sm:inline">{{ __('tournaments.externals.forum') }}</span>
                                </a>
                            @endif

                            @if($tournament->spreadsheet_url)
                                <a href="{{ $tournament->spreadsheet_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.sheet') }}"
                                    title="{{ __('tournaments.externals.sheet') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-green-500/50 bg-green-500/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-green-500/20 hover:border-green-500 hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="si-googlesheets" class="w-6 h-6 text-green-400" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-green-400 sm:inline">{{ __('tournaments.externals.sheet') }}</span>
                                </a>
                            @endif

                            @if(!$tournament->isRegistrationEnded() && $tournament->registration_url)
                                <a href="{{ $tournament->registration_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.registration') }}"
                                    title="{{ __('tournaments.externals.registration') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-pink-500/50 bg-pink-500/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-pink-500/20 hover:border-pink-500 hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="lucide-user-plus" class="w-6 h-6 text-pink-400" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-pink-400 sm:inline">{{ __('tournaments.externals.registration') }}</span>
                                </a>
                            @endif

                            @if($tournament->discord_url)
                                <a href="{{ $tournament->discord_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.discord') }}"
                                    title="{{ __('tournaments.externals.discord') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-[#5865F2]/50 bg-[#5865F2]/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-[#5865F2]/20 hover:border-[#5865F2] hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="si-discord" class="w-6 h-6 text-[#5865F2]" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-[#5865F2] sm:inline">{{ __('tournaments.externals.discord') }}</span>
                                </a>
                            @endif

                            @if($tournament->twitch_url)
                                <a href="{{ $tournament->twitch_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.twitch') }}"
                                    title="{{ __('tournaments.externals.twitch') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-[#9146FF]/50 bg-[#9146FF]/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-[#9146FF]/20 hover:border-[#9146FF] hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="si-twitch" class="w-6 h-6 text-[#9146FF]" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-[#9146FF] sm:inline">{{ __('tournaments.externals.twitch') }}</span>
                                </a>
                            @endif

                            @if($tournament->bracket_url)
                                <a href="{{ $tournament->bracket_url }}" target="_blank" rel="noopener"
                                    aria-label="{{ __('tournaments.externals.challonge') }}"
                                    title="{{ __('tournaments.externals.challonge') }}"
                                    class="flex aspect-square items-center justify-center rounded-xl border-2 border-orange-500/50 bg-orange-500/10 p-3 sm:aspect-auto sm:justify-start sm:gap-3 sm:p-4
                                        hover:bg-orange-500/20 hover:border-orange-500 hover:scale-105
                                        transition-all duration-300 group">
                                    <x-icon name="lucide-chart-column" class="w-6 h-6 text-orange-400" />
                                    <span class="hidden font-bold text-white transition-colors group-hover:text-orange-400 sm:inline">{{ __('tournaments.externals.challonge') }}</span>
                                </a>
                            @endif

                        </div>
                    </div>
                @endif

                <div class="lg:hidden">
                    <x-tournament-details
                        :tournament="$tournament"
                        :progression-steps="$progressionSteps"
                        section="details-mobile"
                    />
                </div>

                {{-- Tournament Staff Section --}}
                <x-tournament-staff-section :tournament="$tournament" />
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <div class="hidden lg:block">
                    <x-tournament-details
                        :tournament="$tournament"
                        :progression-steps="$progressionSteps"
                        section="details-desktop"
                    />
                </div>
            </div>
        </div>

        <section class="mt-10 rounded-2xl bg-slate-800/50 border-2 border-slate-700/50 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-white">{{ __('tournaments.corrections.entry.title') }}</h2>
                </div>
                <div class="flex flex-wrap gap-3">
                    @auth
                        @if($pendingCorrection)
                            <span class="inline-flex items-center rounded-lg border border-yellow-400/40 bg-yellow-500/10 px-4 py-2 text-sm font-semibold text-yellow-200">
                                {{ __('tournaments.corrections.entry.pending', ['id' => $pendingCorrection->id]) }}
                            </span>
                        @else
                            <a href="{{ route('tournaments.corrections.create', $tournament) }}" class="rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                {{ __('tournaments.corrections.entry.correct_tournament') }}
                            </a>
                        @endif
                        <a href="{{ route('tournaments.corrections.history', $tournament) }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                            {{ __('tournaments.corrections.entry.history', ['count' => $approvedCorrectionCount]) }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-pink-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                            {{ __('tournaments.corrections.entry.login_to_correct') }}
                        </a>
                    @endauth
                </div>
            </div>

            <div class="mt-5">
                @if($correctionContributors->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($correctionContributors as $contributor)
                            <a href="{{ route('users.show', $contributor) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 hover:border-pink-500 hover:text-pink-300">
                                <img src="{{ $contributor->avatar_url }}" alt="{{ $contributor->username }}" class="h-6 w-6 rounded-full">
                                {{ $contributor->username }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">{{ __('tournaments.corrections.entry.no_approved') }}</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('styles')
<style>
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-display);
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the back button
        const backButton = document.getElementById('back-to-tournaments-btn');

        if (backButton) {
            // Check if we have a stored referrer from tournaments list
            const storedReferrer = localStorage.getItem('tournamentsReferrer');

            if (storedReferrer) {
                console.log('Found stored referrer:', storedReferrer);

                // Update the back button href
                backButton.href = storedReferrer;
            } else {
                console.log('No stored referrer, using default');
            }

            backButton.addEventListener('click', function() {
                localStorage.removeItem('tournamentsReferrer');
            });
        }
    });
</script>
@endpush
