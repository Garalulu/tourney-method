@extends('layouts.admin')

@section('title', 'Parse History - '.$tournament->title)

@php
use App\Helpers\StaffRoleHelper;
@endphp

@section('content')
<div class="p-8" x-data="{
    expandedItems: {{ $histories->pluck('id')->map(fn($id) => false)->toJson() }}
}">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center space-x-3 mb-4">
            <a href="{{ route('admin.tournaments.show', $tournament) }}"
               class="p-2 hover:bg-[var(--admin-bg)] rounded-lg transition-colors">
                <x-icon name="lucide-arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold" style="font-family: 'Outfit', sans-serif;">
                    📜 Parse History
                </h1>
                <p class="text-sm text-[var(--admin-muted)]">
                    <span class="font-tournament">{{ $tournament->title }}</span> · {{ $tournament->parse_count }} {{ $tournament->parse_count === 1 ? 'parse' : 'parses' }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4 text-sm">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <span class="text-[var(--admin-muted)]">Has Changes</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                    <span class="text-[var(--admin-muted)]">No Changes</span>
                </div>
            </div>

            @if($tournament->forum_topic_id)
            <form method="POST" action="{{ route('admin.tournaments.reparse', $tournament) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-gradient-to-r from-[var(--osu-cyan)] to-[var(--osu-pink)] text-white font-semibold text-sm hover:brightness-110 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <x-icon name="lucide-refresh-cw" class="w-4 h-4 mr-2 animate-spin-slow" />
                    Re-parse Tournament
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- History List --}}
    @if($histories->count() > 0)
        <div class="space-y-4">
            @foreach($histories as $history)
                <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden
                            hover:border-[var(--admin-border)] transition-all duration-200
                            {{ !empty($history->changes) ? 'shadow-lg shadow-blue-500/5' : '' }}">

                    {{-- Header --}}
                    <div class="p-4 cursor-pointer" @click="expandedItems[{{ $loop->index }}] = !expandedItems[{{ $loop->index }}]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                {{-- Parse Number Badge --}}
                                <div class="flex items-center justify-center w-12 h-12 rounded-xl
                                          {{ !empty($history->changes) ? 'bg-blue-500/10 border border-blue-500/30' : 'bg-gray-500/10 border border-gray-500/30' }}">
                                    <span class="text-lg font-bold {{ !empty($history->changes) ? 'text-blue-400' : 'text-gray-400' }}">
                                        #{{ $histories->firstItem() + $loop->index }}
                                    </span>
                                </div>

                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-semibold text-[var(--admin-text)]">
                                            {{ ($history->parsed_at ?? $history->created_at)->format('M j, Y · g:i A') }}
                                        </span>
                                        @if(!empty($history->changes))
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/30">
                                                {{ count($history->changes) }} {{ count($history->changes) === 1 ? 'change' : 'changes' }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-500/10 text-gray-400 border border-gray-500/30">
                                                No changes
                                            </span>
                                        @endif
                                        @if($history->compacted_at)
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                                Compacted
                                            </span>
                                        @endif
                                    </div>

                                    @if($history->parsedBy)
                                        <div class="text-xs text-[var(--admin-muted)] mt-0.5">
                                            by <span class="text-[var(--admin-text)]">{{ $history->parsedBy->username }}</span>
                                            via {{ ucfirst($history->parse_source) }}
                                        </div>
                                    @else
                                        <div class="text-xs text-[var(--admin-muted)] mt-0.5">
                                            via {{ ucfirst($history->parse_source) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                {{-- View Details Link --}}
                                <a href="{{ route('admin.tournaments.parse-history.show', [$tournament, $history]) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium
                                          bg-[var(--admin-bg)] text-[var(--admin-text)]
                                          hover:bg-[var(--admin-border)] transition-all border border-[var(--admin-border)]">
                                    <x-icon name="lucide-eye" class="w-4 h-4 mr-1.5" />
                                    View Details
                                </a>

                                {{-- Delete Button --}}
                                <div x-data="{ showDeleteConfirm: false }" class="relative">
                                    <button x-show="!showDeleteConfirm"
                                            @click="showDeleteConfirm = true"
                                            class="p-2 rounded-lg text-red-500 hover:bg-red-500/10 transition-all">
                                        <x-icon name="lucide-trash-2" class="w-4 h-4" />
                                    </button>

                                    <div x-show="showDeleteConfirm"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         @click.outside="showDeleteConfirm = false"
                                         class="absolute right-0 mt-2 w-56 bg-[var(--admin-surface)] rounded-lg border border-red-500/30 shadow-xl z-50 p-3">
                                        <p class="text-sm text-[var(--admin-text)] mb-3">
                                            Delete this parse history entry?
                                        </p>
                                        <div class="flex items-center justify-end space-x-2">
                                            <button @click="showDeleteConfirm = false"
                                                    class="px-3 py-1.5 rounded text-xs font-medium text-[var(--admin-text)] hover:bg-[var(--admin-bg)] transition-colors">
                                                Cancel
                                            </button>
                                            <button @click="deleteHistory({{ $history->id }})"
                                                    class="px-3 py-1.5 rounded text-xs font-medium bg-red-500 text-white hover:brightness-110 transition-all">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Expand Icon --}}
                                <x-icon name="lucide-chevron-down" class="w-5 h-5 text-[var(--admin-muted)] transition-transform duration-200" x-bind:class="{ 'rotate-180': expandedItems[{{ $loop->index }}] }" />
                            </div>
                        </div>

                        {{-- Quick Preview (Collapsible) --}}
                        <div x-show="expandedItems[{{ $loop->index }}]"
                             x-collapse>
                            @if(!empty($history->changes))
                                <div class="mt-4 pt-4 border-t border-[var(--admin-border)]">
                                    @isset($history->changes['staff'])
                                        {{-- Staff changes preview --}}
                                        @php
                                            $staffChanges = $history->changes['staff'];
                                            $allAdded = StaffRoleHelper::sortStaffByRole($staffChanges['added'] ?? []);
                                            $allRemoved = StaffRoleHelper::sortStaffByRole($staffChanges['removed'] ?? []);
                                            $allRoleChanged = StaffRoleHelper::sortStaffByRole($staffChanges['role_changed'] ?? []);
                                            $added = array_slice($allAdded, 0, 3);
                                            $removed = array_slice($allRemoved, 0, 2);
                                            $roleChanged = array_slice($allRoleChanged, 0, 2);
                                            $totalChanges = count($allAdded) + count($allRemoved) + count($allRoleChanged);
                                        @endphp

                                        <div class="grid grid-cols-1 gap-2">
                                            @if(count($added) > 0)
                                                @foreach($added as $staff)
                                                    @php
                                                        $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                                    @endphp
                                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                                        <div class="flex items-center space-x-2">
                                                            <span class="w-2 h-2 rounded-full {{ $roleColor['bg'] }}"></span>
                                                            <span class="text-xs text-[var(--admin-text)]">{{ $staff['username'] }}</span>
                                                        </div>
                                                        <span class="text-xs {{ $roleColor['text'] }}">{{ __('admin.parse_history.added_role', ['role' => StaffRoleHelper::getRoleLabel($staff['role'])]) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if(count($roleChanged) > 0)
                                                @foreach($roleChanged as $change)
                                                    @php
                                                        $oldRoleColor = StaffRoleHelper::getRoleColor($change['old_role']);
                                                        $newRoleColor = StaffRoleHelper::getRoleColor($change['new_role']);
                                                    @endphp
                                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                                        <span class="text-xs text-[var(--admin-text)]">{{ $change['username'] }}</span>
                                                        <div class="flex items-center space-x-2 text-xs">
                                                            <span class="{{ $oldRoleColor['text'] }} line-through">{{ StaffRoleHelper::getRoleLabel($change['old_role']) }}</span>
                                                            <span class="text-gray-500">→</span>
                                                            <span class="{{ $newRoleColor['text'] }}">{{ StaffRoleHelper::getRoleLabel($change['new_role']) }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if(count($removed) > 0)
                                                @foreach($removed as $staff)
                                                    @php
                                                        $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                                    @endphp
                                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                                        <span class="text-xs text-[var(--admin-muted)] line-through">{{ $staff['username'] }}</span>
                                                        <span class="text-xs {{ $roleColor['text'] }}">{{ __('admin.parse_history.removed_role', ['role' => StaffRoleHelper::getRoleLabel($staff['role'])]) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if($totalChanges > 5)
                                                <div class="text-center text-xs text-[var(--admin-muted)] pt-2">
                                                    And {{ $totalChanges - 5 }} more changes...
                                                    <a href="{{ route('admin.tournaments.parse-history.show', [$tournament, $history]) }}"
                                                       class="text-blue-400 hover:underline">
                                                        View all
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        {{-- Field-level changes preview --}}
                                        <div class="grid grid-cols-1 gap-2">
                                            @foreach(array_slice($history->changes, 0, 3) as $field => $change)
                                                @if(array_key_exists('old', $change) || array_key_exists('new', $change))
                                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                                        <span class="text-xs font-mono text-[var(--admin-muted)]">{{ $field }}</span>
                                                        <div class="flex items-center space-x-2 text-xs">
                                                            <span class="text-red-400 line-through max-w-[120px] truncate">
                                                                @if(isset($change['old']))
                                                                    @if(is_array($change['old']))
                                                                        {{ json_encode($change['old'], JSON_PRETTY_PRINT) }}
                                                                    @elseif(is_bool($change['old']))
                                                                        {{ $change['old'] ? 'Yes' : 'No' }}
                                                                    @else
                                                                        {{ $change['old'] }}
                                                                    @endif
                                                                @else
                                                                    Empty
                                                                @endif
                                                            </span>
                                                            <x-icon name="lucide-arrow-right" class="w-3 h-3 text-[var(--admin-muted)]" />
                                                            <span class="text-green-400 max-w-[120px] truncate">
                                                                @if(isset($change['new']))
                                                                    @if(is_array($change['new']))
                                                                        {{ json_encode($change['new'], JSON_PRETTY_PRINT) }}
                                                                    @elseif(is_bool($change['new']))
                                                                        {{ $change['new'] ? 'Yes' : 'No' }}
                                                                    @else
                                                                        {{ $change['new'] }}
                                                                    @endif
                                                                @else
                                                                    Empty
                                                                @endif
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if(count($history->changes) > 3)
                                                <div class="text-center text-xs text-[var(--admin-muted)] pt-2">
                                                    And {{ count($history->changes) - 3 }} more changes...
                                                    <a href="{{ route('admin.tournaments.parse-history.show', [$tournament, $history]) }}"
                                                       class="text-blue-400 hover:underline">
                                                        View all
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @endisset
                                </div>
                            @else
                                <div class="mt-4 pt-4 border-t border-[var(--admin-border)]">
                                    <p class="text-sm text-[var(--admin-muted)] text-center">
                                        No changes detected in this parse. All values remained the same.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($histories->hasPages())
            <div class="mt-6">
                {{ $histories->appends([])->links() }}
            </div>
        @endif
    @else
        {{-- Empty State --}}
        <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] p-12 text-center">
            <x-icon name="lucide-file-text" class="w-16 h-16 mx-auto mb-4 text-[var(--admin-muted)]" />
            <h3 class="text-lg font-semibold text-[var(--admin-text)] mb-2">No Parse History Yet</h3>
            <p class="text-[var(--admin-muted)] mb-6">
                This tournament hasn't been parsed yet. Re-parse to track changes over time.
            </p>
            @if($tournament->forum_topic_id)
            <form method="POST" action="{{ route('admin.tournaments.reparse', $tournament) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-6 py-3 rounded-lg bg-gradient-to-r from-[var(--osu-cyan)] to-[var(--osu-pink)] text-white font-semibold hover:brightness-110 transition-all shadow-lg">
                    <x-icon name="lucide-refresh-cw" class="w-5 h-5 mr-2" />
                    Parse Tournament Now
                </button>
            </form>
            @endif
        </div>
    @endif
</div>

<script>
function deleteHistory(historyId) {
    if (!confirm('Delete this parse history entry? This action cannot be undone.')) return;

    fetch(`/admin/tournaments/{{ $tournament->id }}/parse-history/${historyId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to delete history entry');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete history entry');
    });
}
</script>
@endsection
