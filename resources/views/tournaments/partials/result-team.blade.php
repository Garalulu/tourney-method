@php
    $size = $size ?? 'compact';
    $isPodium = ($team['is_podium'] ?? false) || $size === 'podium';
    $includePlacementInHeading = $includePlacementInHeading ?? ! $isPodium;
    $showHeading = $showHeading ?? true;
    $padding = $isPodium ? '' : 'p-3';
    $label = $team['placement_label'] ?? $team['section_label'] ?? null;
    $teamName = $team['team_name'] ?? null;
    $heading = filled($teamName) && $includePlacementInHeading && filled($label) ? "{$label} - {$teamName}" : ($teamName ?: ($includePlacementInHeading ? $label : null));
    $currentUser = (bool) ($team['current_user'] ?? false);
    $articleClass = $isPodium
        ? ''
        : 'rounded-xl border '.($currentUser ? 'border-pink-400 bg-pink-500/10' : 'border-slate-700/50 bg-slate-900/45').' '.$padding.' transition hover:border-pink-500/40';
@endphp

<article
    class="{{ $articleClass }}"
    data-result-team-key="{{ $team['key'] }}"
>
    @if($showHeading && $heading)
    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="min-w-0 truncate text-sm font-bold text-white">{{ $heading }}</h3>
    </div>
    @endif

    @if($isPodium)
        <div data-result-roster-layout="mobile-two-column" class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
            @foreach($team['roster'] as $member)
                <div class="group flex min-w-0 items-center gap-2 rounded-xl border border-slate-600/50 bg-slate-900/50 p-2 transition-all duration-300 hover:border-pink-500/30 sm:gap-3 sm:p-4">
                    @if($member['badge_image_url'])
                        <img src="{{ $member['badge_image_url'] }}"
                             alt="{{ $member['name'] }}'s badge"
                             class="hidden h-[40px] w-[86px] flex-shrink-0 object-contain sm:block">
                    @endif

                    @if($member['profile_url'])
                        <a href="{{ $member['profile_url'] }}" class="relative flex-shrink-0">
                            <img src="{{ $member['avatar_url'] ?: 'https://a.ppy.sh/'.$member['osu_id'] }}"
                                 alt="{{ $member['name'] }}"
                                 class="h-7 w-7 rounded-md border border-pink-500/30 object-cover transition-colors group-hover:border-pink-400 sm:h-12 sm:w-12 sm:rounded-full sm:border-2">
                            @if($member['country_code'])
                                <img src="https://flagcdn.com/w40/{{ strtolower($member['country_code']) }}.png"
                                     alt="{{ $member['country_code'] }}"
                                     class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-slate-900 object-cover sm:hidden"
                                     title="{{ $member['country_code'] }}">
                            @endif
                        </a>
                    @else
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md border border-pink-500/20 bg-slate-800 text-xs font-bold text-slate-300 sm:h-12 sm:w-12 sm:rounded-full sm:border-2 sm:text-sm">
                            {{ mb_substr($member['name'], 0, 1) }}
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if($member['profile_url'])
                            <a href="{{ $member['profile_url'] }}" class="block truncate text-sm font-bold text-white transition-colors group-hover:text-pink-400">
                                {{ $member['name'] }}
                            </a>
                        @else
                            <span class="block truncate text-sm font-bold text-white">{{ $member['name'] }}</span>
                        @endif

                        @if($member['country_code'])
                            <img src="https://flagcdn.com/w40/{{ strtolower($member['country_code']) }}.png"
                                 alt="{{ $member['country_code'] }}"
                                 class="mt-1 hidden h-3 w-5 rounded object-cover shadow-sm sm:block"
                                 title="{{ $member['country_code'] }}">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
    <div data-result-roster-layout="mobile-two-column" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        @foreach($team['roster'] as $member)
            @if($member['profile_url'])
                <a href="{{ $member['profile_url'] }}" class="group/member inline-flex min-w-0 max-w-full items-center gap-2 rounded-lg border border-slate-700/50 bg-slate-950/50 px-2 py-1 text-xs text-slate-300 transition hover:border-pink-500/50 hover:text-white">
                    <span class="relative shrink-0">
                        <img src="{{ $member['avatar_url'] ?: 'https://a.ppy.sh/'.$member['osu_id'] }}" alt="{{ $member['name'] }}" class="h-7 w-7 rounded-md object-cover">
                        @if($member['country_code'])
                            <img src="https://flagcdn.com/w40/{{ strtolower($member['country_code']) }}.png" alt="{{ $member['country_code'] }}" class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-slate-950 object-cover" title="{{ $member['country_code'] }}">
                        @endif
                    </span>
                    <span class="min-w-0 truncate font-semibold group-hover/member:text-pink-300">{{ $member['name'] }}</span>
                </a>
            @else
                <span class="inline-flex min-w-0 max-w-full items-center rounded-lg border border-slate-700/50 bg-slate-950/50 px-2.5 py-1.5 text-xs font-semibold text-slate-300">
                    <span class="truncate">{{ $member['name'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
    @endif
</article>
