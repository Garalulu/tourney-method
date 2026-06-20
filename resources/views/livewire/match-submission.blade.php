<div class="space-y-6">
    {{-- Success Message --}}
    @if ($successMessage)
        <div class="animate-slideIn rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 p-5 shadow-lg shadow-emerald-500/20 transform transition-all duration-300">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <x-icon name="lucide-check" class="w-6 h-6 text-white animate-bounce" />
                </div>
                <div class="flex-1">
                    <h3 class="text-white font-semibold text-lg mb-1">Match Submitted!</h3>
                    <p class="text-white/90 text-sm">{{ $successMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Error Message --}}
    @if ($errorMessage)
        <div class="animate-slideIn rounded-2xl bg-gradient-to-r from-red-500 to-rose-500 p-5 shadow-lg shadow-red-500/20 transform transition-all duration-300">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <x-icon name="lucide-circle-alert" class="w-6 h-6 text-white" />
                </div>
                <div class="flex-1">
                    <h3 class="text-white font-semibold text-lg mb-1">Oops!</h3>
                    <p class="text-white/90 text-sm">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Submission Form --}}
    <form wire:submit.prevent="submit" class="space-y-6">
        {{-- MP Link Input --}}
        <div class="group">
            <label for="mpLink" class="block text-sm font-semibold text-gray-800 mb-2 tracking-wide uppercase">
                Multiplayer Link
            </label>
            <div class="relative">
                <input
                    type="text"
                    id="mpLink"
                    wire:model="mpLink"
                    placeholder="https://osu.ppy.sh/community/matches/123456"
                    class="w-full px-5 py-4 text-base rounded-xl border-2 border-gray-200 bg-white/50 backdrop-blur-sm
                           focus:border-pink-400 focus:bg-white focus:ring-4 focus:ring-pink-500/10
                           transition-all duration-300 placeholder:text-gray-400
                           disabled:opacity-50 disabled:cursor-not-allowed
                           hover:border-gray-300 hover:shadow-md"
                    :disabled="$loading"
                >
                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none opacity-40 group-hover:opacity-60 transition-opacity">
                    <x-icon name="lucide-link" class="w-5 h-5 text-pink-500" />
                </div>
            </div>
            @error('mpLink')
                <p class="mt-2 text-sm text-red-600 font-medium animate-slideIn">{{ $message }}</p>
            @enderror
            <p class="mt-2.5 text-sm text-gray-500 flex items-center gap-2">
                <x-icon name="lucide-info" class="w-4 h-4 text-pink-500" />
                <span>Paste the link to your multiplayer match from osu!</span>
            </p>
        </div>

        {{-- Tournament Selector --}}
        <div class="group">
            <label for="tournamentId" class="block text-sm font-semibold text-gray-800 mb-2 tracking-wide uppercase">
                Tournament <span class="text-gray-400 font-normal normal-case text-xs">(Optional)</span>
            </label>
            <div class="relative">
                <select
                    id="tournamentId"
                    wire:model="tournamentId"
                    class="w-full px-5 py-4 text-base rounded-xl border-2 border-gray-200 bg-white/50 backdrop-blur-sm appearance-none
                           focus:border-purple-400 focus:bg-white focus:ring-4 focus:ring-purple-500/10
                           transition-all duration-300
                           disabled:opacity-50 disabled:cursor-not-allowed
                           hover:border-gray-300 hover:shadow-md cursor-pointer"
                    :disabled="$loading"
                >
                    <option value="">🎯 Auto-detect tournament...</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}">{{ $tournament->title }}</option>
                    @endforeach
                </select>
                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none">
                    <x-icon name="lucide-chevron-down" class="w-5 h-5 text-purple-500 transition-transform group-hover:translate-y-0.5" />
                </div>
            </div>
            @error('tournamentId')
                <p class="mt-2 text-sm text-red-600 font-medium animate-slideIn">{{ $message }}</p>
            @enderror
            <p class="mt-2.5 text-sm text-gray-500 flex items-center gap-2">
                <x-icon name="lucide-zap" class="w-4 h-4 text-purple-500" />
                <span>We'll try to auto-detect if not specified</span>
            </p>
        </div>

        {{-- Submit Button --}}
        <div class="pt-2">
            <button
                type="submit"
                class="group relative w-full px-8 py-5 text-lg font-bold text-white rounded-xl
                       bg-gradient-to-r from-pink-500 via-pink-600 to-purple-600
                       hover:from-pink-600 hover:via-pink-700 hover:to-purple-700
                       shadow-xl shadow-pink-500/30 hover:shadow-2xl hover:shadow-pink-500/40
                       transform transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]
                       disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:scale-100
                       overflow-hidden"
                :disabled="$loading"
                wire:loading.attr="disabled"
            >
                {{-- Animated background gradient --}}
                <div class="absolute inset-0 bg-gradient-to-r from-pink-400 via-purple-500 to-pink-400 opacity-0 group-hover:opacity-100 transition-opacity duration-500 animate-gradientFlow"></div>

                {{-- Button content --}}
                <span class="relative flex items-center justify-center gap-3">
                    <span wire:loading.remove wire:target="submit">
                        <x-icon name="lucide-zap" class="w-6 h-6 animate-pulse" />
                    </span>
                    <span wire:loading wire:target="submit">
                        <x-icon name="lucide-loader-circle" class="w-6 h-6 animate-spin" />
                    </span>
                    <span wire:loading.remove wire:target="submit">Submit Match</span>
                    <span wire:loading wire:target="submit">Processing...</span>
                </span>
            </button>
        </div>
    </form>
</div>

<style>
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes gradientFlow {
        0%, 100% {
            background-position: 0% 50%;
        }
        50% {
            background-position: 100% 50%;
        }
    }

    .animate-slideIn {
        animation: slideIn 0.3s ease-out;
    }

    .animate-gradientFlow {
        background-size: 200% 200%;
        animation: gradientFlow 3s ease infinite;
    }
</style>
