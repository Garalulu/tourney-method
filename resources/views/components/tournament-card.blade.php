@props(['tournament'])

@php
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

<a
    href="{{ route('tournaments.show', $tournament) }}"
    class="tournament-card-link">

    {{-- Banner --}}
    <div class="tournament-card-banner {{ $isRegistrationFuture ? 'tournament-card-banner-future' : '' }}">
        @if($tournament->banner_url)
            <img
                src="{{ $tournament->cached_banner_url }}"
                alt="{{ $tournament->title }}"
                class="tournament-card-banner-img {{ $isRegistrationFuture ? 'tournament-card-banner-blur' : '' }}"
                loading="lazy"
                decoding="async">
            <div class="tournament-card-banner-overlay"></div>
        @else
            <div class="tournament-card-banner-placeholder">
                <span class="tournament-card-banner-placeholder-text">{{ __('tournaments.status.no_banner') }}</span>
            </div>
        @endif

        {{-- Mode Badges (data visualization) --}}
        <div class="tournament-card-badges">
            @foreach($tournament->modes_with_details as $modeDetail)
                <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" />
            @endforeach
        </div>

        {{-- Status Badges --}}
        <div class="tournament-card-status-badges">
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
    </div>

    {{-- Title --}}
    <h3 class="tournament-card-title" title="{{ $tournament->title }}">
        {{ $tournament->title }}
    </h3>

    {{-- Metadata --}}
    <div class="tournament-card-metadata">
        @if($tournament->format_badges->isNotEmpty())
            <p class="tournament-card-metadata-text">{{ $tournament->format_badges->join(' / ') }}</p>
        @endif

        <p class="tournament-card-metadata-text">{{ $tournament->rank_range }}</p>

        @if($tournament->team_size_display)
            <p class="tournament-card-team-size">{{ $tournament->team_size_display }}</p>
        @endif

        @if($tournament->progression_chip)
            <p class="tournament-card-metadata-text">{{ $tournament->progression_chip }}</p>
        @endif

        @if($tournament->star_rating_display)
            <p class="tournament-card-star-rating">
                {{ $tournament->star_rating_display }}
            </p>
        @endif
    </div>
</a>
