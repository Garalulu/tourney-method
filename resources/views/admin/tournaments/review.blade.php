@extends('layouts.admin')

@section('title', 'Review Tournament')

@section('content')
<div class="flex h-full flex-col xl:flex-row" x-data="{
    showEditForm: @json($tournament->status === 'approved'),
    saveSuccess: false,
    saveError: null,
    saving: false,
    contextOpen: false
}">
    <!-- Left: Edit Form -->
    <div class="w-full flex-1 overflow-y-auto border-[var(--admin-border)] bg-[var(--admin-surface)] xl:w-1/2 xl:border-r">
        <div class="p-4 sm:p-6 xl:p-8">
            <button
                type="button"
                class="mb-4 inline-flex items-center gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] px-3 py-2 text-sm font-semibold text-[var(--admin-text)] xl:hidden"
                @click="contextOpen = true"
            >
                <x-icon name="lucide-menu" class="h-4 w-4 text-[var(--osu-cyan)]" />
                Context
            </button>

            <x-admin.tournaments.tournament-banner-card :tournament="$tournament" />

            {{-- Tournament Header --}}
            <x-admin.tournaments.tournament-header :tournament="$tournament" />

            {{-- Tournament Actions --}}
            <x-admin.tournaments.tournament-actions :tournament="$tournament" />

            <x-admin.tournaments.tournament-metadata-form :tournament="$tournament" mode="edit">
                <!-- Staff Management Section -->
                <div class="bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)] p-6">

                    <!-- Staff List (Always rendered, handles empty state) -->
                    <div class="mb-6">
                        <x-admin.tournaments.tournament-staff-management :tournament="$tournament" />
                    </div>

                    <!-- Bulk Add Staff by Role -->
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold">Bulk Add Staff</label>
                        <p class="text-xs text-[var(--admin-muted)]">Enter usernames separated by commas for each role</p>
                        <div id="staff-bulk-progress" class="hidden"></div>

                        @php
                            $commonRoles = array_keys(\App\Helpers\StaffRoleHelper::getAvailableRoles());
                        @endphp

                        @foreach($commonRoles as $role)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <span class="px-3 py-1 rounded-lg text-xs font-bold uppercase
                                    bg-[var(--admin-surface)] border border-[var(--admin-border)]
                                    text-[var(--admin-text)] sm:min-w-[100px] text-center">
                                    {{ \App\Helpers\StaffRoleHelper::getRoleLabel($role) }}
                                </span>
                                <input type="text"
                                       placeholder="{{ \App\Helpers\StaffRoleHelper::getRoleLabel($role) }}s..."
                                       id="staff-input-{{ $role }}"
                                       class="min-w-0 flex-1 px-4 py-2 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)] text-sm">
                                <button type="button"
                                        onclick="addStaffMembers('{{ $role }}')"
                                        class="px-4 py-2 rounded-lg bg-[var(--osu-cyan)] text-[var(--admin-bg)] font-medium text-sm hover:brightness-110 transition-all">
                                    Add
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-[var(--admin-muted)] mt-3">
                        💡 Staff will be automatically approved when the tournament is approved. You can add unregistered osu! users and they'll be created as placeholder accounts.
                    </p>
                </div>

                @if($tournament->isEnded())
                <!-- Podium Management Section -->
                <div class="bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                            🏆 Tournament Podium
                        </h3>
                    </div>

                    <x-admin.tournaments.tournament-podium-management :tournament="$tournament" />

                    {{-- Legacy inline podium markup removed; the shared component above is the source of truth. --}}
                    <div class="hidden">
                    @if(false && $tournament->winners()->where('placement', '<=', 3)->exists())
                        <!-- Existing Podium Display -->
                        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                            @foreach([1, 2, 3] as $placement)
                                <div class="p-4 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
                                    <div class="text-center mb-3">
                                        <span class="text-2xl">{{ $placement === 1 ? '🥇' : ($placement === 2 ? '🥈' : '🥉') }}</span>
                                        <h4 class="font-bold mt-1">{{ $placement }}{{ $placement === 1 ? 'st' : ($placement === 2 ? 'nd' : 'rd') }} Place</h4>
                                    </div>
                                    <div class="space-y-2" id="podium-placement-{{ $placement }}">
                                        @foreach($tournament->winners()->where('placement', $placement)->get() as $winner)
                                            <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                                <div class="flex items-center space-x-2">
                                                    <img src="{{ $winner->user?->avatar_url ?? 'https://a.ppy.sh/' }}" class="w-8 h-8 rounded-full">
                                                    <span class="text-sm">{{ $winner->username }}</span>
                                                </div>
                                                <button type="button"
                                                        onclick="removePodiumWinner({{ $winner->id }})"
                                                        class="text-red-500 hover:text-red-400 font-bold px-2">
                                                    &times;
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    </div>

                    <!-- Bulk Add Winners -->
                    <div id="podium-bulk-add" class="space-y-3">
                        <label class="block text-sm font-semibold">Bulk Add Winners</label>
                        <p class="text-xs text-[var(--admin-muted)]">Enter usernames separated by commas (e.g., player1,player2,player3)</p>
                        <div id="podium-bulk-progress" class="hidden"></div>

                        @foreach([1, 2, 3] as $placement)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <span class="text-xl">{{ $placement === 1 ? '🥇' : ($placement === 2 ? '🥈' : '🥉') }}</span>
                                <input type="text"
                                       placeholder="{{ $placement }}{{ $placement === 1 ? 'st' : ($placement === 2 ? 'nd' : 'rd') }} place winners..."
                                       id="podium-input-{{ $placement }}"
                                       class="min-w-0 flex-1 px-4 py-2 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg text-[var(--admin-text)] text-sm">
                                <button type="button"
                                        onclick="addPodiumWinners({{ $placement }})"
                                        class="px-4 py-2 rounded-lg bg-[var(--osu-pink)] text-white font-medium text-sm hover:brightness-110 transition-all">
                                    Add
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($tournament->isEnded())
                <!-- Badge Management Section -->
                <div
                    x-data="{ visible: @js((bool) old('is_badge', $tournament->is_badge)) }"
                    x-show="visible"
                    x-transition
                    @admin-badge-toggle.window="visible = $event.detail.checked"
                    class="bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)] p-6"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                            🏅 Badge Configuration
                        </h3>
                    </div>

                    {{-- Badge Status --}}
                    <div class="mb-6">
                        <label for="badge_status" class="block text-sm font-semibold mb-2">
                            Badge Approval Status
                        </label>
                        @php
                            $badgeStatus = old('badge_status', $tournament->badge_status ?? 'pending');
                        @endphp
                        <select name="badge_status"
                                id="badge_status"
                                :disabled="!visible"
                                class="w-full px-4 py-3 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-pink)] focus:border-transparent text-[var(--admin-text)]">
                            <option value="approved" {{ $badgeStatus === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="pending" {{ $badgeStatus === 'pending' ? 'selected' : '' }}>Pending Review</option>
                            <option value="rejected" {{ $badgeStatus === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                        @error('badge_status')
                            <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Placement-based Badge Management --}}
                    <div class="space-y-4">
                        @foreach([1, 2, 3] as $placement)
                        <div class="p-4 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <span class="text-2xl">{{ $placement === 1 ? '🥇' : ($placement === 2 ? '🥈' : '🥉') }}</span>
                                    <h4 class="font-bold">{{ $placement }}{{ $placement === 1 ? 'st' : ($placement === 2 ? 'nd' : 'rd') }} Place Badges</h4>
                                </div>
                                <span class="text-xs text-[var(--admin-muted)]">
                                    {{ count($tournament->badge_urls[$placement] ?? []) }} badge(s)
                                </span>
                            </div>

                            {{-- Badge List for this placement --}}
                            @if(!empty($tournament->badge_urls[$placement]))
                            <div class="space-y-2 mb-3" id="badge-list-{{ $placement }}">
                                @foreach($tournament->badge_urls[$placement] as $url)
                                <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded" id="badge-{{ md5($url) }}-{{ $placement }}">
                                    <div class="flex items-center space-x-2 flex-1 min-w-0">
                                        <img src="{{ $url }}" alt="Badge" class="h-12 w-auto rounded object-cover border border-[var(--admin-border)]">
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-mono text-[var(--osu-pink)] hover:underline truncate">{{ $url }}</a>
                                    </div>
                                    <button type="button"
                                            onclick="removeBadgeUrl('{{ $url }}', {{ $placement }})"
                                            class="text-red-500 hover:text-red-400 font-bold px-2">
                                        &times;
                                    </button>
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Add Badge for this placement --}}
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input type="url"
                                       id="badge_url_input_{{ $placement }}"
                                       placeholder="https://assets.ppy.sh/profile-badges/badge.png"
                                       class="min-w-0 flex-1 px-3 py-2 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded text-[var(--admin-text)] text-sm font-mono">
                                <button type="button"
                                        onclick="addBadgeUrl({{ $placement }})"
                                        class="px-4 py-2 rounded bg-[var(--osu-pink)] text-white font-medium text-sm hover:brightness-110 transition-all">
                                    + Add
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-[var(--admin-muted)] mt-4">
                        Most tournaments have 1 badge (add to 1st place only). Tri-badge tournaments have different badges for each placement (1st/2nd/3rd).
                    </p>
                </div>
                @endif

            </x-admin.tournaments.tournament-metadata-form>
            {{-- Tournament Final Actions --}}
            <x-admin.tournaments.tournament-final-actions :tournament="$tournament" />
        </div>
    </div>

    <!-- Right: Forum Post Preview -->
    <div class="hidden w-1/2 overflow-y-auto bg-[var(--admin-bg)] xl:block">
        <div class="p-8">
            <x-admin.tournaments.tournament-review-context :tournament="$tournament" />
        </div>
    </div>

    <div
        x-show="contextOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 bg-black/60 xl:hidden"
        style="display: none;"
        @keydown.escape.window="contextOpen = false"
    >
        <div class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col border-l border-[var(--admin-border)] bg-[var(--admin-bg)] shadow-2xl">
            <div class="flex items-center justify-between border-b border-[var(--admin-border)] p-4">
                <h2 class="text-lg font-bold">Tournament Context</h2>
                <button type="button" class="rounded-lg p-2 hover:bg-[var(--admin-surface)]" @click="contextOpen = false">
                    <x-icon name="lucide-x" class="h-5 w-5" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <x-admin.tournaments.tournament-review-context :tournament="$tournament" />
            </div>
        </div>
    </div>
