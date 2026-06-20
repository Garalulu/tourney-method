<!-- Tournament History Tab -->
<div class="space-y-6" x-data="{ staffExpanded: false, podiumExpanded: false }">
    <!-- Staff Roles Section -->
    @if($staffRoles->isNotEmpty())
        <x-ui.panel variant="muted" overflow="visible" class="relative z-10">
            <div class="px-6 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white font-display flex items-center gap-2">
                    <x-icon name="lucide-users" class="w-5 h-5 text-pink-400" />
                    {{ __('users.history.staff') }}
                </h2>
            </div>

            {{-- All items in single container --}}
            <div class="divide-y divide-slate-700/50">
                @foreach($staffRoles->take(5) as $tournamentId => $roles)
                    @php
                        // Get first role and tournament
                        $firstRole = $roles->first();
                        $tournament = $firstRole->tournament;

                        // Get all roles and sort by priority
                        $allRoles = collect(\App\Helpers\StaffRoleHelper::sortRoles(
                            $roles->pluck('role')->unique()->values()->all()
                        ));

                        // Determine primary role (highest priority)
                        $primaryRole = $allRoles->first();
                        $additionalCount = $allRoles->count() - 1;
                    @endphp
                    <div class="px-6 py-4 hover:bg-slate-700/30 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('tournaments.show', $tournament) }}"
                                   class="text-white font-medium hover:text-pink-400 transition-colors font-tournament">
                                    {{ $tournament->title }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-slate-400">
                                    @if($tournament->tournament_start)
                                        <span>{{ $tournament->tournament_start->format('M Y') }}</span>
                                    @endif
                                    @if($tournament->modes)
                                        @foreach($tournament->modes_with_details as $modeDetail)
                                            <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" size="compact" />
                                        @endforeach
                                        @if($tournament->is_badge && $tournament->badge_status === 'approved')
                                            <span class="ui-badge-xs ui-badge-neutral">
                                                {{ __('users.history.badged') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2" x-data="{ showTooltip: false }">
                                <div class="relative"
                                     @mouseenter="showTooltip = true"
                                     @mouseleave="showTooltip = false"
                                     @click="showTooltip = !showTooltip">

                                    <!-- Primary role badge -->
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase bg-green-500/20 text-green-400">
                                        {{ __('tournaments.staffs.' . $primaryRole) }}
                                    </span>

                                    <!-- Additional count indicator -->
                                    @if($additionalCount > 0)
                                        <span class="px-2 py-1 rounded text-xs bg-green-500/10 text-green-400">
                                            +{{ $additionalCount }}
                                        </span>
                                    

                                    <!-- Tooltip with all roles (fade animation, positioned to right) -->
                                    <div x-show="showTooltip"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute left-full ml-3 top-0 z-50 w-48 bg-slate-900 rounded-lg border border-slate-700 shadow-xl">
                                        <div class="p-3">
                                            <div class="text-xs font-medium text-slate-400 mb-2 uppercase tracking-wide">
                                                {{ __('users.history.all_roles') }}
                                            </div>
                                            <div class="space-y-1">
                                                @foreach($allRoles as $role)
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm text-white uppercase">{{ __('tournaments.staffs.' . $role) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <!-- Tooltip arrow -->
                                        <div class="absolute top-3 -left-1 w-2 h-2 bg-slate-900 border-l border-b border-slate-700 transform rotate-45"></div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Remaining items (hidden by default, shown when expanded) --}}
                @foreach($staffRoles->slice(5) as $tournamentId => $roles)
                    @php
                        $firstRole = $roles->first();
                        $tournament = $firstRole->tournament;
                        $allRoles = collect(\App\Helpers\StaffRoleHelper::sortRoles(
                            $roles->pluck('role')->unique()->values()->all()
                        ));
                        $primaryRole = $allRoles->first();
                        $additionalCount = $allRoles->count() - 1;
                    @endphp
                    <div class="px-6 py-4 hover:bg-slate-700/30 transition-colors"
                         x-show="staffExpanded"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('tournaments.show', $tournament) }}"
                                   class="text-white font-medium hover:text-pink-400 transition-colors font-tournament">
                                    {{ $tournament->title }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-slate-400">
                                    @if($tournament->tournament_start)
                                        <span>{{ $tournament->tournament_start->format('M Y') }}</span>
                                    @endif
                                    @if($tournament->modes)
                                        @foreach($tournament->modes_with_details as $modeDetail)
                                            <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" size="compact" />
                                        @endforeach
                                        @if($tournament->is_badge && $tournament->badge_status === 'approved')
                                            <span class="ui-badge-xs ui-badge-neutral">
                                                {{ __('users.history.badged') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2" x-data="{ showTooltip: false }">
                                <div class="relative"
                                     @mouseenter="showTooltip = true"
                                     @mouseleave="showTooltip = false"
                                     @click="showTooltip = !showTooltip">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase bg-green-500/20 text-green-400">
                                        {{ __('tournaments.staffs.' . $primaryRole) }}
                                    </span>
                                    @if($additionalCount > 0)
                                        <span class="px-2 py-1 rounded text-xs bg-green-500/10 text-green-400">
                                            +{{ $additionalCount }}
                                        </span>
                                    
                                    <div x-show="showTooltip"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute left-full ml-3 top-0 z-50 w-48 bg-slate-900 rounded-lg border border-slate-700 shadow-xl">
                                        <div class="p-3">
                                            <div class="text-xs font-medium text-slate-400 mb-2 uppercase tracking-wide">
                                                {{ __('users.history.all_roles') }}
                                            </div>
                                            <div class="space-y-1">
                                                @foreach($allRoles as $role)
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm text-white uppercase">{{ __('tournaments.staffs.' . $role) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="absolute top-3 -left-1 w-2 h-2 bg-slate-900 border-l border-b border-slate-700 transform rotate-45"></div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- See More/Less Button --}}
            @if($staffRoles->count() > 5)
                <div class="px-6 py-3 border-t border-slate-700/50">
                    <button @click="staffExpanded = !staffExpanded"
                            class="w-full text-center text-sm font-medium text-pink-400 hover:text-pink-300 transition-colors">
                        <span x-show="!staffExpanded">{{ __('users.history.see_more', ['count' => $staffRoles->count() - 5]) }}</span>
                        <span x-show="staffExpanded" x-cloak>{{ __('users.history.see_less') }}</span>
                    </button>
                </div>
            @endif
        </x-ui.panel>
    @else
        <div class="bg-slate-800/30 backdrop-blur-sm rounded-xl border border-slate-700/50 p-12 text-center">
            <x-icon name="lucide-building-2" class="w-16 h-16 mx-auto text-slate-600 mb-4" />
            <h3 class="text-lg font-medium text-slate-400 mb-2 font-display">{{ __('users.history.no_staff') }}</h3>
            <p class="text-slate-500 text-sm">{{ __('users.history.no_staff_desc') }}</p>
        </div>
    @endif

    <!-- Player Participation (Podium Placements) Section -->
    @if($podiumPlacements->isNotEmpty())
        <x-ui.panel variant="muted" overflow="visible">
            <div class="px-6 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white font-display flex items-center gap-2">
                    <x-icon name="lucide-trophy" class="w-5 h-5 text-yellow-400" />
                    {{ __('users.history.player') }}
                </h2>
            </div>

            {{-- All items in single container --}}
            <div class="divide-y divide-slate-700/50">
                @foreach($podiumPlacements->take(5) as $tournamentId => $placements)
                    <div class="px-6 py-4 hover:bg-slate-700/30 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('tournaments.show', $placements->first()->tournament) }}"
                                   class="text-white font-medium hover:text-pink-400 transition-colors font-tournament">
                                    {{ $placements->first()->tournament->title }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-slate-400">
                                    @if($placements->first()->tournament->tournament_end)
                                        <span>{{ $placements->first()->tournament->tournament_end->format('M Y') }}</span>
                                    @endif
                                    @if($placements->first()->tournament->modes)
                                        @foreach($placements->first()->tournament->modes_with_details as $modeDetail)
                                            <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" size="compact" />
                                        @endforeach
                                        @if($placements->first()->tournament->is_badge && $placements->first()->tournament->badge_status === 'approved')
                                            <span class="ui-badge-xs ui-badge-neutral">
                                                {{ __('users.history.badged') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @foreach($placements as $placement)
                                    @php
                                        $placementColor = match($placement->placement) {
                                            1 => 'bg-yellow-500/20 text-yellow-400',
                                            2 => 'bg-slate-400/20 text-slate-300',
                                            3 => 'bg-orange-700/20 text-orange-400',
                                            default => 'bg-slate-700/50 text-slate-400',
                                        };
                                        $placementLabel = match($placement->placement) {
                                            1 => '1ST',
                                            2 => '2ND',
                                            3 => '3RD',
                                            default => strtoupper("#{$placement->placement}"),
                                        };
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase {{ $placementColor }}">
                                        {{ $placementLabel }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Remaining items (hidden by default, shown when expanded) --}}
                @foreach($podiumPlacements->slice(5) as $tournamentId => $placements)
                    <div class="px-6 py-4 hover:bg-slate-700/30 transition-colors"
                         x-show="podiumExpanded"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('tournaments.show', $placements->first()->tournament) }}"
                                   class="text-white font-medium hover:text-pink-400 transition-colors font-tournament">
                                    {{ $placements->first()->tournament->title }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-slate-400">
                                    @if($placements->first()->tournament->tournament_end)
                                        <span>{{ $placements->first()->tournament->tournament_end->format('M Y') }}</span>
                                    @endif
                                    @if($placements->first()->tournament->modes)
                                        @foreach($placements->first()->tournament->modes_with_details as $modeDetail)
                                            <x-gamemode-badge :mode="$modeDetail['mode']" :key-count="$modeDetail['key_count'] ?? null" size="compact" />
                                        @endforeach
                                        @if($placements->first()->tournament->is_badge && $placements->first()->tournament->badge_status === 'approved')
                                            <span class="ui-badge-xs ui-badge-neutral">
                                                {{ __('users.history.badged') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @foreach($placements as $placement)
                                    @php
                                        $placementColor = match($placement->placement) {
                                            1 => 'bg-yellow-500/20 text-yellow-400',
                                            2 => 'bg-slate-400/20 text-slate-300',
                                            3 => 'bg-orange-700/20 text-orange-400',
                                            default => 'bg-slate-700/50 text-slate-400',
                                        };
                                        $placementLabel = match($placement->placement) {
                                            1 => '1ST',
                                            2 => '2ND',
                                            3 => '3RD',
                                            default => strtoupper("#{$placement->placement}"),
                                        };
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase {{ $placementColor }}">
                                        {{ $placementLabel }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- See More/Less Button --}}
            @if($podiumPlacements->count() > 5)
                <div class="px-6 py-3 border-t border-slate-700/50">
                    <button @click="podiumExpanded = !podiumExpanded"
                            class="w-full text-center text-sm font-medium text-pink-400 hover:text-pink-300 transition-colors">
                        <span x-show="!podiumExpanded">{{ __('users.history.see_more', ['count' => $podiumPlacements->count() - 5]) }}</span>
                        <span x-show="podiumExpanded" x-cloak>{{ __('users.history.see_less') }}</span>
                    </button>
                </div>
            @endif
        </x-ui.panel>
    @else
        <div class="bg-slate-800/30 backdrop-blur-sm rounded-xl border border-slate-700/50 p-12 text-center">
            <x-icon name="lucide-circle-check" class="w-16 h-16 mx-auto text-slate-600 mb-4" />
            <h3 class="text-lg font-medium text-slate-400 mb-2 font-display">{{ __('users.history.no_player') }}</h3>
            <p class="text-slate-500 text-sm">{{ __('users.history.no_player_desc') }}</p>
        </div>
    @endif
</div>
