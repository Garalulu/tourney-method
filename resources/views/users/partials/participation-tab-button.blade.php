<button @click="activeTab = 'participation'"
        :class="activeTab === 'participation' ? 'bg-pink-500 text-white shadow-lg shadow-pink-500/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-700/50'"
        class="flex-1 sm:flex-none px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 font-display">
    <span class="flex items-center justify-center gap-2">
        <x-icon name="lucide-list-check" class="w-4 h-4" />
        {{ __('users.tabs.participation') }}
    </span>
</button>
