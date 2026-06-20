@props([
    'tournament' => null,
])

@once
<script>
    function approveTournament(tournamentId, component) {
        component.submitting = true;
        component.error = null;

        const body = {
            skip_discord_webhook: component.skipDiscordWebhook || false
        };

        fetch(`/admin/tournaments/${tournamentId}/approve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        })
        .then(response => response.json())
        .then(data => {
            if (data.message === 'Tournament approved successfully') {
                window.location.href = '/admin/tournaments/pending';
            } else {
                component.error = data.message || data.error || 'Failed to approve tournament';
                component.submitting = false;
            }
        })
        .catch(err => {
            component.error = 'Network error. Please try again.';
            component.submitting = false;
        });
    }

    function restoreTournament(tournamentId, component) {
        component.restoreSubmitting = true;
        component.restoreError = null;

        fetch(`/admin/tournaments/${tournamentId}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.message === 'Tournament restored to pending review') {
                window.location.reload();
            } else {
                component.restoreError = data.message || data.error || 'Failed to restore tournament';
                component.restoreSubmitting = false;
            }
        })
        .catch(err => {
            component.restoreError = 'Network error. Please try again.';
            component.restoreSubmitting = false;
        });
    }

    function copyTournament(tournamentId, component) {
        component.copySubmitting = true;
        component.copyError = null;

        fetch(`/admin/tournaments/${tournamentId}/copy`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                title: component.copyTitle
            })
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const titleError = data.errors?.title?.[0];
                throw new Error(titleError || data.message || data.error || 'Failed to copy tournament');
            }

            return data;
        })
        .then(data => {
            window.location.href = data.url;
        })
        .catch(err => {
            component.copyError = err.message || 'Network error. Please try again.';
            component.copySubmitting = false;
        });
    }
</script>
@endonce

