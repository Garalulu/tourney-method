<nav x-data="navigation()" class="nav">
    <div class="nav-container">
        <div class="nav-content">
            <!-- Logo -->
            <div class="nav-logo">
                <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                    <div class="w-10 h-10 bg-osu-pink rounded-lg flex items-center justify-center transform group-hover:scale-105 transition-transform hidden">
                    </div>
                    <span class="font-display font-bold text-xl text-osu-pink">
                        Tourney Method
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="nav-desktop">
                <!-- Left Side: Navigation Links -->
                <div class="nav-links">
                    <!-- Tournaments Link (always visible) -->
                    <a href="{{ route('tournaments.index') }}"
                       class="nav-item {{ request()->routeIs('tournaments.*') ? 'nav-link-active' : 'nav-link' }}">
                        {{ __('common.nav.tournaments') }}
                    </a>

                    @auth
                        <!-- Dashboard Link (authenticated users only) -->
                        <a href="{{ route('dashboard') }}"
                           class="nav-item {{ request()->routeIs('dashboard') ? 'nav-link-active' : 'nav-link' }}">
                            {{ __('common.nav.dashboard') }}
                        </a>

                        <!-- Admin Link (for admin/master roles only) -->
                        @if(auth()->user()->isAdmin() || auth()->user()->isMaster())
                            <a href="{{ route('admin.dashboard') }}"
                               class="nav-item {{ request()->routeIs('admin.*') ? 'nav-link-active' : 'nav-link' }}">
                                {{ __('common.nav.admin') }}
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- Right Side: Language, Search & User/Login -->
                <div class="nav-actions">     
                    @auth
                        <!-- Search Button -->
                        <button
                            @click="$dispatch('open-search')"
                            class="nav-icon-btn inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-dark-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-osu-pink/40"
                            aria-label="{{ __('common.nav.search') }}"
                            title="{{ __('common.nav.search') }}"
                        >
                            <x-icon name="lucide-search" class="h-5 w-5" />
                            <span class="sr-only">{{ __('common.nav.search') }}</span>
                        </button>
                    @endauth

                    <a href="{{ route('contribute') }}"
                       class="nav-icon-btn inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-dark-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-osu-pink/40"
                       aria-label="{{ __('common.footer.contribute') }}"
                       title="{{ __('common.footer.contribute') }}">
                        <x-icon name="lucide-heart" class="h-5 w-5" />
                        <span class="sr-only">{{ __('common.footer.contribute') }}</span>
                    </a>

                    <!-- Language Switcher -->
                    <x-language-switcher />

                    @auth
                        <x-notification-dropdown />

                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                                class="nav-user-btn">
                            <img src="{{ auth()->user()->avatar_url }}"
                                 alt="{{ auth()->user()->username }}"
                                 class="w-8 h-8 rounded-lg ring-2 ring-osu-pink/30">
                            <span class="font-medium text-sm">{{ auth()->user()->username }}</span>
                            <x-icon name="lucide-chevron-down" class="w-4 h-4 transition-transform" x-bind:class="{ 'rotate-180': open }" />
                        </button>

                            <!-- Dropdown Menu -->
                            <div x-show="open"
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="transform opacity-0 scale-95 -translate-y-2"
                                 x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                                 x-transition:leave-end="transform opacity-0 scale-95 -translate-y-2"
                                 class="nav-dropdown"
                                 style="display: none;">
                                <!-- Dropdown Content -->
                                <div class="nav-dropdown-content">
                                    <!-- Menu Items -->
                                    <div class="py-1">
                                        <a href="{{ route('users.show', auth()->user()->id) }}"
                                           class="nav-dropdown-item">
                                            {{ __('common.nav.profile') }}
                                        </a>
                                        <a href="{{ route('settings.index') }}"
                                           class="nav-dropdown-item">
                                            {{ __('common.nav.settings') }}
                                        </a>
                                    </div>

                                    <!-- Divider -->
                                    <div class="h-px bg-dark-700"></div>

                                    <!-- Logout -->
                                    <div class="py-1">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                    class="nav-dropdown-item w-full text-left text-red-400 hover:bg-red-500/10 hover:text-red-300">
                                                {{ __('common.nav.logout') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                    </div>
                    @endauth
                    @guest
                        <!-- Login Button (border style, minimal) -->
                        <a href="{{ route('login') }}"
                           class="px-4 py-2 border border-osu-pink/50 text-osu-pink rounded-lg font-medium hover:border-osu-pink hover:bg-osu-pink/10 transition-colors">
                            {{ __('common.nav.login_with_osu') }}
                        </a>
                    @endguest
                </div>
            </div>

            <!-- Mobile Menu Button -->
            <div class="flex items-center md:hidden">
                <button @click="toggleMobileMenu"
                        class="nav-mobile-btn">
                    <x-icon name="lucide-menu" x-show="!mobileMenuOpen" class="w-6 h-6" />
                    <x-icon name="lucide-x" x-show="mobileMenuOpen" class="w-6 h-6" style="display: none;" />
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="nav-mobile-menu"
         style="display: none;">
        <div class="nav-mobile-content">
            @auth
                <div class="nav-mobile-subheader">
                    <button
                        type="button"
                        @click="setMobilePanel('account')"
                        class="nav-mobile-user-tab"
                        :class="{ 'nav-mobile-tab-active': mobilePanel === 'account' }"
                        :aria-pressed="mobilePanel === 'account'"
                    >
                        <img src="https://a.ppy.sh/{{ auth()->user()->osu_id }}"
                             alt="{{ auth()->user()->username }}"
                             class="h-9 w-9 rounded-lg ring-2 ring-osu-pink/30">
                        <span class="min-w-0 truncate font-medium">{{ auth()->user()->username }}</span>
                    </button>

                    <div class="nav-mobile-action-tabs">
                        <button
                            type="button"
                            @click="setMobilePanel('menu')"
                            class="nav-mobile-icon-tab"
                            :class="{ 'nav-mobile-tab-active': mobilePanel === 'menu' }"
                            :aria-pressed="mobilePanel === 'menu'"
                            aria-label="Menu"
                        >
                            <x-icon name="lucide-menu" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            @click="setMobilePanel('search')"
                            class="nav-mobile-icon-tab"
                            :class="{ 'nav-mobile-tab-active': mobilePanel === 'search' }"
                            :aria-pressed="mobilePanel === 'search'"
                            aria-label="{{ __('common.nav.search') }}"
                        >
                            <x-icon name="lucide-search" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            @click="setMobilePanel('notifications')"
                            class="nav-mobile-icon-tab relative"
                            :class="{ 'nav-mobile-tab-active': mobilePanel === 'notifications' }"
                            :aria-pressed="mobilePanel === 'notifications'"
                            aria-label="{{ __('common.notifications.title') }}"
                        >
                            <x-icon name="lucide-bell" class="h-5 w-5" />
                            <span x-show="mobileUnreadCount > 0"
                                  x-text="mobileUnreadCount > 99 ? '99+' : mobileUnreadCount"
                                  class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[11px] font-bold leading-none text-white ring-2 ring-dark-850"
                                  style="display: none;"></span>
                        </button>
                    </div>
                </div>

                <div class="border-t border-dark-700"></div>

                <div x-show="mobilePanel === 'menu'" class="space-y-1">
                    <a href="{{ route('tournaments.index') }}"
                       class="nav-mobile-item {{ request()->routeIs('tournaments.*') ? 'text-osu-pink' : '' }}">
                        {{ __('common.nav.tournaments') }}
                    </a>

                    <a href="{{ route('dashboard') }}"
                       class="nav-mobile-item {{ request()->routeIs('dashboard') ? 'text-osu-pink' : '' }}">
                        {{ __('common.nav.dashboard') }}
                    </a>

                    @if(auth()->user()->isAdmin() || auth()->user()->isMaster())
                        <a href="{{ route('admin.dashboard') }}" class="nav-mobile-item {{ request()->routeIs('admin.*') ? 'text-osu-pink' : '' }}">
                            {{ __('common.nav.admin') }}
                        </a>
                    @endif

                    <div class="border-t border-dark-700 my-2"></div>

                    <div class="px-0 py-2">
                        <x-language-switcher mobile />
                    </div>
                </div>

                <div x-show="mobilePanel === 'account'" class="space-y-1" style="display: none;">
                    <a href="{{ route('users.show', auth()->user()->id) }}" class="nav-mobile-item">
                        {{ __('common.nav.profile') }}
                    </a>

                    <a href="{{ route('settings.index') }}" class="nav-mobile-item">
                        {{ __('common.nav.settings') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-mobile-item text-red-400 hover:bg-red-500/10 w-full text-left">
                            {{ __('common.nav.logout') }}
                        </button>
                    </form>
                </div>

                <div x-show="mobilePanel === 'search'" class="space-y-4" style="display: none;">
                    <div class="nav-mobile-search-wrap">
                        <x-icon name="lucide-search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" />
                        <input
                            x-ref="mobileSearchInput"
                            x-model="mobileSearchQuery"
                            @input="debouncedMobileSearch"
                            type="search"
                            placeholder="{{ __('search.search.placeholder') }}"
                            class="nav-mobile-search-input"
                        >
                        <div x-show="mobileSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2" style="display: none;">
                            <x-icon name="lucide-loader-circle" class="h-5 w-5 animate-spin text-osu-pink" />
                        </div>
                    </div>

                    <div x-show="mobileSearchQuery.length < minQueryLength" class="px-3 py-8 text-center text-sm text-gray-400">
                        {{ __('search.search.min_chars') }}
                    </div>

                    <div x-show="mobileSearchQuery.length >= minQueryLength" class="space-y-5" style="display: none;">
                        <template x-if="mobileSearchResults.users.length > 0">
                            <section>
                                <h3 class="nav-mobile-section-title">{{ __('search.search.players') }}</h3>
                                <div class="space-y-2">
                                    <template x-for="user in mobileSearchResults.users" :key="user.id">
                                        <a :href="`/users/${user.id}`" class="nav-mobile-result-row">
                                            <div class="relative shrink-0">
                                                <img :src="`https://a.ppy.sh/${user.osu_id}`"
                                                     :alt="user.username"
                                                     class="h-10 w-10 rounded-lg ring-2 ring-dark-700">
                                                <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-md bg-dark-850 text-sm shadow-lg" x-text="getFlagEmoji(user.country_code)"></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-display font-semibold text-white" x-text="user.username"></p>
                                                <span
                                                    class="mt-1 inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="gamemodeBadgeClasses(user.main_mode)"
                                                >
                                                    <template x-if="gamemodeIcon(user.main_mode)">
                                                        <img :src="gamemodeIcon(user.main_mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                    </template>
                                                    <span x-text="gamemodeLabel(user.main_mode)"></span>
                                                </span>
                                            </div>
                                            <x-icon name="lucide-chevron-right" class="h-5 w-5 shrink-0 text-gray-600" />
                                        </a>
                                    </template>
                                </div>
                            </section>
                        </template>

                        <template x-for="section in mobileTournamentSections()" :key="section.key">
                            <section x-show="section.items.length > 0">
                                <h3 class="nav-mobile-section-title" x-text="section.label"></h3>
                                <div class="space-y-2">
                                    <template x-for="tournament in section.items" :key="`${section.key}-${tournament.id}`">
                                        <a :href="`/tournaments/${tournament.id}`" class="nav-mobile-result-row">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <p class="truncate font-tournament font-semibold text-white" x-text="tournament.title"></p>
                                                    <template x-if="tournament.role">
                                                        <span class="shrink-0 rounded-md bg-osu-pink/10 px-2 py-0.5 text-xs font-medium text-osu-pink" x-text="section.key === 'podium' ? tournament.role : localizeRole(tournament.role)"></span>
                                                    </template>
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <span class="text-sm text-gray-400" x-text="tournament.year"></span>
                                                    <span class="text-gray-500">&bull;</span>
                                                    <template x-for="mode in (tournament.modes || [])" :key="mode">
                                                        <span
                                                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium"
                                                            :class="gamemodeBadgeClasses(mode)"
                                                        >
                                                            <template x-if="gamemodeIcon(mode)">
                                                                <img :src="gamemodeIcon(mode)" alt="" class="h-3.5 w-3.5 object-contain opacity-90" aria-hidden="true">
                                                            </template>
                                                            <span x-text="gamemodeLabel(mode)"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                            <x-icon name="lucide-chevron-right" class="h-5 w-5 shrink-0 text-gray-600" />
                                        </a>
                                    </template>
                                </div>
                            </section>
                        </template>

                        <a x-show="totalMobileTournaments > 0"
                           :href="`/tournaments?q=${encodeURIComponent(mobileSearchQuery)}&tab=all`"
                           class="block text-center text-sm font-medium text-osu-pink hover:opacity-80"
                           x-text="'{{ __('search.search.view_all_tournaments') }}'.replace(':count', totalMobileTournaments)">
                        </a>

                        <div x-show="!mobileSearchHasResults()" class="px-3 py-8 text-center text-sm text-gray-400" style="display: none;">
                            <p>{{ __('search.search.no_tournaments') }}</p>
                            <p class="mt-2">{{ __('search.search.no_players') }}</p>
                        </div>
                    </div>
                </div>

                <div x-show="mobilePanel === 'notifications'" class="space-y-3" style="display: none;">
                    <div class="flex items-start justify-between gap-3 px-1">
                        <div>
                            <h2 class="text-sm font-semibold text-white">{{ __('common.notifications.title') }}</h2>
                            <p class="text-xs text-gray-500">
                                <span x-text="mobileUnreadCount"></span>
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <button x-show="mobileNotifications.some((notification) => !notification.read_at)"
                                    type="button"
                                    @click="readAllMobileNotifications"
                                    class="text-xs font-semibold text-osu-pink hover:text-pink-300"
                                    style="display: none;">
                                {{ __('common.notifications.read_all') }}
                            </button>
                            <button x-show="mobileNotifications.some((notification) => notification.read_at)"
                                    type="button"
                                    @click="removeReadMobileNotifications"
                                    class="text-xs font-semibold text-gray-400 hover:text-red-300"
                                    style="display: none;">
                                {{ __('common.notifications.remove_read') }}
                            </button>
                        </div>
                    </div>

                    <template x-if="mobileNotificationsLoading">
                        <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('common.notifications.loading') }}</div>
                    </template>

                    <template x-if="!mobileNotificationsLoading && mobileNotifications.length === 0">
                        <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('common.notifications.empty') }}</div>
                    </template>

                    <div class="overflow-hidden rounded-lg border border-dark-700">
                        <template x-for="notification in mobileNotifications" :key="notification.id">
                            <div class="nav-mobile-notification-row"
                                 :class="notification.read_at ? 'bg-dark-850' : 'bg-red-500/10'">
                                <button type="button"
                                        @click="openMobileNotification(notification)"
                                        class="min-w-0 flex-1 text-left">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="text-sm font-semibold text-white" x-text="notification.title"></h3>
                                        <span class="shrink-0 text-[11px] text-gray-500" x-text="notification.created_label"></span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-gray-400" x-text="notification.body"></p>
                                </button>
                                <button type="button"
                                        @click.stop="dismissMobileNotification(notification)"
                                        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-500 transition hover:bg-dark-700 hover:text-white"
                                        aria-label="Dismiss notification">
                                    <x-icon name="lucide-x" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <!-- Guest: Tournaments -->
                <a href="{{ route('tournaments.index') }}"
                   class="nav-mobile-item {{ request()->routeIs('tournaments.*') ? 'text-osu-pink' : '' }}">
                    {{ __('common.nav.tournaments') }}
                </a>

                <!-- Guest: Login (border style, matches desktop) -->
                <a href="{{ route('login') }}"
                   class="block px-4 py-3 border border-osu-pink/50 text-osu-pink rounded-lg font-medium text-center hover:border-osu-pink hover:bg-osu-pink/10 transition-colors">
                    {{ __('common.nav.login_with_osu') }}
                </a>

                <div class="border-t border-dark-700 my-2"></div>

                <!-- Language Switcher -->
                <div class="px-0 py-2">
                    <x-language-switcher mobile />
                </div>
            @endauth
        </div>
    </div>
