@props([
    'activeTab',
    'layout' => 'card',
    'tournament',
])

@php
    $statusMeta = [
        'pending' => [
            'label' => 'Pending',
            'class' => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-300',
        ],
        'approved' => [
            'label' => 'Approved',
            'class' => 'border-green-500/30 bg-green-500/10 text-green-300',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'class' => 'border-red-500/30 bg-red-500/10 text-red-300',
        ],
    ][$activeTab] ?? [
        'label' => ucfirst((string) $tournament->status),
        'class' => 'border-[var(--admin-border)] bg-[var(--admin-bg)] text-[var(--admin-muted)]',
    ];

    $modeLabels = $tournament->formatted_modes->isNotEmpty()
        ? $tournament->formatted_modes
        : collect(['Unknown mode']);

    $registrationStartLabel = $tournament->registration_start
        ? $tournament->registration_start->format('M d, Y')
        : 'Not set';

    $tournamentEndLabel = $tournament->tournament_end
        ? $tournament->tournament_end->format('M d, Y')
        : 'Not set';

    $source = $tournament->import_source ? strtoupper((string) $tournament->import_source) : 'MANUAL';
    $host = $tournament->host;
@endphp

@if($layout === 'row')
    <tr class="transition hover:bg-[var(--admin-bg)]/70">
        <td class="px-5 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <img src="{{ $tournament->cached_banner_url }}"
                     alt="{{ $tournament->title }} banner"
                     class="h-14 w-20 flex-none rounded-lg border border-[var(--admin-border)] object-cover"
                     loading="lazy"
                     decoding="async"
                     onerror="this.src='{{ asset('images/no-banner-placeholder.png') }}'">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md border px-2 py-0.5 text-xs font-semibold {{ $statusMeta['class'] }}">
                            {{ $statusMeta['label'] }}
                        </span>
                        @if($tournament->isUnread())
                            <span class="rounded-md border border-[var(--osu-cyan)]/30 bg-[var(--osu-cyan)]/10 px-2 py-0.5 text-xs font-semibold text-[var(--osu-cyan)]">
                                {{ $activeTab === 'pending' ? 'New' : 'Updated' }}
                            </span>
                        @endif
                        @if($tournament->is_badge)
                            <span class="rounded-md bg-[var(--osu-pink)] px-2 py-0.5 text-xs font-semibold text-white">Badge</span>
                        @endif
                        @if($tournament->is_bws)
                            <span class="rounded-md bg-[var(--osu-cyan)] px-2 py-0.5 text-xs font-semibold text-[var(--admin-bg)]">BWS</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                       class="mt-2 block max-w-md truncate font-tournament font-semibold text-[var(--admin-text)] transition hover:text-[var(--osu-pink)]">
                        {{ $tournament->title }}
                    </a>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--admin-muted)]">
                        <span>{{ $tournament->rank_range }}</span>
                        @if($tournament->team_size_display)
                            <span>{{ $tournament->team_size_display }}</span>
                        @endif
                        <span>{{ $source }}</span>
                    </div>
                    @if($activeTab === 'rejected' && $tournament->rejection_reason)
                        <p class="mt-2 max-w-xl text-sm text-red-300">
                            {{ $tournament->rejection_reason }}
                        </p>
                    @endif
                </div>
            </div>
        </td>
        <td class="px-5 py-4 text-sm text-[var(--admin-text)]">
            @if($host && $host->exists)
                <a href="{{ route('users.show', $host) }}"
                   target="_blank"
                   rel="noopener"
                   class="font-semibold transition hover:text-[var(--osu-cyan)]">
                    {{ $tournament->host_username ?? $host->username }}
                </a>
            @else
                {{ $tournament->host_username ?? 'Unknown' }}
            @endif
        </td>
        <td class="px-5 py-4">
            <div class="flex max-w-48 flex-wrap gap-1.5">
                @foreach($modeLabels as $mode)
                    <span class="rounded-md border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold text-[var(--admin-text)]">
                        {{ $mode }}
                    </span>
                @endforeach
            </div>
        </td>
        @if($activeTab === 'pending')
            <td class="px-5 py-4 text-sm text-[var(--admin-muted)]">
                {{ $registrationStartLabel }}
            </td>
        @endif
        <td class="px-5 py-4 text-sm text-[var(--admin-muted)]">
            {{ $tournamentEndLabel }}
        </td>
        <td class="px-5 py-4 text-sm text-[var(--admin-muted)]">
            <div class="font-mono text-[var(--admin-text)]">{{ $tournament->updated_at?->format('M d, Y') }}</div>
            <div class="text-xs">{{ $tournament->updated_at?->format('H:i') }}</div>
        </td>
    </tr>
