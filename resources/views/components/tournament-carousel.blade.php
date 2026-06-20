@props(['tournaments', 'id' => 'carousel'])

<div
    x-data="{
        scrollContainer: null,
        canScrollLeft: false,
        canScrollRight: true,
        init() {
            this.scrollContainer = this.$refs.container;
            this.checkScroll();
            this.scrollContainer.addEventListener('scroll', () => this.checkScroll());
            window.addEventListener('resize', () => this.checkScroll());
        },
        checkScroll() {
            if (!this.scrollContainer) return;
            this.canScrollLeft = this.scrollContainer.scrollLeft > 0;
            this.canScrollRight = this.scrollContainer.scrollLeft < (this.scrollContainer.scrollWidth - this.scrollContainer.clientWidth - 10);
        },
        scrollLeft() {
            this.scrollContainer.scrollBy({ left: -340, behavior: 'smooth' });
        },
        scrollRight() {
            this.scrollContainer.scrollBy({ left: 340, behavior: 'smooth' });
        }
    }"
    class="relative"
>
    {{-- Left Navigation Arrow --}}
    <button
        x-show="canScrollLeft"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-2"
        @click="scrollLeft()"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 -ml-4
            bg-dark-800/95 backdrop-blur-sm border border-dark-600 rounded-full
            flex items-center justify-center
            text-white hover:text-osu-pink hover:border-osu-pink/50
            shadow-xl shadow-black/30
            transition-all duration-200
            focus:outline-none focus:ring-2 focus:ring-osu-pink focus:ring-offset-2 focus:ring-offset-dark-900"
        aria-label="Scroll left"
    >
        <x-icon name="lucide-chevron-left" class="w-5 h-5" />
    </button>

    {{-- Right Navigation Arrow --}}
    <button
        x-show="canScrollRight"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-2"
        @click="scrollRight()"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 -mr-4
            bg-dark-800/95 backdrop-blur-sm border border-dark-600 rounded-full
            flex items-center justify-center
            text-white hover:text-osu-pink hover:border-osu-pink/50
            shadow-xl shadow-black/30
            transition-all duration-200
            focus:outline-none focus:ring-2 focus:ring-osu-pink focus:ring-offset-2 focus:ring-offset-dark-900"
        aria-label="Scroll right"
    >
        <x-icon name="lucide-chevron-right" class="w-5 h-5" />
    </button>

    {{-- Gradient Fade Edges --}}
    <div
        x-show="canScrollLeft"
        class="absolute left-0 top-0 bottom-0 w-16 bg-gradient-to-r from-dark-900 to-transparent pointer-events-none z-[5]"
    ></div>
    <div
        x-show="canScrollRight"
        class="absolute right-0 top-0 bottom-0 w-16 bg-gradient-to-l from-dark-900 to-transparent pointer-events-none z-[5]"
    ></div>

    {{-- Scrollable Container --}}
    <div
        x-ref="container"
        class="flex gap-4 overflow-x-auto scrollbar-hide scroll-smooth pb-2 -mb-2"
        style="scrollbar-width: none; -ms-overflow-style: none;"
    >
        @foreach($tournaments as $tournament)
            <div class="flex-shrink-0 w-80">
                <x-tournament-card :tournament="$tournament" />
            </div>
        @endforeach
    </div>
</div>

<style>
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
</style>