</div>

<!-- Staff Add Modal -->
<div id="staffModal"
     data-tournament-id="{{ $tournament->id }}"
     class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-[var(--admin-surface)] rounded-xl border border-[var(--admin-border)] shadow-2xl w-full max-w-2xl mx-4 max-h-[92vh] overflow-y-auto">
        <div class="p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold" style="font-family: 'Outfit', sans-serif;">
                    Add Tournament Staff
                </h3>
                <button type="button" onclick="closeStaffModal()" class="p-2 hover:bg-[var(--admin-bg)] rounded-lg transition-colors">
                    <x-icon name="lucide-x" class="w-5 h-5" />
                </button>
            </div>

            <!-- User Search -->
            <div class="mb-4">
                <label for="userSearch" class="block text-sm font-semibold mb-2">
                    Search User (by osu! username)
                </label>
                <div class="relative">
                    <input type="text"
                           id="userSearch"
                           placeholder="{{ __('admin.review.username_placeholder') }}"
                           class="w-full px-4 py-3 pr-10 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)]"
                           oninput="searchUsers(this.value)">
                    <x-icon name="lucide-search" class="w-5 h-5 absolute right-3 top-3.5 text-[var(--admin-muted)]" />
                </div>

                <!-- Search Results Dropdown -->
                <div id="userSearchResults" class="absolute z-10 w-full mt-1 bg-[var(--admin-surface)] border border-[var(--admin-border)] rounded-lg shadow-xl max-h-60 overflow-y-auto hidden">
                    <!-- Results will be populated here -->
                </div>
            </div>

            <!-- Selected User -->
            <div id="selectedUser" class="hidden mb-4 p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                <div class="flex items-center space-x-3">
                    <img id="selectedUserAvatar" src="" alt="" class="w-10 h-10 rounded-full">
                    <div>
                        <div id="selectedUsername" class="font-semibold text-[var(--admin-text)]"></div>
                        <div id="selectedUserId" class="text-xs text-[var(--admin-muted)]"></div>
                    </div>
                </div>

                <!-- Warning for unregistered users -->
                <div id="unregisteredWarning" class="hidden mt-3 p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <x-icon name="lucide-triangle-alert" class="w-5 h-5 text-yellow-600 mt-0.5" />
                        <div class="text-sm text-yellow-700">
                            <p class="font-semibold">This user is not registered yet</p>
                            <p class="text-xs mt-1">A placeholder account will be created. They can claim it when they sign up via osu! OAuth.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role Selection (Multi-select checkboxes) -->
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-3">
                    Roles <span class="text-[var(--admin-muted)] font-normal">(select all that apply)</span>
                </label>
                
                <!-- Roles Grid -->
                <div id="rolesGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    <!-- Roles will be populated by JavaScript -->
                </div>
                
                <!-- Selected Roles Summary -->
                <div id="selectedRolesSummary" class="hidden mt-3 p-3 bg-[var(--admin-bg)]/50 border border-[var(--admin-border)] rounded-lg">
                    <p class="text-sm text-[var(--admin-muted)] mb-2">No roles selected</p>
                </div>
            </div>

            <!-- Notes (Optional) -->
            <div class="mb-6">
                <label for="staffNotes" class="block text-sm font-semibold mb-2">
                    Notes <span class="text-[var(--admin-muted)] font-normal">(optional)</span>
                </label>
                <textarea id="staffNotes"
                          rows="2"
                          placeholder="{{ __('admin.review.notes_placeholder') }}"
                          class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)] focus:border-transparent text-[var(--admin-text)] resize-none"></textarea>
            </div>

            <!-- Add Button -->
            <button type="button"
                    onclick="addStaff()"
                    id="addStaffBtn"
                    disabled
                    class="w-full px-6 py-3 rounded-lg bg-[var(--osu-cyan)] text-[var(--admin-bg)] font-semibold hover:brightness-110 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                Add Staff Member
            </button>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
