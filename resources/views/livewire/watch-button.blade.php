<div
    class="relative inline-block"
    x-data="{
        open: false,
        loading: false,
        watchType: '{{ $watchType }}',
        showWatching: {{ $showWatching ? 'true' : 'false' }},
        async watch(type) {
            this.loading = true;
            try {
                const response = await fetch(`/tournaments/{{ $tournamentId }}/watch`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ watch_type: type })
                });
                if (response.ok) {
                    const data = await response.json();
                    this.watchType = data.watch_type;
                    this.open = false;
                }
            } finally {
                this.loading = false;
            }
        },
        async unwatch() {
            this.loading = true;
            try {
                const response = await fetch(`/tournaments/{{ $tournamentId }}/watch`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                if (response.ok) {
                    this.watchType = null;
                    this.open = false;
                }
            } finally {
                this.loading = false;
            }
        }
    }"
    @click.outside="open = false"
>
    {{-- Main Watch Button --}}
    <button
        @click="open = !open"
        :disabled="loading"
        :class="{
            'group relative flex items-center gap-2 px-4 py-2.5 rounded-xl font-display font-semibold text-sm': true,
            'transition-all duration-300 ease-out transform-gpu': true,
            'focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark-900': true,
            'bg-dark-800/80 border border-dark-600 text-gray-400 hover:text-white hover:border-osu-pink/50 hover:bg-dark-700 focus:ring-osu-pink/30': !watchType,
            'bg-gradient-to-r from-osu-pink/20 to-pink-500/10 border border-osu-pink/40 text-osu-pink hover:border-osu-pink hover:shadow-lg hover:shadow-osu-pink/20 focus:ring-osu-pink/30': watchType === 'watching',
            'bg-gradient-to-r from-purple-500/20 to-violet-500/10 border border-purple-400/40 text-purple-400 hover:border-purple-400 hover:shadow-lg hover:shadow-purple-500/20 focus:ring-purple-400/30': watchType === 'stream'
        }"
    >
        {{-- Loading spinner (inline, non-blocking) --}}
        <x-icon name="lucide-loader-circle" x-show="loading" class="w-4 h-4 animate-spin" />

        {{-- Icon based on state --}}
        <span x-show="!loading" class="text-lg transition-transform duration-200 group-hover:scale-110">
            <span x-show="!watchType">
                {{-- Bookmark outline --}}
                <x-icon name="lucide-bookmark" class="w-5 h-5" />
            </span>
            <span x-show="watchType === 'watching'">📺</span>
            <span x-show="watchType === 'stream'">🎥</span>
        </span>

        {{-- Label --}}
        <span x-show="!loading" class="hidden sm:inline">
            <span x-show="!watchType">{{ __('tournaments.subscriptions.title') }}</span>
            <span x-show="watchType === 'watching'">{{ __('tournaments.subscriptions.reminder') }}</span>
            <span x-show="watchType === 'stream'">{{ __('tournaments.subscriptions.stream') }}</span>
        </span>

        {{-- Dropdown indicator --}}
        <x-icon name="lucide-chevron-down" x-show="!loading" class="w-4 h-4 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-cloak
        x-show="open"
        class="absolute right-0 mt-2 w-56 z-50 origin-top-right"
    >
        <div class="rounded-xl bg-dark-800/95 backdrop-blur-xl border border-dark-600 shadow-2xl shadow-black/50 overflow-hidden">
            {{-- Options --}}
            <div class="py-2">
                @if($showWatching)
                    {{-- Watching --}}
                    <button
                        type="button"
                        @click="watch('watching')"
                        :class="{
                            'w-full flex items-center gap-3 px-4 py-3 text-left transition-all duration-150': true,
                            'hover:bg-osu-pink/10': true,
                            'bg-osu-pink/5 border-l-2 border-osu-pink': watchType === 'watching',
                            'border-l-2 border-transparent': watchType !== 'watching'
                        }"
                    >
                        <span class="text-xl">📺</span>
                        <div class="flex-1">
                            <p :class="{
                                'font-display font-semibold text-sm': true,
                                'text-osu-pink': watchType === 'watching',
                                'text-gray-200': watchType !== 'watching'
                            }">{{ __('tournaments.subscriptions.reminder') }}</p>
                            <p class="text-xs text-gray-500">{{ __('tournaments.subscriptions.reminder_desc') }}</p>
                        </div>
                        <x-icon name="lucide-check" x-show="watchType === 'watching'" class="w-5 h-5 text-osu-pink" />
                    </button>
                @endif

                {{-- Stream --}}
                <button
                    type="button"
                    @click="watch('stream')"
                    :class="{
                        'w-full flex items-center gap-3 px-4 py-3 text-left transition-all duration-150': true,
                        'hover:bg-purple-500/10': true,
                        'bg-purple-500/5 border-l-2 border-purple-400': watchType === 'stream',
                        'border-l-2 border-transparent': watchType !== 'stream'
                    }"
                >
                    <span class="text-xl">🎥</span>
                    <div class="flex-1">
                        <p :class="{
                            'font-display font-semibold text-sm': true,
                            'text-purple-400': watchType === 'stream',
                            'text-gray-200': watchType !== 'stream'
                        }">{{ __('tournaments.subscriptions.stream') }}</p>
                        <p class="text-xs text-gray-500">{{ __('tournaments.subscriptions.stream_desc') }}</p>
                    </div>
                    <x-icon name="lucide-check" x-show="watchType === 'stream'" class="w-5 h-5 text-purple-400" />
                </button>
            </div>

            {{-- Unwatch option (only if watching) --}}
            <div x-show="watchType" class="border-t border-dark-700 py-2">
                <button
                    type="button"
                    @click="unwatch()"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left transition-all duration-150
                        text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                >
                        <x-icon name="lucide-x" class="w-5 h-5" />
                        <span class="font-display font-medium text-sm">{{ __('tournaments.subscriptions.stop') }}</span>
                    </button>
            </div>
        </div>
    </div>
</div>
