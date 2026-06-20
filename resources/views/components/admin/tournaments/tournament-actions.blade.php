@props([
    'tournament' => null,
])

{{-- Only show for rejected tournaments (approved tournaments use "Return to Pending" in final-actions) --}}
@if($tournament->status === 'rejected')
    <div class="mb-6 flex items-center justify-between p-4 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
        <div class="text-sm">
            <span class="text-[var(--admin-muted)]">This tournament was rejected. You can restore it to pending review.</span>
        </div>
        <div class="flex items-center space-x-2">
            <button onclick="restoreTournament({{ $tournament->id }})"
                    class="inline-flex items-center px-4 py-2 rounded-lg bg-green-500 text-white font-medium hover:brightness-110 transition-all shadow-lg">
                <x-icon name="lucide-refresh-cw" class="w-4 h-4 mr-2" />
                Restore
            </button>

            {{-- Delete button --}}
            <div x-data="{ showDeleteConfirm: false }" class="relative">
                <button x-show="!showDeleteConfirm"
                        @click="showDeleteConfirm = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-red-500 text-white font-medium hover:brightness-110 transition-all shadow-lg">
                    <x-icon name="lucide-trash-2" class="w-4 h-4 mr-2" />
                    Delete
                </button>

                <div x-show="showDeleteConfirm"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 mt-2 w-72 bg-[var(--admin-surface)] rounded-lg border border-red-500/30 shadow-xl z-50 p-4">
                    <div class="mb-3">
                        <h4 class="font-semibold text-red-400 mb-1">Delete Tournament?</h4>
                        <p class="text-sm text-[var(--admin-muted)]">This will soft delete the tournament. You can restore it later if needed.</p>
                    </div>
                    <div class="flex items-center justify-end space-x-2">
                        <button @click="showDeleteConfirm = false"
                                class="px-3 py-1.5 rounded text-sm font-medium text-[var(--admin-text)] hover:bg-[var(--admin-bg)] transition-colors">
                            Cancel
                        </button>
                        <button onclick="deleteTournament({{ $tournament->id }})"
                                class="px-3 py-1.5 rounded text-sm font-medium bg-red-500 text-white hover:brightness-110 transition-all">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