@if($tournament->status === 'pending_review')
<div x-data="{
        open: false,
        error: null,
        submitting: false
    }"
     @open-reject-modal.window="open = true"
     @keydown.escape.window="open = false"
     x-cloak>
    <!-- Modal Overlay -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">

        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm"
             @click="open = false"></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-center justify-center p-4">
            <!-- Modal Content -->
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-lg bg-[var(--admin-surface)] rounded-2xl shadow-2xl border border-[var(--admin-border)] overflow-hidden"
                 @click.stop>

                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-red-500 bg-opacity-20 flex items-center justify-center">
                                <x-icon name="lucide-triangle-alert" class="w-6 h-6 text-red-500" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                                    Reject Tournament
                                </h3>
                                <p class="text-sm text-[var(--admin-muted)]">This action cannot be undone</p>
                            </div>
                        </div>
                        <button @click="open = false"
                                class="p-2 hover:bg-[var(--admin-border)] rounded-lg transition-colors">
                            <x-icon name="lucide-x" class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <form method="POST"
                      action="{{ route('admin.tournaments.reject', $tournament) }}"
                      @submit.prevent="
                          submitting = true;
                          error = null;
                          const form = $el;
                          const formData = new FormData(form);

                          fetch(form.action, {
                              method: 'POST',
                              headers: {
                                  'X-Requested-With': 'XMLHttpRequest',
                                  'Accept': 'application/json'
                              },
                              body: formData
                          })
                          .then(response => response.json())
                          .then(data => {
                              if (data.message === 'Tournament rejected') {
                                  window.location.href = '/admin/tournaments/pending';
                              } else {
                                  error = data.message || data.error || 'Failed to reject tournament';
                                  submitting = false;
                              }
                          })
                          .catch(err => {
                              error = 'Network error. Please try again.';
                              submitting = false;
                          });
                      ">
                    @csrf
                    <div class="px-6 py-6">
                        <!-- Error Alert -->
                        <div x-show="error"
                             x-transition
                             class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                            <div class="flex items-start space-x-3">
                                <x-icon name="lucide-circle-x" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-red-500">Rejection Failed</p>
                                    <p class="text-sm text-red-400 mt-1" x-text="error"></p>
                                </div>
                                <button @click="error = null" class="text-red-500 hover:text-red-400">
                                    <x-icon name="lucide-circle-x" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="block text-sm font-semibold mb-2">
                                Rejection Reason <span class="text-[var(--admin-muted)] font-normal">(optional)</span>
                            </label>
                            <p class="text-xs text-[var(--admin-muted)] mb-3">
                                Explain why this tournament is being rejected. This helps improve future submissions.
                            </p>
                            <textarea name="reason"
                                      id="reason"
                                      maxlength="1000"
                                      rows="5"
                                      placeholder="e.g., Not a legitimate tournament, insufficient information, duplicate submission..."
                                      class="w-full px-4 py-3 bg-[var(--admin-bg)] border border-[var(--admin-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none text-[var(--admin-text)] placeholder-[var(--admin-muted)] font-mono text-sm"></textarea>
                            @error('reason')
                                <p class="mt-2 text-sm text-red-500 flex items-center">
                                    <x-icon name="lucide-info" class="w-4 h-4 mr-1" />
                                    {{ $message }}
                                </p>
                            @enderror
                            <div class="mt-2 text-xs text-[var(--admin-muted)] text-right">
                                <span x-text="document.getElementById('reason')?.value.length || 0"></span> / 1000 characters
                            </div>
                        </div>

                        <!-- Tournament Info -->
                        <div class="p-4 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                            <p class="text-xs uppercase tracking-wider text-[var(--admin-muted)] mb-2">Tournament to Reject:</p>
                            <p class="font-tournament font-semibold text-[var(--admin-text)]">{{ $tournament->title }}</p>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 bg-[var(--admin-bg)] border-t border-[var(--admin-border)] flex items-center justify-end space-x-3">
                        <button type="button"
                                @click="open = false"
                                :disabled="submitting"
                                class="px-5 py-2.5 rounded-lg border border-[var(--admin-border)] text-[var(--admin-text)] font-medium hover:bg-[var(--admin-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="submitting"
                                class="px-5 py-2.5 rounded-lg bg-red-500 text-white font-semibold hover:brightness-110 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                            <x-icon name="lucide-loader-circle" x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" />
                            <span x-text="submitting ? 'Rejecting...' : 'Confirm Rejection'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- JavaScript functions for rejected tournament actions --}}
