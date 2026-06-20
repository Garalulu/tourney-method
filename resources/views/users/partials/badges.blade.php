<!-- Badges Tab -->
<div class="space-y-6">
    @isset($badgesByMode) @foreach($badgesByMode as $mode => $modeBadges)
        <x-ui.panel variant="muted" overflow="visible">
            <!-- Mode Header -->
            <div class="px-6 py-4 border-b border-slate-700/50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-white font-display flex items-center gap-2">
                        <x-gamemode-icon :mode="$mode" size="md" />

                        {{-- Mode Label --}}
                        <span>
                            @if($mode === 'osu')
                                osu!
                            @elseif($mode === 'taiko')
                                osu!taiko
                            @elseif($mode === 'catch')
                                osu!catch
                            @elseif($mode === 'mania')
                                osu!mania
                            @elseif($mode === '4k' || $mode === '7k')
                                osu!mania {{ strtoupper($mode) }}
                            @else
                                {{ ucfirst($mode) }}
                            @endif
                        </span>
                    </h3>
                </div>
                <span class="text-sm text-slate-400">
                    {{ count($modeBadges) }} badge{{ count($modeBadges) !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Badges Grid -->
            @if(!empty($modeBadges))
                <div class="p-6">
                    <div class="flex flex-wrap gap-4">
                        @foreach($modeBadges as $badge)
                            <div class="relative group">
                                {{-- Badge Link to Tournament --}}
                                <a href="{{ $badge->tournament?->showRoute() ?? '#' }}"
                                   class="block inline-block {{ $badge->tournament ? 'hover:scale-105' : 'cursor-default' }} transition-transform duration-200"
                                   @if($badge->tournament) title="{{ $badge->tournament->title }}" @endif>

                                    {{-- Badge Container with 86x40 Ratio --}}
                                    <div class="relative">
                                        {{-- Badge Image at 86x40 ratio --}}
                                        @if($badge->image_url)
                                            <img src="{{ $badge->image_url }}"
                                                 alt="{{ $badge->name }}"
                                                 class="w-[86px] h-[40px]">
                                        @else
                                            <div class="w-[86px] h-[40px] bg-gradient-to-br from-slate-700 to-slate-800 rounded flex items-center justify-center border border-slate-600/50">
                                                <x-icon name="lucide-trophy" class="w-8 h-8 text-pink-400/50" />
                                            </div>
                                        @endif
                                    </div>
                                </a>

                                {{-- Tooltip on Hover --}}
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-slate-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none shadow-xl border border-slate-700/50 z-10">
                                    <div class="font-medium">{{ $badge->name }}</div>
                                    @if($badge->tournament)
                                        <div class="text-slate-400 text-[10px] mt-0.5">
                                            {{ $badge->tournament->rank_range }}
                                            @if($badge->tournament->team_size_display) · {{ $badge->tournament->team_size_display }} @endif
                                            · {{ $badge->tournament->isRegionRestricted() ? 'Regional' : 'Global' }}
                                        </div>
                                    @endif
                                    <div class="text-slate-500 text-[10px] mt-0.5">
                                        {{ $badge->awarded_at->format('M j, Y') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-slate-500 text-sm italic">
                        No {{ $mode }} badges
                    </p>
                </div>
            @endif
        </x-ui.panel>
    @endforeach @else

    {{-- Fallback: No badges grouped (shouldn't happen with new controller logic) --}}
        @if($user->badges->isNotEmpty())
            <x-ui.panel variant="muted" padding="md">
                <div class="flex flex-wrap gap-4">
                    @foreach($user->badges as $badge)
                        <div class="relative group">
                            <a href="{{ $badge->tournament?->showRoute() ?? '#' }}"
                               class="block {{ $badge->tournament ? 'hover:scale-105' : 'cursor-default' }} transition-transform duration-200">
                                @if($badge->image_url)
                                    <img src="{{ $badge->image_url }}"
                                         alt="{{ $badge->name }}"
                                         class="w-[86px] h-[40px] object-contain {{ $badge->is_bws_eligible ? '' : 'opacity-50' }}">
                                @endif
                            </a>
                            @if(!$badge->is_bws_eligible)
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold shadow-lg">×</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>
        @else
            {{-- Empty State --}}
            <x-ui.panel variant="muted" padding="p-16" class="text-center">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-slate-900/50 flex items-center justify-center
                            border border-slate-700/50">
                    <x-icon name="lucide-pencil" class="w-10 h-10 text-slate-600" />
                </div>
                <h3 class="text-xl font-semibold text-slate-400 mb-2 font-display">No Badges Yet</h3>
                <p class="text-slate-500 text-sm max-w-sm mx-auto">
                    Badges are awarded for tournament participation and achievements. Start competing to earn your first badge!
                </p>
            </x-ui.panel>
        @endif
    @endisset
</div>
