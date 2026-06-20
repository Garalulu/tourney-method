<div class="tournament-page" x-data="{
    screenSize: 'desktop',
    isAtTop: true,
    previousScrollPosition: 0,
    isScrollingProgrammatically: false,
    init() {
        this.updateScreenSize();
        window.addEventListener('resize', () => this.updateScreenSize());
        window.addEventListener('scroll', () => this.handleScroll());
    },
    updateScreenSize() {
        const width = window.innerWidth;
        if (width < 768) {
            this.screenSize = 'mobile';
        } else {
            this.screenSize = 'desktop';
        }
        // Notify Livewire of batch size change
        @this.setBatchSize(this.screenSize === 'mobile' ? 12 : 18);
    },
    toggleScroll() {
        if (this.isAtTop) {
            // Only proceed if not already at top
            if (window.scrollY === 0) {
                return;
            }
            
            // First click: Store current position and scroll to top
            this.isScrollingProgrammatically = true;
            this.previousScrollPosition = window.scrollY;
            
            // Force instant scroll (override any smooth scroll CSS)
            document.documentElement.style.scrollBehavior = 'auto';
            document.body.style.scrollBehavior = 'auto';
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
            
            this.isAtTop = false;
            
            // Clean up after scroll completes
            setTimeout(() => {
                this.isScrollingProgrammatically = false;
                document.documentElement.style.scrollBehavior = '';
                document.body.style.scrollBehavior = '';
            }, 100);
        } else {
            // Second click: Scroll back to previous position
            this.isScrollingProgrammatically = true;
            
            // Force instant scroll
            document.documentElement.style.scrollBehavior = 'auto';
            document.body.style.scrollBehavior = 'auto';
            document.documentElement.scrollTop = this.previousScrollPosition;
            document.body.scrollTop = this.previousScrollPosition;
            
            this.isAtTop = true;
            
            // Clean up after scroll completes
            setTimeout(() => {
                this.isScrollingProgrammatically = false;
                document.documentElement.style.scrollBehavior = '';
                document.body.style.scrollBehavior = '';
            }, 100);
        }
    },
    handleScroll() {
        // Ignore programmatic scrolls (prevent state reset)
        if (this.isScrollingProgrammatically) {
            return;
        }
        
        // Reset to initial state if user manually scrolls down
        if (!this.isAtTop && window.scrollY > 0) {
            this.isAtTop = true;
            this.previousScrollPosition = 0;
        }
    }
}">
    {{-- Filter Section --}}
    <div class="tournament-filter-section">
        <div class="tournament-filter-container">
            <h2 class="tournament-section-title">{{ __('tournaments.title') }}</h2>

            {{-- Tab Navigation --}}
            <div class="flex gap-2 mb-8">
                <button
                    wire:click="$set('activeTab', 'active')"
                    class="tournament-tab-button @if($activeTab === 'active') tournament-tab-active @elseif($activeTab === 'ended') tournament-tab-inactive @else tournament-tab-inactive @endif">
                    {{ __('tournaments.filters.active') }}
                </button>

                <button
                    wire:click="$set('activeTab', 'ended')"
                    class="tournament-tab-button @if($activeTab === 'ended') tournament-tab-active @elseif($activeTab === 'active') tournament-tab-inactive-alt @else tournament-tab-inactive-alt @endif">
                    {{ __('tournaments.filters.ended') }}
                </button>
            </div>

            {{-- Filter Controls (Single Column) --}}
            <div class="tournament-filter-group">
                {{-- Search --}}
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <label class="min-w-0 flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('tournaments.filters.search_placeholder') }}"
                            class="tournament-search-input">
                    </label>
                    <a href="{{ auth()->check() ? route('tournaments.add') : route('login') }}" class="shrink-0 rounded-lg bg-osu-pink px-4 py-3 text-center text-sm font-semibold text-white shadow-lg shadow-osu-pink/20 transition hover:bg-osu-pink/80">
                        {{ auth()->check() ? __('tournaments.add.action') : __('tournaments.add.login_action') }}
                    </a>
                </div>
            </div>

            {{-- Game Mode Section --}}
            <div class="tournament-mode-section">
                <h3 class="tournament-mode-section-title">{{ __('tournaments.filters.mode') }}</h3>
                <div class="tournament-mode-group">
                    <label class="tournament-mode-label group">
                        <input
                            type="radio"
                            name="mode"
                            value=""
                            wire:model.live="mode"
                            class="hidden peer">
                        <span class="tournament-mode-text tournament-mode-text-checked">
                            {{ __('tournaments.filters.all_modes') }}
                        </span>
                    </label>

                    <label class="tournament-mode-label group">
                        <input
                            type="radio"
                            name="mode"
                            value="osu"
                            wire:model.live="mode"
                            class="hidden peer">
                        <span class="tournament-mode-text tournament-mode-text-checked">
                            osu!
                        </span>
                    </label>

                    <label class="tournament-mode-label group">
                        <input
                            type="radio"
                            name="mode"
                            value="taiko"
                            wire:model.live="mode"
                            class="hidden peer">
                        <span class="tournament-mode-text tournament-mode-text-checked">
                            osu!taiko
                        </span>
                    </label>

                    <label class="tournament-mode-label group">
                        <input
                            type="radio"
                            name="mode"
                            value="catch"
                            wire:model.live="mode"
                            class="hidden peer">
                        <span class="tournament-mode-text tournament-mode-text-checked">
                            osu!catch
                        </span>
                    </label>

                    <label class="tournament-mode-label group">
                        <input
                            type="radio"
                            name="mode"
                            value="mania"
                            wire:model.live="mode"
                            class="hidden peer">
                        <span class="tournament-mode-text tournament-mode-text-checked">
                            osu!mania
                        </span>
                    </label>
                </div>
            </div>

            {{-- Filter Options (Checkboxes + Year) --}}
            <div class="tournament-options-section">
                <div class="tournament-options-layout">
                    {{-- Left: Checkboxes --}}
                    <div class="flex-1">
                        <h3 class="tournament-mode-section-title">{{ __('tournaments.filters.filter_options', ['default' => 'Filter Options']) }}</h3>
                        <div class="tournament-checkbox-group">
                            <label class="tournament-checkbox-label group">
                                <input
                                    type="checkbox"
                                    wire:model.live="badgeOnly"
                                    class="hidden peer">
                                <span class="tournament-checkbox-text tournament-checkbox-text-checked">
                                    {{ __('tournaments.status.badged') }}
                                </span>
                            </label>

                            @if($activeTab === 'active' || $activeTab === 'all')
                                @auth
                                    <label class="tournament-checkbox-label group">
                                        <input
                                            type="checkbox"
                                            wire:model.live="eligibleOnly"
                                            class="hidden peer">
                                        <span class="tournament-checkbox-text tournament-checkbox-text-checked">
                                            {{ __('tournaments.filters.eligible') }}
                                        </span>
                                    </label>

                                    <label class="tournament-checkbox-label group">
                                        <input
                                            type="checkbox"
                                            wire:model.live="regOpenOnly"
                                            class="hidden peer">
                                        <span class="tournament-checkbox-text tournament-checkbox-text-checked">
                                            {{ __('tournaments.filters.reg_open') }}
                                        </span>
                                    </label>
                                @endauth
                            @endif
                        </div>
                    </div>

                    {{-- Right: Year (ended tab or all tab) --}}
                    @if($activeTab === 'ended' || $activeTab === 'all')
                        <div class="flex-shrink-0">
                            <label class="tournament-filter-label">{{ __('tournaments.filters.year') }}</label>
                            <select
                                wire:model.live="selectedYear"
                                class="tournament-year-select">
                                <option value="">{{ __('tournaments.filters.all_years') }}</option>
                                @foreach($availableYears as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tournament Grid --}}
    <div class="tournament-grid-section">
        <div class="tournament-grid-container">
            @if($tournaments->count() > 0)
                {{-- Tournament Grid with loading state --}}
                <div 
                    wire:loading.delay.class="tournament-grid-loading"
                    wire:target="mode, badgeOnly, eligibleOnly, regOpenOnly, search, activeTab, selectedYear"
                    class="tournament-grid">
                    @foreach($tournaments as $tournament)
                        <div wire:key="tournament-{{ $tournament->id }}">
                            <x-tournament-card :tournament="$tournament" />
                        </div>
                    @endforeach
                </div>

                {{-- Text-based loading indicator (philosophy-aligned) --}}
                <div 
                    wire:loading.delay
                    wire:target="mode, badgeOnly, eligibleOnly, regOpenOnly, search, activeTab, selectedYear"
                    class="text-center py-8">
                    <p class="text-gray-400 text-sm">{{ __('tournaments.loading.title') }}</p>
                </div>

                {{-- Load More Button --}}
                @if($hasMorePages)
                    <div class="tournament-load-more">
                        <button
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="tournament-load-more-button">
                            <span wire:loading.remove>{{ __('tournaments.loading.load_more') }}</span>
                            <span wire:loading>{{ __('tournaments.loading.loading') }}</span>
                        </button>
                        <p class="tournament-load-more-count">
                            {{ __('tournaments.loading.load_more_count', ['count' => $tournaments->count(), 'total' => $totalCount]) }}
                        </p>
                    </div>
                @endif
            @else
                {{-- Empty State --}}
                <div class="tournament-empty-state">
                    <h3 class="tournament-empty-title">{{ __('tournaments.empty.no_tournaments') }}</h3>
                    <p class="tournament-empty-description">
                        @if($search)
                            {{ __('tournaments.empty.no_results', ['search' => $search]) }}
                        @else
                            {{ __('tournaments.empty.try') }}
                        @endif
                    </p>
                    <div class="tournament-empty-actions">
                        @if($search)
                            <a
                                href="#"
                                wire:click.prevent="$set('search', '')"
                                class="tournament-clear-link">
                                {{ __('tournaments.empty.clear_search') }}
                            </a>
                            @if($mode || $badgeOnly || $eligibleOnly || $regOpenOnly)
                                <span class="tournament-separator">•</span>
                            @endif
                        @endif

                        @if($mode)
                            <a
                                href="#"
                                wire:click.prevent="$set('mode', '')"
                                class="tournament-clear-link">
                                {{ __('tournaments.empty.clear_mode') }}
                            </a>
                            @if($badgeOnly || $eligibleOnly || $regOpenOnly)
                                <span class="tournament-separator">•</span>
                            @endif
                        @endif

                        @if($badgeOnly)
                            <a
                                href="#"
                                wire:click.prevent="$set('badgeOnly', false)"
                                class="tournament-clear-link">
                                {{ __('tournaments.empty.clear_badge') }}
                            </a>
                            @if($eligibleOnly || $regOpenOnly)
                                <span class="tournament-separator">•</span>
                            @endif
                        @endif

                        @if($eligibleOnly)
                            <a
                                href="#"
                                wire:click.prevent="$set('eligibleOnly', false)"
                                class="tournament-clear-link">
                                {{ __('tournaments.empty.clear_eligible') }}
                            </a>
                            @if($regOpenOnly)
                                <span class="tournament-separator">•</span>
                            @endif
                        @endif

                        @if($regOpenOnly)
                            <a
                                href="#"
                                wire:click.prevent="$set('regOpenOnly', false)"
                                class="tournament-clear-link">
                                {{ __('tournaments.empty.clear_reg') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Back to Top Button --}}
    <div class="back-to-top-button">
        <button 
            @click="toggleScroll()"
            class="back-to-top-circle"
            aria-label="Toggle scroll position"
            title="back to top">
            
            {{-- Up Arrow Icon (^) --}}
            <x-icon name="lucide-chevron-up" x-show="isAtTop" class="back-to-top-icon" />
            
            {{-- Down Arrow Icon (v) --}}
            <x-icon name="lucide-chevron-down" x-show="!isAtTop" class="back-to-top-icon" />
        </button>
    </div>
</div>