@if($tournament->status === 'rejected')
<script>
function restoreTournament(tournamentId) {
    if (!confirm('Return this tournament to pending review? You can then reject it if needed.')) {
        return;
    }

    fetch(`/admin/tournaments/${tournamentId}/restore`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text || 'Failed to restore tournament');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            // Show success and redirect
            alert('Tournament restored to pending review!');
            window.location.href = '/admin/tournaments/pending';
        } else if (data.error) {
            alert(`Error: ${data.error}`);
        }
    })
    .catch(error => {
        alert(error.message || 'Failed to restore tournament');
    });
}

function deleteTournament(tournamentId) {
    fetch(`/admin/tournaments/${tournamentId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || err.error || 'Failed to delete tournament');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            // Show success and redirect
            alert('Tournament deleted successfully!');
            window.location.href = '/admin/tournaments/pending';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Failed to delete tournament');
    });
}
</script>
@endif

{{-- Diff Viewer Styles --}}
<style>
    /* GitHub-style diff highlighting */
    .diff-unchanged {
        color: var(--admin-text);
    }

    .diff-deleted {
        background-color: rgba(248, 81, 73, 0.15);
        color: #f85149;
        text-decoration: line-through;
        padding: 0.1em 0.2em;
        border-radius: 0.2em;
        margin: 0 0.1em;
    }

    .diff-added {
        background-color: rgba(46, 160, 67, 0.15);
        color: #2ea043;
        padding: 0.1em 0.2em;
        border-radius: 0.2em;
        margin: 0 0.1em;
        font-weight: 500;
    }

    .diff-content {
        line-height: 1.6;
    }

    .diff-content .diff-unchanged {
        background-color: transparent;
    }
</style>

<script>
window.escapeOperationText = function(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

window.renderAsyncOperationProgress = function(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.classList.remove('hidden');
    const errors = data.errors || [];
    const errorHtml = errors.length > 0
        ? `<div class="mt-2 text-xs text-red-400">${errors.slice(-3).map(error => escapeOperationText(error.message || 'Operation item failed')).join('<br>')}</div>`
        : '';

    container.innerHTML = `
        <div class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] p-3">
            <div class="flex items-center justify-between text-xs text-[var(--admin-muted)] mb-2">
                <span>${escapeOperationText(data.message || 'Working...')}</span>
                <span>${data.completed}/${data.total} (${data.percent}%)</span>
            </div>
            <div class="h-2 rounded-full bg-[var(--admin-bg)] overflow-hidden">
                <div class="h-full bg-[var(--osu-cyan)] transition-all duration-300" style="width: ${data.percent}%"></div>
            </div>
            ${errorHtml}
        </div>
    `;
};

window.applyAsyncOperationFragments = function(data) {
    if (data.fragments?.staff) {
        const staffContainer = document.querySelector('[data-tournament-staff-list]');
        if (staffContainer) {
            const temp = document.createElement('div');
            temp.innerHTML = data.fragments.staff;
            const replacement = temp.querySelector('[data-tournament-staff-list]');
            if (replacement) {
                staffContainer.innerHTML = replacement.innerHTML;
            }
        }
    }

    if (data.fragments?.podium) {
        const podiumContainer = document.querySelector('[data-tournament-podium-list]');
        if (podiumContainer) {
            const temp = document.createElement('div');
            temp.innerHTML = data.fragments.podium;
            const replacement = temp.querySelector('[data-tournament-podium-list]');
            if (replacement) {
                podiumContainer.innerHTML = replacement.innerHTML;
            }
        }
    }
};

window.refreshPodiumList = function(fragment = null) {
    const podiumContainer = document.querySelector('[data-tournament-podium-list]');
    if (!podiumContainer) {
        return Promise.resolve();
    }

    const applyFragment = (html) => {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const replacement = temp.querySelector('[data-tournament-podium-list]');

        if (replacement) {
            podiumContainer.innerHTML = replacement.innerHTML;
        }
    };

    if (fragment) {
        applyFragment(fragment);
        return Promise.resolve();
    }

    return fetch(`/admin/tournaments/{{ $tournament->id }}/podium/component`, {
        headers: {
            'Accept': 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.text();
    })
    .then(applyFragment);
};

window.pollAsyncOperation = function(operationId, containerId, onComplete) {
    const poll = () => {
        fetch(`/admin/operations/${operationId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(response => response.json())
        .then(data => {
            renderAsyncOperationProgress(containerId, data);

            if (data.status === 'completed' || data.status === 'failed') {
                applyAsyncOperationFragments(data);
                if (typeof onComplete === 'function') {
                    onComplete(data);
                }
                return;
            }

            setTimeout(poll, 1500);
        })
        .catch(error => {
            console.error('Operation polling failed:', error);
            setTimeout(poll, 3000);
        });
    };

    poll();
};

