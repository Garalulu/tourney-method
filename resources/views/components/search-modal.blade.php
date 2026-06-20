@auth
<div x-data="searchModal()" @keydown.escape.window="close" x-show="isOpen" class="relative z-[60]" style="display: none;">
    <!-- Backdrop with dramatic blur and gradient tint -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    <!-- Modal Container -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-[60] overflow-y-auto"
    >
        <div class="flex min-h-full items-center justify-center p-4">
            <!-- Modal with glow effect -->
            <div
                @click.stop
                class="relative w-full max-w-2xl"
            >
                <!-- Modal Content -->
                <x-ui.panel variant="modal">
                    <!-- Header -->
                    <div class="flex items-center gap-3 px-4 py-4 border-b border-dark-700/50 bg-dark-850">
                        <!-- Close Button -->
                        <button
                            @click="close"
                            class="p-2 rounded-lg hover:bg-dark-700 text-gray-400 hover:text-white transition-all duration-200"
                            aria-label="Close search"
                        >
                            <x-icon name="lucide-x" class="w-5 h-5" />
                        </button>

                        <!-- Search Input -->
                        <div class="flex-1 relative">
                            <x-icon name="lucide-search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" />
                            <input
                                x-ref="searchInput"
                                x-model="query"
                                @input="debouncedSearch"
                                type="text"
                                placeholder="{{ __('search.search.placeholder') }}"
                                class="w-full pl-10 pr-12 py-2.5 bg-dark-900/50 border border-dark-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-osu-pink/50 focus:ring-2 focus:ring-osu-pink/20 transition-all"
                            >
                            <!-- Loading Spinner -->
                            <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                <x-icon name="lucide-loader-circle" class="animate-spin w-5 h-5 text-osu-pink" />
                            </div>
                        </div>
                    </div>

                    <!-- Results Container -->
                    <div class="max-h-[60vh] overflow-y-auto">
                        <!-- Initial State -->
                        <div
                            x-show="query.length < minQueryLength"
                            class="px-8 py-16 text-center"
                        >
                            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-gradient-to-br from-osu-pink/10 to-osu-cyan/10">
                                <x-icon name="lucide-search" class="w-8 h-8 text-osu-pink" />
                            </div>
                            <h3 class="text-lg font-display font-semibold text-white mb-2">{{ __('search.search.start_your_search') }}</h3>
                            <p class="text-sm text-gray-400">{{ __('search.search.min_chars') }}</p>
                        </div>

                        <!-- Results -->
                        <template x-if="query.length >= minQueryLength">
                            <div class="p-4 space-y-6">
                                <!-- Users Section -->
                                <template x-if="results.users.length > 0">
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
                                            {{ __('search.search.players') }}
                                        </h3>
                                        <div class="space-y-2">
                                            <template x-for="user in results.users" :key="user.id">
                                                <a
                                                    :href="`/users/${user.id}`"
                                                    class="group flex items-center gap-3 p-3 bg-dark-900/30 border border-dark-700/50 rounded-lg hover:border-osu-cyan/50 hover:bg-dark-800/50 transition-all duration-200"
                                                >
                                                    <!-- Avatar with ring -->
                                                    <div class="relative flex-shrink-0">
                                                        <img
                                                            :src="`https://a.ppy.sh/${user.osu_id}`"
                                                            :alt="user.username"
                                                            class="w-10 h-10 rounded-lg ring-2 ring-dark-700 group-hover:ring-osu-cyan/30 transition-all"
                                                        >
                                                        <!-- Country Flag -->
                                                        <span
                                                            class="absolute -bottom-1 -right-1 flex items-center justify-center w-5 h-5 bg-dark-850 rounded-md text-sm shadow-lg"
                                                            x-text="getFlagEmoji(user.country_code)"
                                                        ></span>
                                                    </div>

                                                    <!-- User Info -->
                                                    <div class="flex-1 min-w-0">
                                                        <p
                                                            class="font-display font-semibold text-white group-hover:text-osu-cyan transition-colors truncate"
                                                            x-text="user.username"
                                                        ></p>
                                                        <!-- Mode Badge -->
                                                        <span
                                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-md mt-1"
                                                            :class="gamemodeBadgeClasses(user.main_mode)"
                                                        >
                                                            <template x-if="gamemodeIcon(user.main_mode)">
                                                                <img :src="gamemodeIcon(user.main_mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                            </template>
                                                            <span x-text="gamemodeLabel(user.main_mode)"></span>
                                                        </span>
                                                    </div>

                                                    <!-- Arrow -->
                                                    <x-icon name="lucide-chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-osu-cyan group-hover:translate-x-0.5 transition-all flex-shrink-0" />
                                                </a>
                                            </template>
                                        </div>

                                        <!-- View All Users Link (Coming Soon) -->
                                        <div
                                            class="mt-3 text-center text-sm text-gray-600 italic"
                                        >
                                            {{ __('search.search.view_all_players') }}
                                        </div>
                                    </div>
                                </template>

                                <!-- Tournament Section -->
                                <template x-if="results.tournaments.length > 0">
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
                                            {{ __('search.search.tournaments') }}
                                        </h3>
                                        <div class="space-y-2">
                                            <template x-for="tournament in results.tournaments" :key="`tournament-${tournament.id}`">
                                                <a
                                                    :href="`/tournaments/${tournament.id}`"
                                                    class="group flex items-center justify-between p-3 bg-dark-900/30 border border-dark-700/50 rounded-lg hover:border-osu-pink/50 hover:bg-dark-800/50 transition-all duration-200"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <p
                                                                class="font-tournament font-semibold text-white group-hover:text-osu-pink transition-colors truncate"
                                                                x-text="tournament.title"
                                                            ></p>
                                                            <template x-if="tournament.role">
                                                                <span
                                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-osu-pink/10 text-osu-pink whitespace-nowrap"
                                                                    x-text="localizeRole(tournament.role)"
                                                                ></span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <span class="text-sm text-gray-400" x-text="tournament.year"></span>
                                                            <span class="text-gray-500">&bull;</span>
                                                            <template x-for="mode in tournament.modes" :key="mode">
                                                                <span
                                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-md"
                                                                    :class="gamemodeBadgeClasses(mode)"
                                                                >
                                                                    <template x-if="gamemodeIcon(mode)">
                                                                        <img :src="gamemodeIcon(mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                                    </template>
                                                                    <span x-text="gamemodeLabel(mode)"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="tournament.is_badge">
                                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-yellow-500/10 text-yellow-300">
                                                                    {{ __('tournaments.status.badged') }}
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <x-icon name="lucide-chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-osu-pink group-hover:translate-x-0.5 transition-all flex-shrink-0 ml-3" />
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Staff Tournament Section -->
                                <template x-if="results.staff_tournaments.length > 0">
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
                                            Staff
                                        </h3>
                                        <div class="space-y-2">
                                            <template x-for="tournament in results.staff_tournaments" :key="`staff-${tournament.id}`">
                                                <a
                                                    :href="`/tournaments/${tournament.id}`"
                                                    class="group flex items-center justify-between p-3 bg-dark-900/30 border border-dark-700/50 rounded-lg hover:border-osu-pink/50 hover:bg-dark-800/50 transition-all duration-200"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <p
                                                                class="font-tournament font-semibold text-white group-hover:text-osu-pink transition-colors truncate"
                                                                x-text="tournament.title"
                                                            ></p>
                                                            <template x-if="tournament.role">
                                                                <span
                                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-osu-pink/10 text-osu-pink whitespace-nowrap"
                                                                    x-text="localizeRole(tournament.role)"
                                                                ></span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <span class="text-sm text-gray-400" x-text="tournament.year"></span>
                                                            <span class="text-gray-500">&bull;</span>
                                                            <template x-for="mode in tournament.modes" :key="mode">
                                                                <span
                                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-md"
                                                                    :class="gamemodeBadgeClasses(mode)"
                                                                >
                                                                    <template x-if="gamemodeIcon(mode)">
                                                                        <img :src="gamemodeIcon(mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                                    </template>
                                                                    <span x-text="gamemodeLabel(mode)"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="tournament.is_badge">
                                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-yellow-500/10 text-yellow-300">
                                                                    {{ __('tournaments.status.badged') }}
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <x-icon name="lucide-chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-osu-pink group-hover:translate-x-0.5 transition-all flex-shrink-0 ml-3" />
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Podium Tournament Section -->
                                <template x-if="results.podium_tournaments.length > 0">
                                    <div>
                                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
                                            Podium
                                        </h3>
                                        <div class="space-y-2">
                                            <template x-for="tournament in results.podium_tournaments" :key="`podium-${tournament.id}`">
                                                <a
                                                    :href="`/tournaments/${tournament.id}`"
                                                    class="group flex items-center justify-between p-3 bg-dark-900/30 border border-dark-700/50 rounded-lg hover:border-osu-pink/50 hover:bg-dark-800/50 transition-all duration-200"
                                                >
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <p
                                                                class="font-tournament font-semibold text-white group-hover:text-osu-pink transition-colors truncate"
                                                                x-text="tournament.title"
                                                            ></p>
                                                            <template x-if="tournament.role">
                                                                <span
                                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-osu-pink/10 text-osu-pink whitespace-nowrap"
                                                                    x-text="tournament.role"
                                                                ></span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <span class="text-sm text-gray-400" x-text="tournament.year"></span>
                                                            <span class="text-gray-500">&bull;</span>
                                                            <template x-for="mode in tournament.modes" :key="mode">
                                                                <span
                                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-md"
                                                                    :class="gamemodeBadgeClasses(mode)"
                                                                >
                                                                    <template x-if="gamemodeIcon(mode)">
                                                                        <img :src="gamemodeIcon(mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                                    </template>
                                                                    <span x-text="gamemodeLabel(mode)"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="tournament.is_badge">
                                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-yellow-500/10 text-yellow-300">
                                                                    {{ __('tournaments.status.badged') }}
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <x-icon name="lucide-chevron-right" class="w-5 h-5 text-gray-600 group-hover:text-osu-pink group-hover:translate-x-0.5 transition-all flex-shrink-0 ml-3" />
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- View All Tournaments Link -->
                                <a
                                    x-show="totalTournaments > 0"
                                    :href="`/tournaments?q=${encodeURIComponent(query)}&tab=all`"
                                    class="block text-center text-sm font-medium text-osu-pink hover:opacity-80 transition-opacity"
                                    x-text="'{{ __('search.search.view_all_tournaments') }}'.replace(':count', totalTournaments)"
                                ></a>

                                <!-- No Tournaments Found -->
                                <template x-if="query.length >= minQueryLength && results.tournaments.length === 0 && results.staff_tournaments.length === 0 && results.podium_tournaments.length === 0">
                                    <div class="px-8 py-8 text-center">
                                        <p class="text-gray-500">{{ __('search.search.no_tournaments') }}</p>
                                    </div>
                                </template>

                                <!-- No Users Found -->
                                <template x-if="query.length >= minQueryLength && results.users.length === 0">
                                    <div class="px-8 py-8 text-center">
                                        <p class="text-gray-500">{{ __('search.search.no_players') }}</p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Footer with keyboard shortcut hint -->
                    <div class="px-4 py-3 border-t border-dark-700/50 bg-dark-900/30">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{!! __('search.search.close_desc', ['esc' => '<kbd class="px-1.5 py-0.5 bg-dark-800 rounded border border-dark-700 font-mono">ESC</kbd>']) !!}</span>
                        </div>
                    </div>
                </x-ui.panel>
            </div>
        </div>
    </div>
</div>
@endauth