</nav>

<!-- Search Modal (outside navigation to avoid clipping) -->
@auth
    <x-search-modal />
@endauth

@push('scripts')
<script>
    // Register navigation and search modal Alpine components
    // This script runs after Livewire/Alpine are loaded
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('navigation', () => ({
            mobileMenuOpen: false,
            mobilePanel: 'menu',
            mobileSearchQuery: '',
            mobileSearchLoading: false,
            mobileSearchResults: { tournaments: [], staff_tournaments: [], podium_tournaments: [], users: [] },
            totalMobileTournaments: 0,
            mobileSearchDebounceTimer: null,
            mobileNotifications: [],
            mobileNotificationsLoading: false,
            mobileUnreadCount: 0,
            minQueryLength: 3,
            roleTranslations: @json(__('tournaments.staffs')),
            modeLabels: {
                'osu': 'osu!',
                'taiko': 'osu!taiko',
                'catch': 'osu!catch',
                'fruits': 'osu!catch',
                'mania': 'osu!mania'
            },
            notificationConfig: {
                @auth
                    indexUrl: @js(route('notifications.index')),
                    readAllUrl: @js(route('notifications.read-all')),
                    removeReadUrl: @js(route('notifications.destroy-read')),
                    readUrlTemplate: @js(route('notifications.read', ['notification' => '__ID__'])),
                    dismissUrlTemplate: @js(route('notifications.destroy', ['notification' => '__ID__'])),
                @else
                    indexUrl: null,
                    readAllUrl: null,
                    removeReadUrl: null,
                    readUrlTemplate: null,
                    dismissUrlTemplate: null,
                @endauth
            },

            init() {
                this.$watch('mobileMenuOpen', (isOpen) => {
                    document.body.classList.toggle('overflow-hidden', isOpen);
                });
            },

            toggleMobileMenu() {
                this.mobileMenuOpen = !this.mobileMenuOpen;
                if (this.mobileMenuOpen) {
                    this.mobilePanel = 'menu';
                    this.loadMobileNotifications();
                }
            },

            setMobilePanel(panel) {
                this.mobilePanel = panel;

                if (panel === 'search') {
                    this.$nextTick(() => {
                        if (this.$refs.mobileSearchInput) {
                            this.$refs.mobileSearchInput.focus();
                        }
                    });
                }

                if (panel === 'notifications') {
                    this.loadMobileNotifications();
                }
            },

            debouncedMobileSearch() {
                clearTimeout(this.mobileSearchDebounceTimer);

                if (this.mobileSearchQuery.length < this.minQueryLength) {
                    this.mobileSearchResults = { tournaments: [], staff_tournaments: [], podium_tournaments: [], users: [] };
                    this.totalMobileTournaments = 0;
                    this.mobileSearchLoading = false;
                    return;
                }

                this.mobileSearchLoading = true;
                this.mobileSearchDebounceTimer = setTimeout(() => this.performMobileSearch(), 300);
            },

            async performMobileSearch() {
                try {
                    const response = await fetch(`/api/search?q=${encodeURIComponent(this.mobileSearchQuery)}`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (response.ok) {
                        const data = await response.json();
                        this.mobileSearchResults = {
                            tournaments: data.tournaments || [],
                            staff_tournaments: data.staff_tournaments || [],
                            podium_tournaments: data.podium_tournaments || [],
                            users: data.users || [],
                        };
                        this.totalMobileTournaments = data.total_tournaments || 0;
                    } else if (response.status === 401) {
                        window.location.href = '{{ route('login') }}';
                    } else {
                        console.error('Search failed:', response.statusText, response.status);
                    }
                } catch (error) {
                    console.error('Search error:', error);
                } finally {
                    this.mobileSearchLoading = false;
                }
            },

            mobileTournamentSections() {
                return [
                    {
                        key: 'tournaments',
                        label: @js(__('search.search.tournaments')),
                        items: this.mobileSearchResults.tournaments,
                    },
                    {
                        key: 'staff',
                        label: 'Staff',
                        items: this.mobileSearchResults.staff_tournaments,
                    },
                    {
                        key: 'podium',
                        label: 'Podium',
                        items: this.mobileSearchResults.podium_tournaments,
                    },
                ];
            },

            mobileSearchHasResults() {
                return this.mobileSearchResults.users.length > 0
                    || this.mobileSearchResults.tournaments.length > 0
                    || this.mobileSearchResults.staff_tournaments.length > 0
                    || this.mobileSearchResults.podium_tournaments.length > 0;
            },

            async loadMobileNotifications() {
                if (!this.notificationConfig.indexUrl || this.mobileNotificationsLoading) {
                    return;
                }

                this.mobileNotificationsLoading = true;
                try {
                    const response = await fetch(this.notificationConfig.indexUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (response.ok) {
                        const data = await response.json();
                        this.mobileNotifications = data.notifications || [];
                        this.mobileUnreadCount = data.unread_count || 0;
                    }
                } finally {
                    this.mobileNotificationsLoading = false;
                }
            },

            async readAllMobileNotifications() {
                if (!this.notificationConfig.readAllUrl) {
                    return;
                }

                await fetch(this.notificationConfig.readAllUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                this.mobileNotifications = this.mobileNotifications.map((notification) => ({
                    ...notification,
                    read_at: notification.read_at || new Date().toISOString(),
                }));
                this.mobileUnreadCount = 0;
            },

            async removeReadMobileNotifications() {
                if (!this.notificationConfig.removeReadUrl) {
                    return;
                }

                const response = await fetch(this.notificationConfig.removeReadUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (response.ok) {
                    this.mobileNotifications = this.mobileNotifications.filter((notification) => !notification.read_at);
                    this.mobileUnreadCount = this.mobileNotifications.length;
                }
            },

            async openMobileNotification(notification) {
                if (!this.notificationConfig.readUrlTemplate) {
                    return;
                }

                await fetch(this.notificationConfig.readUrlTemplate.replace('__ID__', notification.id), {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                window.location.href = notification.action_url;
            },

            async dismissMobileNotification(notification) {
                if (!this.notificationConfig.dismissUrlTemplate) {
                    return;
                }

                const response = await fetch(this.notificationConfig.dismissUrlTemplate.replace('__ID__', notification.id), {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (response.ok) {
                    this.mobileNotifications = this.mobileNotifications.filter((item) => item.id !== notification.id);
                    this.mobileUnreadCount = this.mobileNotifications.filter((item) => !item.read_at).length;
                }
            },

            getFlagEmoji(countryCode) {
                if (!countryCode) return '';
                const codePoints = countryCode
                    .toUpperCase()
                    .split('')
                    .map(char => 127397 + char.charCodeAt(0));
                return String.fromCodePoint(...codePoints);
            },

            gamemodeLabel(mode) {
                return window.TourneyMethod?.gamemodes?.label(mode) || mode || '';
            },

            gamemodeIcon(mode) {
                return window.TourneyMethod?.gamemodes?.icon(mode);
            },

            gamemodeBadgeClasses(mode) {
                return window.TourneyMethod?.gamemodes?.badgeClasses(mode) || 'bg-slate-700/50 text-slate-300';
            },

            localizeRole(role) {
                if (!role) return '';
                if (role === 'Host') {
                    return this.roleTranslations['organizer'] || role;
                }
                if (role.includes('ðŸ†')) {
                    return role;
                }

                const normalizedRole = role.toLowerCase();
                return this.roleTranslations[normalizedRole] || role;
            },
        }));

        window.Alpine.data('searchModal', () => ({
            isOpen: false,
            query: '',
            loading: false,
            results: { tournaments: [], staff_tournaments: [], podium_tournaments: [], users: [] },
            totalTournaments: 0,
            totalStaffTournaments: 0,
            totalPodiumTournaments: 0,
            totalUsers: 0,
            debounceTimer: null,
            minQueryLength: 3,
            queryStorageKey: 'search-last-query',
            resultsStorageKey: 'search-last-results',
            roleTranslations: @json(__('tournaments.staffs')),
            gamemodeLabel(mode) {
                return window.TourneyMethod?.gamemodes?.label(mode) || mode || '';
            },

            gamemodeIcon(mode) {
                return window.TourneyMethod?.gamemodes?.icon(mode);
            },

            gamemodeBadgeClasses(mode) {
                return window.TourneyMethod?.gamemodes?.badgeClasses(mode) || 'bg-slate-700/50 text-slate-300';
            },

            open() {
                this.isOpen = true;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });

                // Load saved query from localStorage
                const savedQuery = localStorage.getItem(this.queryStorageKey);
                if (savedQuery && savedQuery.length >= this.minQueryLength) {
                    this.query = savedQuery;

                    // Load saved results if they exist
                    const savedResults = localStorage.getItem(this.resultsStorageKey);
                    if (savedResults) {
                        try {
                            const parsed = JSON.parse(savedResults);

                            // Check if results are stale (older than 5 minutes)
                            const MAX_AGE = 5 * 60 * 1000; // 5 minutes
                            const isStale = parsed.timestamp && (Date.now() - parsed.timestamp) > MAX_AGE;

                            // Only use saved results if query matches and not stale
                            if (parsed.query === this.query && !isStale) {
                                this.results = {
                                    tournaments: parsed.results?.tournaments || [],
                                    staff_tournaments: parsed.results?.staff_tournaments || [],
                                    podium_tournaments: parsed.results?.podium_tournaments || [],
                                    users: parsed.results?.users || [],
                                };
                                this.totalTournaments = parsed.totalTournaments;
                                this.totalStaffTournaments = parsed.totalStaffTournaments || 0;
                                this.totalPodiumTournaments = parsed.totalPodiumTournaments || 0;
                                this.totalUsers = parsed.totalUsers;
                                return; // Don't fetch again
                            }

                            // Results are stale, clear and fetch fresh
                            if (isStale) {
                                localStorage.removeItem(this.resultsStorageKey);
                            }
                        } catch (e) {
                            console.error('Failed to parse saved results:', e);
                            localStorage.removeItem(this.resultsStorageKey);
                        }
                    }

                    // No saved results, query mismatch, or stale results, fetch from API
                    this.debouncedSearch();
                }
            },

            close() {
                this.isOpen = false;
                // Don't clear query or results - preserve them for next time
                this.loading = false;
                clearTimeout(this.debounceTimer);
            },

            debouncedSearch() {
                clearTimeout(this.debounceTimer);

                // Save query to localStorage
                if (this.query.length >= this.minQueryLength) {
                    localStorage.setItem(this.queryStorageKey, this.query);
                } else {
                    localStorage.removeItem(this.queryStorageKey);
                    localStorage.removeItem(this.resultsStorageKey);
                }

                if (this.query.length < this.minQueryLength) {
                    this.results = { tournaments: [], staff_tournaments: [], podium_tournaments: [], users: [] };
                    this.totalTournaments = 0;
                    this.totalStaffTournaments = 0;
                    this.totalPodiumTournaments = 0;
                    this.totalUsers = 0;
                    this.loading = false;
                    return;
                }

                this.loading = true;
                this.debounceTimer = setTimeout(() => this.performSearch(), 300);
            },

            async performSearch() {
                try {
                    const response = await fetch(`/api/search?q=${encodeURIComponent(this.query)}`, {
                        method: 'GET',
                        credentials: 'same-origin', // Include cookies for authentication
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (response.ok) {
                        const data = await response.json();
                        this.results = {
                            tournaments: data.tournaments || [],
                            staff_tournaments: data.staff_tournaments || [],
                            podium_tournaments: data.podium_tournaments || [],
                            users: data.users || [],
                        };
                        this.totalTournaments = data.total_tournaments || 0;
                        this.totalStaffTournaments = data.total_staff_tournaments || 0;
                        this.totalPodiumTournaments = data.total_podium_tournaments || 0;
                        this.totalUsers = data.total_users || 0;

                        // Save results to localStorage
                        localStorage.setItem(this.resultsStorageKey, JSON.stringify({
                            query: this.query,
                            results: this.results,
                            totalTournaments: this.totalTournaments,
                            totalStaffTournaments: this.totalStaffTournaments,
                            totalPodiumTournaments: this.totalPodiumTournaments,
                            totalUsers: this.totalUsers,
                            timestamp: Date.now(),
                        }));
                    } else if (response.status === 401) {
                        console.error('Search failed: Unauthorized');
                        window.location.href = '{{ route('login') }}';
                    } else {
                        console.error('Search failed:', response.statusText, response.status);
                    }
                } catch (error) {
                    console.error('Search error:', error);
                } finally {
                    this.loading = false;
                }
            },

            getFlagEmoji(countryCode) {
                if (!countryCode) return '';
                const codePoints = countryCode
                    .toUpperCase()
                    .split('')
                    .map(char => 127397 + char.charCodeAt(0));
                return String.fromCodePoint(...codePoints);
            },

            localizeRole(role) {
                if (!role) return '';
                
                // Map "Host" to "organizer" translation
                if (role === 'Host') {
                    return this.roleTranslations['organizer'] || role;
                }

                // If it contains an emoji (likely podium like "🏆 #1"), return as is
                if (role.includes('🏆')) {
                    return role;
                }
                
                const normalizedRole = role.toLowerCase();
                return this.roleTranslations[normalizedRole] || role;
            },

            init() {
                window.addEventListener('open-search', () => {
                    this.open();
                });

                document.addEventListener('keydown', (e) => {
                    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                        e.preventDefault();
                        this.isOpen ? this.close() : this.open();
                    }
                });
            },
        }));
    });
</script>
@endpush