document.querySelector('[data-reparse-form]')?.addEventListener('submit', function(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const button = form.querySelector('[data-reparse-button]');
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Queued...';

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Failed to queue re-parse');
            return;
        }

        pollAsyncOperation(data.operation_id, 'reparse-progress', () => {
            button.disabled = false;
            button.textContent = originalText;
        });
    })
    .catch(error => {
        alert('Error queueing re-parse: ' + error.message);
        button.disabled = false;
        button.textContent = originalText;
    });
});

// Refresh tournament banner cache
window.refreshBanner = function(tournamentId, button) {
    button = button || (typeof event !== 'undefined' ? event.target?.closest('button') : null);
    if (!button) return;

    const originalText = button.textContent;

    button.disabled = true;
    button.textContent = 'Refreshing...';

    fetch(`/admin/tournaments/${tournamentId}/refresh-banner`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));

        if (response.status === 419) {
            throw new Error('Your session token expired. Refresh the page and try again.');
        }

        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status} error`);
        }

        return data;
    })
    .then(data => {
        if (data.success) {
            // Reload the page to show the updated cache timestamp
            window.location.reload();
        } else {
            button.disabled = false;
            button.textContent = originalText;
            alert(data.message || 'Failed to refresh banner');
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = originalText;
        alert(error.message || 'Error refreshing banner');
    });
};

// Add podium winners for a placement
window.addPodiumWinners = function(placement) {
    const input = document.getElementById(`podium-input-${placement}`);
    const usernames = input.value.trim();

    if (!usernames) {
        alert('Please enter at least one username');
        return;
    }

    const tournamentId = {{ $tournament->id }};
    const addButton = event.target;
    const originalText = addButton.textContent;
    addButton.disabled = true;
    addButton.textContent = 'Adding...';

    fetch(`/admin/tournaments/${tournamentId}/podium`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            placement: placement,
            usernames: usernames,
        }),
    })
    .then(async response => {
        const data = await response.json();

        if (data.success) {
            // Clear input field only (preserve all other form data)
            input.value = '';
            pollAsyncOperation(data.operation_id, 'podium-bulk-progress', (operation) => {
                if (operation.failed > 0) {
                    alert('Podium add finished with some errors. Check the progress panel for details.');
                } else if (typeof showSuccessMessage === 'function') {
                    showSuccessMessage('Podium add complete');
                }
            });
            return;

            // Ensure podium display structure exists
            let podiumSection = document.querySelector('.grid.grid-cols-3.gap-4.mb-6');

            // Create podium display structure if it doesn't exist (first winner for this tournament)
            if (!podiumSection) {
                const bulkAddSection = document.getElementById('podium-bulk-add');
                if (!bulkAddSection) {
                    console.error('Podium bulk-add section not found');
                    return;
                }

                podiumSection = document.createElement('div');
                podiumSection.className = 'grid grid-cols-3 gap-4 mb-6';

                for (let p = 1; p <= 3; p++) {
                    const placementDiv = document.createElement('div');
                    placementDiv.className = 'p-4 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)]';

                    const medal = p === 1 ? '\u{1F947}' : (p === 2 ? '\u{1F948}' : '\u{1F949}');
                    const ordinal = p === 1 ? 'st' : (p === 2 ? 'nd' : 'rd');

                    placementDiv.innerHTML = `
                        <div class="text-center mb-3">
                            <span class="text-2xl">${medal}</span>
                            <h4 class="font-bold mt-1">${p}${ordinal} Place</h4>
                        </div>
                        <div class="space-y-2" id="podium-placement-${p}"></div>
                    `;

                    podiumSection.appendChild(placementDiv);
                }

                // Insert podium display before the bulk-add section
                bulkAddSection.parentNode.insertBefore(podiumSection, bulkAddSection);
            }

            // Insert new winners into DOM
            const placementContainer = document.getElementById(`podium-placement-${placement}`);
            if (placementContainer && data.winners && data.winners.length > 0) {
                data.winners.forEach(winner => {
                    const winnerDiv = document.createElement('div');
                    winnerDiv.className = 'flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded';
                    winnerDiv.innerHTML = `
                        <div class="flex items-center space-x-2">
                            <img src="${winner.user?.avatar_url || (winner.osu_id ? 'https://a.ppy.sh/' + winner.osu_id : 'https://a.ppy.sh/')}" class="w-8 h-8 rounded-full">
                            <span class="text-sm">${winner.username}</span>
                        </div>
                        <button type="button"
                                onclick="removePodiumWinner(${winner.id})"
                                class="text-red-500 hover:text-red-400 font-bold px-2">
                            ×
                        </button>
                    `;
                    placementContainer.appendChild(winnerDiv);
                });
            }

            // Show success message if there were some errors
            if (data.errors && data.errors.length > 0) {
                alert('Some winners added successfully:\n\n' + data.errors.join('\n'));
            }
        } else {
            // Show errors
            const errorMsg = data.errors ? data.errors.join('\n') : (data.error || 'Failed to add winners');
            alert(errorMsg);
        }
    })
    .catch(error => {
        alert('Error adding winners: ' + error.message);
    })
    .finally(() => {
        addButton.disabled = false;
        addButton.textContent = originalText;
    });
};

// Remove a podium winner
window.removePodiumWinner = function(winnerId) {
    if (!confirm('Remove this winner from the podium?')) {
        return;
    }

    const tournamentId = {{ $tournament->id }};

    fetch(`/admin/tournaments/${tournamentId}/podium/${winnerId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
        } else {
            alert('Failed to remove winner');
        }
    })
    .catch(error => {
        alert('Error removing winner: ' + error.message);
    });
};

