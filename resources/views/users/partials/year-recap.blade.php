<!-- Year Recap Tab -->
<div class="space-y-6"
     x-data="yearRecap()">
    @if(auth()->check() && auth()->id() === $user->id)
        <!-- Generation Controls -->
        <x-ui.panel variant="muted" padding="md">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white font-display flex items-center gap-2">
                        <x-icon name="lucide-image" class="w-5 h-5 text-pink-400" />
                        Year-End Recap
                    </h2>
                    <p class="text-slate-400 text-sm mt-1">
                        Generate a beautiful recap image of your yearly achievements to share on social media.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Year Selector -->
                    <select x-model="selectedYear"
                            class="px-4 py-2 bg-slate-900/50 border border-slate-700/50 rounded-lg text-white text-sm
                                   focus:outline-none focus:border-pink-500/50 focus:ring-2 focus:ring-pink-500/20">
                        <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                        <option value="{{ date('Y') - 1 }}">{{ date('Y') - 1 }}</option>
                        <option value="{{ date('Y') - 2 }}">{{ date('Y') - 2 }}</option>
                    </select>

                    <!-- Generate Button -->
                    <button @click="generateRecap()"
                            :disabled="isGenerating"
                            class="px-5 py-2 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700
                                   text-white font-semibold rounded-lg text-sm transition-all duration-200
                                   disabled:opacity-50 disabled:cursor-not-allowed
                                   shadow-lg shadow-pink-500/25 hover:shadow-pink-500/40 font-display">
                        <span x-show="!isGenerating" class="flex items-center gap-2">
                            <x-icon name="lucide-image" class="w-4 h-4" />
                            Generate Recap
                        </span>
                        <span x-show="isGenerating" class="flex items-center gap-2">
                            <x-icon name="lucide-loader-circle" class="w-4 h-4 animate-spin" />
                            Generating...
                        </span>
                    </button>
                </div>
            </div>
        </x-ui.panel>

        <!-- Generated Image Preview -->
        <x-ui.panel x-show="imageUrl" x-transition variant="muted">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white font-semibold font-display">Your @{{ selectedYear }} Recap</h3>
                    <a :href="`/users/me/recap/${selectedYear}/download`"
                       class="px-4 py-2 bg-slate-700/50 hover:bg-slate-700 text-white text-sm rounded-lg
                              transition-colors duration-200 flex items-center gap-2">
                        <x-icon name="lucide-download" class="w-4 h-4" />
                        Download PNG
                    </a>
                </div>
                <div class="relative bg-slate-900 rounded-lg overflow-hidden">
                    <img :src="imageUrl" alt="Year Recap" class="w-full h-auto">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/20 to-transparent pointer-events-none"></div>
                </div>

                <!-- Share Buttons -->
                <div class="flex items-center gap-3 mt-4">
                    <span class="text-slate-400 text-sm">Share:</span>
                    <button class="p-2 bg-[#5865F2] hover:bg-[#4752C4] rounded-lg transition-colors">
                        <x-icon name="si-discord" class="w-5 h-5 text-white" />
                    </button>
                    <button class="p-2 bg-[#1DA1F2] hover:bg-[#0C85D0] rounded-lg transition-colors">
                        <x-icon name="si-x" class="w-5 h-5 text-white" />
                    </button>
                </div>
            </div>
        </x-ui.panel>

        <!-- Error Message -->
        <div x-show="errorMessage" x-transition class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
            <p class="text-red-400 text-sm" x-text="errorMessage"></p>
        </div>
    @else
        <!-- Not Your Profile Notice -->
        <div class="bg-slate-800/30 backdrop-blur-sm rounded-xl border border-slate-700/50 p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-900/50 flex items-center justify-center
                        border border-slate-700/50">
                <x-icon name="lucide-lock" class="w-8 h-8 text-slate-500" />
            </div>
            <h3 class="text-lg font-semibold text-slate-400 mb-2 font-display">Private Feature</h3>
            <p class="text-slate-500 text-sm max-w-sm mx-auto">
                You can only generate and view your own year recap. Sign in to create yours!
            </p>
        </div>
    @endif

    <!-- Info Card -->
    <div class="bg-gradient-to-br from-pink-500/10 to-purple-500/10 rounded-xl border border-pink-500/20 p-6">
        <div class="flex items-start gap-4">
            <div class="p-3 bg-pink-500/20 rounded-lg">
                <x-icon name="lucide-info" class="w-6 h-6 text-pink-400" />
            </div>
            <div>
                <h4 class="text-white font-semibold mb-1 font-display">About Year Recaps</h4>
                <p class="text-slate-400 text-sm">
                    Year recaps are automatically generated images (1200x630px) showcasing your tournament participation, match statistics, badges earned, and most frequent teammates/opponents. Perfect for sharing on social media!
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function yearRecap() {
    return {
        selectedYear: '{{ date('Y') }}',
        isGenerating: false,
        imageUrl: null,
        errorMessage: null,

        async generateRecap() {
            this.isGenerating = true;
            this.errorMessage = null;
            try {
                const response = await fetch(`/users/me/recap/${this.selectedYear}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.imageUrl = data.image_url;
                } else {
                    const error = await response.json();
                    this.errorMessage = error.message || 'Failed to generate recap';
                }
            } catch (error) {
                console.error('Error generating recap:', error);
                this.errorMessage = 'Network error. Please try again.';
            } finally {
                this.isGenerating = false;
            }
        },
    }
}
</script>
