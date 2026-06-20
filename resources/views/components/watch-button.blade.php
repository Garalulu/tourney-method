@props(['tournament', 'showWatching' => true])

@auth
    <livewire:watch-button :tournament="$tournament" :show-watching="$showWatching" />
@else
    {{-- Guest state - shows login prompt --}}
    <a
        href="{{ route('login') }}"
        class="group relative flex items-center gap-2 px-4 py-2.5 rounded-xl font-display font-semibold text-sm
            bg-dark-800/80 border border-dark-600 text-gray-400
            hover:text-white hover:border-osu-pink/50 hover:bg-dark-700
            transition-all duration-300 ease-out transform-gpu
            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark-900 focus:ring-osu-pink/30"
    >
        <x-icon name="lucide-bookmark" class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
        <span class="hidden sm:inline">Watch</span>
    </a>
@endauth