window.groupSelectedPodiumWinners = function(placement) {
    const selected = Array.from(document.querySelectorAll(`input[data-podium-placement="${placement}"]:checked`))
        .map((input) => Number(input.value))
        .filter((value) => value > 0);

    if (selected.length < 1) {
        alert('Select at least one podium member to group.');
        return;
    }

    const tournamentId = {{ $tournament->id }};
    const selectedSource = document.querySelector(`input[name="podium-source-${placement}"]:checked`);
    const sourceWinnerId = selected.includes(Number(selectedSource?.value))
        ? Number(selectedSource.value)
        : selected[0];

    fetch(`/admin/tournaments/${tournamentId}/podium/groups`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            winner_ids: selected,
            source_winner_id: sourceWinnerId,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
        } else {
            alert(data.error || 'Failed to group podium members');
        }
    })
    .catch(error => {
        alert('Error grouping podium members: ' + error.message);
    });
};

window.savePodiumGroupDetails = function(button) {
    const card = button?.closest('[data-podium-group-card]');
    const groupKey = card?.dataset.podiumGroupKey;
    const tournamentId = {{ $tournament->id }};
    const teamNameInput = document.querySelector(`[data-podium-team-name="${groupKey}"]`);
    const numericWinnerIds = JSON.parse(card?.dataset.podiumWinnerIds || '[]')
        .map((value) => Number(value))
        .filter((value) => value > 0);

    if (numericWinnerIds.length === 0) {
        alert('No podium members found for this group.');
        return;
    }

    const originalText = button.textContent;
    if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
    }

    fetch(`/admin/tournaments/${tournamentId}/podium/groups`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            winner_ids: numericWinnerIds,
            team_name: teamNameInput ? teamNameInput.value : null,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
            if (typeof showSuccessMessage === 'function') {
                showSuccessMessage('Podium team saved');
            }
        } else {
            alert(data.error || 'Failed to save podium group');
        }
    })
    .catch(error => {
        alert('Error saving podium group: ' + error.message);
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
};

window.splitPodiumWinner = function(winnerId) {
    const tournamentId = {{ $tournament->id }};

    fetch(`/admin/tournaments/${tournamentId}/podium/${winnerId}/group`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
        } else {
            alert(data.error || 'Failed to split podium member');
        }
    })
    .catch(error => {
        alert('Error splitting podium member: ' + error.message);
    });
};

