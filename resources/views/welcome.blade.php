@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
<!-- Hero Section -->
<div class="hero-section">
    <div class="max-w-4xl mx-auto px-6 text-center">
        <h1 class="hero-title">
            {{ __('home.hero.title') }}
        </h1>

        <p class="hero-subtitle">
            {{ __('home.hero.subtitle') }}
        </p>

        <!-- Action Boxes -->
        <div class="grid md:grid-cols-2 gap-4 max-w-3xl mx-auto">
            @guest
                <a href="{{ route('tournaments.index') }}" class="action-card-cyan group">
                    <h2 class="action-card-title">{{ __('home.guest_actions.browse_tournaments') }}</h2>
                    <p class="action-card-description">{{ __('home.guest_actions.browse_tournaments_desc') }}</p>
                </a>

                <a href="{{ route('login') }}" class="action-card-pink group">
                    <h2 class="action-card-title-pink">{{ __('home.guest_actions.login_to_search') }}</h2>
                    <p class="action-card-description">{{ __('home.guest_actions.login_to_search_desc') }}</p>
                </a>
            @else
                <a href="{{ route('tournaments.index') }}" class="action-card-cyan group">
                    <h2 class="action-card-title">{{ __('home.auth_actions.browse_tournaments') }}</h2>
                    <p class="action-card-description">{{ __('home.auth_actions.browse_tournaments_desc') }}</p>
                </a>

                <div class="action-card-disabled">
                    <h2 class="action-card-disabled-title">{{ __('home.auth_actions.already_logged_in') }}</h2>
                    <p class="action-card-disabled-description">{{ __('home.auth_actions.already_logged_in_desc') }}</p>
                </div>
            @endguest
        </div>
    </div>
</div>

<!-- Tournament Discovery Section -->
<div class="info-section">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">{{ __('home.sections.tournament_discovery.title') }}</h2>
        <p class="info-section-content">
            {{ __('home.sections.tournament_discovery.content') }}
        </p>
    </div>
</div>

<!-- Tournament History Section -->
<div class="info-section-alt">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">{{ __('home.sections.tournament_history.title') }}</h2>
        <p class="info-section-content">
            {{ __('home.sections.tournament_history.content') }}
        </p>
    </div>
</div>

<!-- User Profiles Section -->
<div class="info-section">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">{{ __('home.sections.user_profiles.title') }}</h2>
        <p class="info-section-content">
            {{ __('home.sections.user_profiles.content') }}
        </p>
    </div>
</div>

<!-- How To Use Section -->
<div class="info-section-alt">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">{{ __('home.sections.how_to_use.title') }}</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach(__('home.sections.how_to_use.items') as $item)
                <div class="rounded-lg border border-slate-700/60 bg-slate-900/50 p-4 text-left">
                    <h3 class="font-display text-base font-semibold text-white">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-400">{{ $item['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Eligibility Checking Section -->
<div class="info-section">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">{{ __('home.sections.eligibility_checking.title') }}</h2>
        <p class="info-section-content mb-4">
            {{ __('home.sections.eligibility_checking.content') }}
        </p>
    </div>
</div>

<!-- Organizer Tools Section
<div class="info-section">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title">Organizer Tools</h2>
        <p class="info-section-content">
            Tournament organizers can add staff members to their tournaments and submit match results
            directly from multiplayer links. Staff roles are recorded and displayed on tournament pages.
        </p>
    </div>
</div>-->

<!-- Data Sources Section -->
<div class="info-section-alt">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="info-section-title-large">{{ __('home.sections.data_sources.title') }}</h2>

        <div class="data-source-list">
            <div class="data-source-item">
                <div class="data-source-dot-pink"></div>
                <div>
                    <span class="data-source-label">{{ __('home.sections.data_sources.osu_api') }}</span>
                    <span class="data-source-description">{{ __('home.sections.data_sources.osu_api_desc') }}</span>
                </div>
            </div>

            <div class="data-source-item">
                <div class="data-source-dot-cyan"></div>
                <div>
                    <span class="data-source-label">{{ __('home.sections.data_sources.tcomm') }}</span>
                    <span class="data-source-description">{{ __('home.sections.data_sources.tcomm_desc') }}</span>
                </div>
            </div>

            <div class="data-source-item">
                <div class="data-source-dot-purple"></div>
                <div>
                    <span class="data-source-label">{{ __('home.sections.data_sources.otr') }}</span>
                    <span class="data-source-description">{{ __('home.sections.data_sources.otr_desc') }}</span>
                </div>
            </div>

            <div class="data-source-item">
                <div class="data-source-dot-green"></div>
                <div>
                    <span class="data-source-label">{{ __('home.sections.data_sources.community') }}</span>
                    <span class="data-source-description">{{ __('home.sections.data_sources.community_desc') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Section -->
<div class="stats-section">
    <div class="max-w-5xl mx-auto px-6">
        <div class="grid md:grid-cols-3 gap-12">
            <div class="stat-item">
                <div class="stat-number">
                    {{ number_format($playerCount) }}
                </div>
                <p class="stat-label">{{ __('home.stats.active_players') }}</p>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    {{ number_format($staffCount) }}
                </div>
                <p class="stat-label">{{ __('home.stats.active_staff') }}</p>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    {{ number_format($tournamentCount) }}
                </div>
                <p class="stat-label">{{ __('home.stats.tournaments') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