@else
    <article class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4">
        <div class="flex gap-3">
            <img src="{{ $tournament->cached_banner_url }}"
                 alt="{{ $tournament->title }} banner"
                 class="h-20 w-24 flex-none rounded-lg border border-[var(--admin-border)] object-cover"
                 loading="lazy"
                 decoding="async"
                 onerror="this.src='{{ asset('images/no-banner-placeholder.png') }}'">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md border px-2 py-0.5 text-xs font-semibold {{ $statusMeta['class'] }}">
                        {{ $statusMeta['label'] }}
                    </span>
                    @if($tournament->isUnread())
                        <span class="rounded-md border border-[var(--osu-cyan)]/30 bg-[var(--osu-cyan)]/10 px-2 py-0.5 text-xs font-semibold text-[var(--osu-cyan)]">
                            {{ $activeTab === 'pending' ? 'New' : 'Updated' }}
                        </span>
                    @endif
                </div>
                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                   class="mt-2 block text-base font-tournament font-bold leading-snug text-[var(--admin-text)] transition hover:text-[var(--osu-pink)]">
                    {{ $tournament->title }}
                </a>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-1.5">
            @foreach($modeLabels as $mode)
                <span class="rounded-md border border-[var(--admin-border)] bg-[var(--admin-bg)] px-2 py-1 text-xs font-semibold text-[var(--admin-text)]">
                    {{ $mode }}
                </span>
            @endforeach
            @if($tournament->is_badge)
                <span class="rounded-md bg-[var(--osu-pink)] px-2 py-1 text-xs font-semibold text-white">Badge</span>
            @endif
            @if($tournament->is_bws)
                <span class="rounded-md bg-[var(--osu-cyan)] px-2 py-1 text-xs font-semibold text-[var(--admin-bg)]">BWS</span>
            @endif
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Host</dt>
                <dd class="mt-1 min-w-0 break-words text-[var(--admin-text)]">
                    @if($host && $host->exists)
                        <a href="{{ route('users.show', $host) }}"
                           target="_blank"
                           rel="noopener"
                           class="font-semibold transition hover:text-[var(--osu-cyan)]">
                            {{ $tournament->host_username ?? $host->username }}
                        </a>
                    @else
                        {{ $tournament->host_username ?? 'Unknown' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Last edited</dt>
                <dd class="mt-1 text-[var(--admin-text)]">{{ $tournament->updated_at?->format('M d, H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Rank</dt>
                <dd class="mt-1 text-[var(--admin-text)]">{{ $tournament->rank_range }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Team</dt>
                <dd class="mt-1 text-[var(--admin-text)]">{{ $tournament->team_size_display ?: 'Not set' }}</dd>
            </div>
            @if($activeTab === 'pending')
                <div>
                    <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Reg Start</dt>
                    <dd class="mt-1 text-[var(--admin-text)]">{{ $registrationStartLabel }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Tourney End</dt>
                <dd class="mt-1 text-[var(--admin-text)]">{{ $tournamentEndLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-[var(--admin-muted)]">Source</dt>
                <dd class="mt-1 text-[var(--admin-text)]">{{ $source }}</dd>
            </div>
        </dl>

        @if($activeTab === 'rejected' && $tournament->rejection_reason)
            <div class="mt-4 rounded-lg border border-red-500/20 bg-red-500/10 p-3">
                <div class="text-xs font-semibold uppercase tracking-wider text-red-300">Rejection reason</div>
                <p class="mt-1 text-sm leading-relaxed text-red-100">{{ $tournament->rejection_reason }}</p>
            </div>
        @endif

    </article>
@endif