window.startPodiumDrag = function(event) {
    const row = event.currentTarget;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', JSON.stringify({
        winnerId: Number(row.dataset.podiumWinnerId),
        placement: Number(row.dataset.podiumPlacement),
    }));
    row.classList.add('opacity-50');
};

window.endPodiumDrag = function(event) {
    event.currentTarget.classList.remove('opacity-50');
    document.querySelectorAll('[data-podium-group-card]').forEach((card) => {
        card.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');
    });
};

window.podiumDragOver = function(event) {
    const card = event.currentTarget;
    const dragData = podiumDragData(event);

    if (!dragData || Number(card.dataset.podiumPlacement) !== dragData.placement) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    card.classList.add('ring-2', 'ring-[var(--osu-cyan)]');
};

window.podiumDragLeave = function(event) {
    event.currentTarget.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');
};

window.dropPodiumWinner = function(event) {
    const card = event.currentTarget;
    const dragData = podiumDragData(event);

    card.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');

    if (!dragData || Number(card.dataset.podiumPlacement) !== dragData.placement) {
        return;
    }

    event.preventDefault();

    const targetWinnerIds = JSON.parse(card.dataset.podiumWinnerIds || '[]')
        .map((value) => Number(value))
        .filter((value) => value > 0);

    if (targetWinnerIds.includes(dragData.winnerId)) {
        return;
    }

    const winnerIds = [...new Set([...targetWinnerIds, dragData.winnerId])];
    const teamNameInput = card.querySelector('[data-podium-team-name]');
    const tournamentId = {{ $tournament->id }};

    fetch(`/admin/tournaments/${tournamentId}/podium/groups`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            winner_ids: winnerIds,
            source_winner_id: targetWinnerIds[0] || dragData.winnerId,
            team_name: teamNameInput ? teamNameInput.value : null,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
        } else {
            alert(data.error || 'Failed to move podium member');
        }
    })
    .catch(error => {
        alert('Error moving podium member: ' + error.message);
    });
};

window.togglePodiumSelection = function(button) {
    const card = button.closest('[data-podium-group-card]');
    if (!card) {
        return;
    }

    const enteringSelection = !card.dataset.podiumSelecting;
    card.dataset.podiumSelecting = enteringSelection ? 'true' : '';
    button.textContent = enteringSelection ? 'Cancel' : 'Select';

    card.querySelectorAll('[data-podium-member-checkbox]').forEach((checkbox) => {
        checkbox.classList.toggle('hidden', !enteringSelection);
        checkbox.checked = false;
    });

    card.querySelectorAll('[data-podium-selection-action]').forEach((action) => {
        action.classList.toggle('hidden', !enteringSelection);
    });
};

window.moveSelectedPodiumWinnersToNewGroup = function(button) {
    const card = button.closest('[data-podium-group-card]');
    if (!card) {
        return;
    }

    const selectedWinnerIds = Array.from(card.querySelectorAll('[data-podium-member-checkbox]:checked'))
        .map((checkbox) => Number(checkbox.value))
        .filter((value) => value > 0);

    if (selectedWinnerIds.length === 0) {
        alert('Select at least one podium member to move.');
        return;
    }

    createNewPodiumGroup(selectedWinnerIds);
};

window.podiumNewGroupDragOver = function(event) {
    const target = event.currentTarget;
    const dragData = podiumDragData(event);

    if (!dragData || Number(target.dataset.podiumPlacement) !== dragData.placement) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    target.classList.add('ring-2', 'ring-[var(--osu-cyan)]');
};

window.podiumNewGroupDragLeave = function(event) {
    event.currentTarget.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');
};

window.dropPodiumWinnerToNewGroup = function(event) {
    const target = event.currentTarget;
    const dragData = podiumDragData(event);

    target.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');

    if (!dragData || Number(target.dataset.podiumPlacement) !== dragData.placement) {
        return;
    }

    event.preventDefault();
    createNewPodiumGroup([dragData.winnerId]);
};

function createNewPodiumGroup(winnerIds) {
    const tournamentId = {{ $tournament->id }};

    fetch(`/admin/tournaments/${tournamentId}/podium/groups/new`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            winner_ids: winnerIds,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshPodiumList(data.fragment);
        } else {
            alert(data.error || 'Failed to create podium team');
        }
    })
    .catch(error => {
        alert('Error creating podium team: ' + error.message);
    });
}

function podiumDragData(event) {
    try {
        const payload = JSON.parse(event.dataTransfer.getData('text/plain') || '{}');
        const winnerId = Number(payload.winnerId);
        const placement = Number(payload.placement);

        if (!winnerId || !placement) {
            return null;
        }

        return { winnerId, placement };
    } catch (error) {
        return null;
    }
}