<!-- Action Buttons -->
<div class="mt-8 pt-8 border-t border-[var(--admin-border)] space-y-4">
    <h3 class="text-sm font-semibold uppercase tracking-wider text-[var(--admin-muted)] mb-4">Final Actions</h3>

    {{-- Pending Tournaments: Show both Approve and Reject buttons --}}
    @if($tournament->status === 'pending_review')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Approve Button --}}
            <div x-data="{ open: false, submitting: false, error: null, skipDiscordWebhook: false }"
                 @open-approve-modal.window="open = true"
                 @keydown.escape.window="open = false"
                 x-cloak>

                {{-- Approve Modal --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 z-50 overflow-y-auto"
                     style="display: none;">

                    {{-- Backdrop --}}
                    <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm"
                         @click="open = false"></div>

                    {{-- Modal Container --}}
                    <div class="flex min-h-full items-center justify-center p-4">
                        {{-- Modal Content --}}
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="relative w-full max-w-lg bg-[var(--admin-surface)] rounded-2xl shadow-2xl border border-[var(--admin-border)] overflow-hidden"
                             @click.stop>

                            {{-- Modal Header --}}
                            <div class="px-6 py-5 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                                            <x-icon name="lucide-circle-check" class="w-6 h-6 text-green-500" />
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                                                Approve Tournament
                                            </h3>
                                            <p class="text-sm text-[var(--admin-muted)]">This will make it visible to all players</p>
                                        </div>
                                    </div>
                                    <button @click="open = false"
                                            class="p-2 hover:bg-[var(--admin-border)] rounded-lg transition-colors">
                                        <x-icon name="lucide-x" class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>

                            {{-- Modal Body --}}
                            <form @submit.prevent="approveTournament({{ $tournament->id }}, $data)">
                                @csrf
                                <div class="px-6 py-6">
                                    {{-- Error Alert --}}
                                    <div x-show="error"
                                         x-transition
                                         class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                                        <div class="flex items-start space-x-3">
                                            <x-icon name="lucide-circle-x" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-red-500">Approval Failed</p>
                                                <p class="text-sm text-red-400 mt-1" x-text="error"></p>
                                            </div>
                                            <button @click="error = null" class="text-red-500 hover:text-red-400">
                                                <x-icon name="lucide-circle-x" class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <p class="text-sm text-[var(--admin-text)]">
                                            Are you sure you want to approve this tournament?
                                        </p>

                                        <ul class="text-sm text-[var(--admin-muted)] space-y-2 mt-4">
                                            <li class="flex items-start space-x-2">
                                                <x-icon name="lucide-circle-check" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                                <span>Tournament will become visible to all players</span>
                                            </li>
                                            <li class="flex items-start space-x-2">
                                                <x-icon name="lucide-circle-check" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                                <span>Staff members will be approved automatically</span>
                                            </li>
                                            <li class="flex items-start space-x-2">
                                                <x-icon name="lucide-circle-check" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                                <span>Announcement will be posted to Discord</span>
                                            </li>
                                        </ul>

                                        {{-- Skip Discord Webhook Checkbox --}}
                                        <div class="mt-4 p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                                            <label class="flex items-start space-x-3 cursor-pointer">
                                                <input type="checkbox"
                                                       x-model="skipDiscordWebhook"
                                                       class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-blue-400">Skip Discord webhook notification</p>
                                                    <p class="text-xs text-blue-400/70 mt-1">Don't send announcement to central Discord server</p>
                                                </div>
                                            </label>
                                        </div>

                                        {{-- Tournament Info --}}
                                        <div class="mt-4 p-4 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                                            <p class="text-xs uppercase tracking-wider text-[var(--admin-muted)] mb-2">Tournament to Approve:</p>
                                            <p class="font-tournament font-semibold text-[var(--admin-text)]">{{ $tournament->title }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Modal Footer --}}
                                <div class="px-6 py-4 bg-[var(--admin-bg)] border-t border-[var(--admin-border)] flex items-center justify-end space-x-3">
                                    <button type="button"
                                            @click="open = false"
                                            :disabled="submitting"
                                            class="px-5 py-2.5 rounded-lg border border-[var(--admin-border)] text-[var(--admin-text)] font-medium hover:bg-[var(--admin-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            :disabled="submitting"
                                            class="px-5 py-2.5 rounded-lg bg-green-500 text-white font-semibold hover:brightness-110 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                        <x-icon name="lucide-loader-circle" x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" />
                                        <span x-text="submitting ? 'Approving...' : 'Confirm Approval'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Trigger Button --}}
                <button @click="open = true"
                        class="w-full px-6 py-3 rounded-lg bg-[var(--success)] text-white font-semibold hover:brightness-110 transition-all shadow-lg flex items-center justify-center space-x-2">
                    <x-icon name="lucide-circle-check" class="w-5 h-5" />
                    <span>Approve Tournament</span>
                </button>
            </div>

            {{-- Reject Button --}}
            <button @click="$dispatch('open-reject-modal', { tournamentId: {{ $tournament->id }} })"
                    class="w-full px-6 py-3 rounded-lg bg-red-500 text-white font-semibold hover:brightness-110 transition-all shadow-lg flex items-center justify-center space-x-2">
                <x-icon name="lucide-circle-x" class="w-5 h-5" />
                <span>Reject Tournament</span>
            </button>
        </div>
    @endif

    {{-- Approved Tournaments: Show "Return to Pending" button --}}
    @if($tournament->status === 'approved')
        <div x-data="{ openRestoreModal: false, restoreSubmitting: false, restoreError: null, openCopyModal: false, copySubmitting: false, copyError: null, copyTitle: @js($tournament->title) }"
             @keydown.escape.window="openRestoreModal = false; openCopyModal = false"
             x-cloak>

            @if($tournament->isEnded())
            {{-- Copy Modal --}}
            <div x-show="openCopyModal"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 style="display: none;">

                <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm"
                     @click="openCopyModal = false"></div>

                <div class="flex min-h-full items-center justify-center p-4">
                    <div x-show="openCopyModal"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative w-full max-w-lg bg-[var(--admin-surface)] rounded-2xl shadow-2xl border border-[var(--admin-border)] overflow-hidden"
                         @click.stop>

                        <div class="px-6 py-5 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-[var(--osu-cyan)] bg-opacity-20 flex items-center justify-center">
                                        <x-icon name="lucide-copy" class="w-6 h-6 text-[var(--osu-cyan)]" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                                            Copy Tournament
                                        </h3>
                                        <p class="text-sm text-[var(--admin-muted)]">Create an approved copy without podium winners</p>
                                    </div>
                                </div>
                                <button @click="openCopyModal = false"
                                        class="p-2 hover:bg-[var(--admin-border)] rounded-lg transition-colors">
                                    <x-icon name="lucide-x" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="copyTournament({{ $tournament->id }}, $data)">
                            @csrf
                            <div class="px-6 py-6">
                                <div x-show="copyError"
                                     x-transition
                                     class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                                    <div class="flex items-start space-x-3">
                                        <x-icon name="lucide-circle-x" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-red-500">Copy Failed</p>
                                            <p class="text-sm text-red-400 mt-1" x-text="copyError"></p>
                                        </div>
                                    </div>
                                </div>

                                <label for="copy_tournament_title" class="block text-sm font-semibold mb-2">
                                    New tournament title
                                </label>
                                <input type="text"
                                       id="copy_tournament_title"
                                       x-model="copyTitle"
                                       required
                                       maxlength="256"
                                       class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]">

                                <div class="mt-4 p-4 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                                    <p class="text-xs uppercase tracking-wider text-[var(--admin-muted)] mb-2">Source Tournament:</p>
                                    <p class="font-tournament font-semibold text-[var(--admin-text)]">{{ $tournament->title }}</p>
                                </div>
                            </div>

                            <div class="px-6 py-4 bg-[var(--admin-bg)] border-t border-[var(--admin-border)] flex items-center justify-end space-x-3">
                                <button type="button"
                                        @click="openCopyModal = false"
                                        :disabled="copySubmitting"
                                        class="px-5 py-2.5 rounded-lg border border-[var(--admin-border)] text-[var(--admin-text)] font-medium hover:bg-[var(--admin-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    Cancel
                                </button>
                                <button type="submit"
                                        :disabled="copySubmitting"
                                        class="px-5 py-2.5 rounded-lg bg-[var(--osu-cyan)] text-[var(--admin-bg)] font-semibold hover:brightness-110 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                    <x-icon name="lucide-loader-circle" x-show="copySubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-[var(--admin-bg)]" />
                                    <span x-text="copySubmitting ? 'Copying...' : 'Create Copy'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- Restore Modal --}}
            <div x-show="openRestoreModal"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 style="display: none;">

                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm"
                     @click="openRestoreModal = false"></div>

                {{-- Modal Container --}}
                <div class="flex min-h-full items-center justify-center p-4">
                    {{-- Modal Content --}}
                    <div x-show="openRestoreModal"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative w-full max-w-lg bg-[var(--admin-surface)] rounded-2xl shadow-2xl border border-[var(--admin-border)] overflow-hidden"
                         @click.stop>

                        {{-- Modal Header --}}
                        <div class="px-6 py-5 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-yellow-500 bg-opacity-20 flex items-center justify-center">
                                        <x-icon name="lucide-undo-2" class="w-6 h-6 text-yellow-500" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                                            Return to Pending Review
                                        </h3>
                                        <p class="text-sm text-[var(--admin-muted)]">This will require re-approval before going live</p>
                                    </div>
                                </div>
                                <button @click="openRestoreModal = false"
                                        class="p-2 hover:bg-[var(--admin-border)] rounded-lg transition-colors">
                                    <x-icon name="lucide-x" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {{-- Modal Body --}}
                        <form @submit.prevent="restoreTournament({{ $tournament->id }}, $data)">
                            @csrf
                            <div class="px-6 py-6">
                                {{-- Error Alert --}}
                                <div x-show="restoreError"
                                     x-transition
                                     class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                                    <div class="flex items-start space-x-3">
                                        <x-icon name="lucide-circle-x" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-red-500">Restore Failed</p>
                                            <p class="text-sm text-red-400 mt-1" x-text="restoreError"></p>
                                        </div>
                                        <button @click="restoreError = null" class="text-red-500 hover:text-red-400">
                                            <x-icon name="lucide-circle-x" class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <p class="text-sm text-[var(--admin-text)]">
                                        Are you sure you want to return this tournament to pending review?
                                    </p>

                                    <ul class="text-sm text-[var(--admin-muted)] space-y-2 mt-4">
                                        <li class="flex items-start space-x-2">
                                            <x-icon name="lucide-circle-check" class="w-4 h-4 text-yellow-500 flex-shrink-0 mt-0.5" />
                                            <span>Tournament will need to be re-approved before going live</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <x-icon name="lucide-circle-check" class="w-4 h-4 text-yellow-500 flex-shrink-0 mt-0.5" />
                                            <span>You can then make edits or reject the tournament</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <x-icon name="lucide-circle-check" class="w-4 h-4 text-yellow-500 flex-shrink-0 mt-0.5" />
                                            <span>Previous approval history will be preserved</span>
                                        </li>
                                    </ul>

                                    {{-- Tournament Info --}}
                                    <div class="mt-4 p-4 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                                        <p class="text-xs uppercase tracking-wider text-[var(--admin-muted)] mb-2">Tournament to Restore:</p>
                                        <p class="font-tournament font-semibold text-[var(--admin-text)]">{{ $tournament->title }}</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal Footer --}}
                            <div class="px-6 py-4 bg-[var(--admin-bg)] border-t border-[var(--admin-border)] flex items-center justify-end space-x-3">
                                <button type="button"
                                        @click="openRestoreModal = false"
                                        :disabled="restoreSubmitting"
                                        class="px-5 py-2.5 rounded-lg border border-[var(--admin-border)] text-[var(--admin-text)] font-medium hover:bg-[var(--admin-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    Cancel
                                </button>
                                <button type="submit"
                                        :disabled="restoreSubmitting"
                                        class="px-5 py-2.5 rounded-lg bg-yellow-500 text-white font-semibold hover:brightness-110 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                    <x-icon name="lucide-loader-circle" x-show="restoreSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" />
                                    <span x-text="restoreSubmitting ? 'Restoring...' : 'Confirm Restore'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Trigger Button --}}
            <div class="grid grid-cols-1 gap-4 @if($tournament->isEnded()) sm:grid-cols-2 @endif">
                @if($tournament->isEnded())
                <button @click="copyTitle = @js($tournament->title); openCopyModal = true"
                        class="w-full px-6 py-3 rounded-lg bg-[var(--osu-cyan)] text-[var(--admin-bg)] font-semibold hover:brightness-110 transition-all shadow-lg flex items-center justify-center space-x-2">
                    <x-icon name="lucide-copy" class="w-5 h-5" />
                    <span>Copy Tournament</span>
                </button>
                @endif

                <button @click="openRestoreModal = true"
                        class="w-full px-6 py-3 rounded-lg bg-yellow-500 text-white font-semibold hover:brightness-110 transition-all shadow-lg flex items-center justify-center space-x-2">
                    <x-icon name="lucide-undo-2" class="w-5 h-5" />
                    <span>Return to Pending Review</span>
                </button>
            </div>
        </div>
    @endif
</div>