// Add badge URL to tournament (placement-based)
window.addBadgeUrl = function(placement) {
    const input = document.getElementById(`badge_url_input_${placement}`);
    const url = input.value.trim();

    if (!url) {
        alert('Please enter a badge URL');
        return;
    }

    // Basic URL format validation
    try {
        new URL(url);
    } catch (e) {
        alert('Please enter a valid URL (e.g., https://assets.ppy.sh/profile-badges/badge.png)');
        return;
    }

    const tournamentId = {{ $tournament->id }};
    const addButton = event.target;
    const originalText = addButton.textContent;
    addButton.disabled = true;
    addButton.textContent = 'Adding...';

    fetch(`/admin/tournaments/${tournamentId}/badges`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: url, placement: placement }),
    })
    .then(async response => {
        const data = await response.json();

        if (data.success) {
            // Clear input field
            input.value = '';

            // Add badge to DOM (no page reload)
            let badgeList = document.getElementById(`badge-list-${placement}`);

            // Create badge container if it doesn't exist
            if (!badgeList) {
                const inputContainer = input.parentElement;
                const newList = document.createElement('div');
                newList.id = `badge-list-${placement}`;
                newList.className = 'space-y-2 mb-3';
                inputContainer.parentElement.insertBefore(newList, inputContainer);
                badgeList = newList;
            }

            // Create badge element
            const badgeElement = document.createElement('div');
            badgeElement.className = 'flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded';
            badgeElement.id = `badge-${btoa(url).replace(/[^a-zA-Z0-9]/g, '')}-${placement}`;
            badgeElement.innerHTML = `
                <div class="flex items-center space-x-2 flex-1 min-w-0">
                    <img src="${url}" alt="Badge" class="h-12 w-auto rounded object-cover border border-[var(--admin-border)]">
                    <a href="${url}" target="_blank" rel="noopener noreferrer" class="text-sm font-mono text-[var(--osu-pink)] hover:underline truncate">${url}</a>
                </div>
                <button type="button"
                        onclick="removeBadgeUrl('${url}', ${placement})"
                        class="text-red-500 hover:text-red-400 font-bold px-2">
                    ×
                </button>
            `;

            // Add to list
            badgeList.appendChild(badgeElement);

            // Update badge count
            const countSpan = input.closest('.p-4').querySelector('.text-xs');
            const currentCount = parseInt(countSpan.textContent) || 0;
            countSpan.textContent = `${currentCount + 1} badge(s)`;
        } else {
            alert(data.error || 'Failed to add badge URL');
        }
    })
    .catch(error => {
        alert('Error adding badge URL: ' + error.message);
    })
    .finally(() => {
        addButton.disabled = false;
        addButton.textContent = originalText;
    });
};

// Remove badge URL from tournament (placement-based)
window.removeBadgeUrl = function(url, placement) {
    if (!confirm('Remove this badge URL?')) {
        return;
    }

    const tournamentId = {{ $tournament->id }};

    fetch(`/admin/tournaments/${tournamentId}/badges`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: url, placement: placement }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove badge from DOM (no page reload)
            const badgeId = `badge-${btoa(url).replace(/[^a-zA-Z0-9]/g, '')}-${placement}`;
            const badgeElement = document.getElementById(badgeId);

            if (badgeElement) {
                badgeElement.remove();

                // Update badge count
                const input = document.getElementById(`badge_url_input_${placement}`);
                const countSpan = input.closest('.p-4').querySelector('.text-xs');
                const currentCount = parseInt(countSpan.textContent) || 0;
                countSpan.textContent = `${Math.max(0, currentCount - 1)} badge(s)`;

                // Remove the badge list container if empty
                const badgeList = document.getElementById(`badge-list-${placement}`);
                if (badgeList && badgeList.children.length === 0) {
                    badgeList.remove();
                }
            }
        } else {
            alert(data.error || 'Failed to remove badge URL');
        }
    })
    .catch(error => {
        alert('Error removing badge URL: ' + error.message);
    });
};

// Add staff members for a role
window.addStaffMembers = function(role) {
    const input = document.getElementById(`staff-input-${role}`);
    const usernames = input.value.trim();

    if (!usernames) {
        alert('Please enter at least one username');
        return;
    }

    const tournamentId = {{ $tournament->id }};
    const addButton = event.target;
    const originalText = addButton.textContent;
    addButton.disabled = true;
    addButton.textContent = 'Adding...';

    fetch(`/admin/tournaments/${tournamentId}/staff`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            usernames: usernames,
            role: role,
        }),
    })
    .then(async response => {
        const data = await response.json();

        if (data.success) {
            // Clear input field only (preserve all other form data)
            input.value = '';
            pollAsyncOperation(data.operation_id, 'staff-bulk-progress', (operation) => {
                if (operation.failed > 0) {
                    alert('Staff add finished with some errors. Check the progress panel for details.');
                } else if (typeof showSuccessMessage === 'function') {
                    showSuccessMessage('Staff add complete');
                }
            });
            return;

            // Refresh staff list using the existing function from staff-management.js
            if (typeof refreshStaffList === 'function') {
                refreshStaffList();
            } else {
                // Fallback if refreshStaffList is not available
                console.warn('refreshStaffList function not found, reloading page');
                window.location.reload();
                return;
            }

            // Show success message if there were some errors
            if (data.errors && data.errors.length > 0) {
                alert('Some staff added successfully:\n\n' + data.errors.join('\n'));
            } else if (typeof showSuccessMessage === 'function') {
                showSuccessMessage('Staff added successfully');
            }
        } else if (data.errors && data.errors.length > 0) {
            alert(data.errors.join('\n'));
        } else {
            alert(data.error || 'Failed to add staff members');
        }
    })
    .catch(error => {
        alert('Error adding staff: ' + error.message);
    })
    .finally(() => {
        addButton.disabled = false;
        addButton.textContent = originalText;
    });
};

</script>
@vite('resources/js/admin/tournaments/staff-management.js')

@endsection
