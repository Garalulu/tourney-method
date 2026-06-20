@extends('layouts.admin')

@section('title', 'Parse History #'.$history->id.' - '.$tournament->title)

@php
use App\Helpers\StaffRoleHelper;
@endphp

@section('content')
<div class="p-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center space-x-3 mb-4">
            <a href="{{ route('admin.tournaments.parse-history', $tournament) }}"
               class="p-2 hover:bg-[var(--admin-bg)] rounded-lg transition-colors">
                <x-icon name="lucide-arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold" style="font-family: 'Outfit', sans-serif;">
                    Parse History Details
                </h1>
                <p class="text-sm text-[var(--admin-muted)]">
                    <span class="font-tournament">{{ $tournament->title }}</span> · Entry #{{ $history->id }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                @if(!empty($history->changes))
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/30">
                        <x-icon name="lucide-circle-check" class="w-4 h-4 mr-2" />
                        {{ count($history->changes) }} {{ count($history->changes) === 1 ? 'Change' : 'Changes' }}
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-semibold bg-gray-500/10 text-gray-400 border border-gray-500/30">
                        <x-icon name="lucide-circle-check" class="w-4 h-4 mr-2" />
                        No Changes
                    </span>
                @endif

                <span class="text-sm text-[var(--admin-muted)]">
                    {{ ($history->parsed_at ?? $history->created_at)->format('F j, Y \a\t g:i A') }}
                </span>

                @if($history->parsedBy)
                    <span class="text-sm text-[var(--admin-muted)]">
                        by <span class="text-[var(--admin-text)]">{{ $history->parsedBy->username }}</span>
                    </span>
                @endif

                <span class="px-2 py-1 rounded text-xs font-medium bg-[var(--admin-bg)] text-[var(--admin-muted)] border border-[var(--admin-border)]">
                    {{ ucfirst($history->parse_source) }}
                </span>

                @if($history->compacted_at)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30">
                        Compacted {{ $history->compacted_at->format('M j, Y') }}
                    </span>
                @endif
            </div>

            <div x-data="{ showDeleteConfirm: false }" class="relative">
                <button x-show="!showDeleteConfirm"
                        @click="showDeleteConfirm = true"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-red-500/10 text-red-400 border border-red-500/30 font-medium text-sm hover:bg-red-500/20 transition-all">
                    <x-icon name="lucide-trash-2" class="w-4 h-4 mr-2" />
                    Delete Entry
                </button>

                <div x-show="showDeleteConfirm"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     @click.outside="showDeleteConfirm = false"
                     class="absolute right-0 mt-2 w-72 bg-[var(--admin-surface)] rounded-lg border border-red-500/30 shadow-xl z-50 p-4">
                    <div class="mb-3">
                        <h4 class="font-semibold text-red-400 mb-1">{{ __('admin.parse_history.delete_confirm') }}</h4>
                        <p class="text-sm text-[var(--admin-muted)]">This will permanently delete this parse history entry. This action cannot be undone.</p>
                    </div>
                    <div class="flex items-center justify-end space-x-2">
                        <button @click="showDeleteConfirm = false"
                                class="px-3 py-1.5 rounded text-sm font-medium text-[var(--admin-text)] hover:bg-[var(--admin-bg)] transition-colors">
                            Cancel
                        </button>
                        <button @click="deleteHistory({{ $history->id }})"
                                class="px-3 py-1.5 rounded text-sm font-medium bg-red-500 text-white hover:brightness-110 transition-all">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Changes Grid --}}
    @if(!empty($history->changes))
        <div class="mb-8">
            <h2 class="text-lg font-bold mb-4 flex items-center space-x-2" style="font-family: 'Outfit', sans-serif;">
                <x-icon name="lucide-file-text" class="w-5 h-5 text-blue-400" />
                <span>Field Changes</span>
            </h2>

            <div class="grid grid-cols-1 gap-4">
                @isset($history->changes['staff'])
                    {{-- Staff changes detail view --}}
                    @php
                        $staffChanges = $history->changes['staff'];
                        $added = StaffRoleHelper::sortStaffByRole($staffChanges['added'] ?? []);
                        $removed = StaffRoleHelper::sortStaffByRole($staffChanges['removed'] ?? []);
                        $roleChanged = StaffRoleHelper::sortStaffByRole($staffChanges['role_changed'] ?? []);
                        $stats = $staffChanges['stats'] ?? [];
                    @endphp

                    @if(count($added) > 0)
                        <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                            <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-green-400">Staff Added</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-green-400/20 text-green-400 font-medium">{{ count($added) }}</span>
                                </div>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach($added as $staff)
                                    @php
                                        $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                    @endphp
                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
                                                <span class="text-xs font-bold text-white">{{ strtoupper(substr($staff['username'], 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-[var(--admin-text)]">{{ $staff['username'] }}</div>
                                                <div class="text-xs text-[var(--admin-muted)]">osu! ID: {{ $staff['osu_id'] }}</div>
                                            </div>
                                        </div>
                                        <span class="px-2 py-1 rounded {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }} border text-xs font-medium">{{ StaffRoleHelper::getRoleLabel($staff['role']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($roleChanged) > 0)
                        <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                            <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-yellow-400">Role Changes</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-yellow-400/20 text-yellow-400 font-medium">{{ count($roleChanged) }}</span>
                                </div>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach($roleChanged as $change)
                                    @php
                                        $oldRoleColor = StaffRoleHelper::getRoleColor($change['old_role']);
                                        $newRoleColor = StaffRoleHelper::getRoleColor($change['new_role']);
                                    @endphp
                                    <div class="flex items-center justify-between p-3 bg-[var(--admin-bg)] rounded border border-[var(--admin-border)]">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
                                                <span class="text-xs font-bold text-white">{{ strtoupper(substr($change['username'], 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-[var(--admin-text)]">{{ $change['username'] }}</div>
                                                <div class="text-xs text-[var(--admin-muted)]">osu! ID: {{ $change['osu_id'] }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="px-2 py-1 rounded {{ $oldRoleColor['bg'] }} {{ $oldRoleColor['text'] }} {{ $oldRoleColor['border'] }} border text-xs font-medium line-through">{{ StaffRoleHelper::getRoleLabel($change['old_role']) }}</span>
                                            <x-icon name="lucide-arrow-right" class="w-4 h-4 text-gray-500" />
                                            <span class="px-2 py-1 rounded {{ $newRoleColor['bg'] }} {{ $newRoleColor['text'] }} {{ $newRoleColor['border'] }} border text-xs font-medium">{{ StaffRoleHelper::getRoleLabel($change['new_role']) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($removed) > 0)
                        <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                            <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-red-400">Staff Removed</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-red-400/20 text-red-400 font-medium">{{ count($removed) }}</span>
                                </div>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach($removed as $staff)
                                    @php
                                        $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                    @endphp
                                    <div class="flex items-center justify-between p-2 bg-[var(--admin-bg)] rounded opacity-60">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center">
                                                <span class="text-xs font-bold text-white line-through">{{ strtoupper(substr($staff['username'], 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-[var(--admin-muted)] line-through">{{ $staff['username'] }}</div>
                                                <div class="text-xs text-[var(--admin-muted)]">osu! ID: {{ $staff['osu_id'] }}</div>
                                            </div>
                                        </div>
                                        <span class="px-2 py-1 rounded {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }} border text-xs font-medium line-through">{{ StaffRoleHelper::getRoleLabel($staff['role']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(isset($stats['before'], $stats['after']))
                        <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                            <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                <span class="text-sm font-bold text-[var(--admin-text)]">Statistics</span>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="text-xs text-[var(--admin-muted)] mb-2">Before Parse</div>
                                        <div class="text-2xl font-bold text-[var(--admin-text)]">{{ $stats['before']['total'] ?? 0 }}</div>
                                        <div class="text-xs text-[var(--admin-muted)]">total staff</div>
                                        @if(isset($stats['before']))
                                            <div class="mt-3 space-y-1">
                                                @foreach(StaffRoleHelper::getAvailableRoles() as $role => $priority)
                                                    @if(isset($stats['before'][$role]) && $role !== 'total')
                                                        @php
                                                            $roleColor = StaffRoleHelper::getRoleColor($role);
                                                        @endphp
                                                        <div class="flex justify-between text-xs">
                                                            <span class="flex items-center space-x-2">
                                                                <span class="w-2 h-2 rounded-full {{ $roleColor['bg'] }}"></span>
                                                                <span class="{{ $roleColor['text'] }}">{{ StaffRoleHelper::getRoleLabel($role) }}</span>
                                                            </span>
                                                            <span class="text-[var(--admin-text)]">{{ $stats['before'][$role] }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-xs text-[var(--admin-muted)] mb-2">After Parse</div>
                                        <div class="text-2xl font-bold text-[var(--osu-cyan)]">{{ $stats['after']['total'] ?? 0 }}</div>
                                        <div class="text-xs text-[var(--admin-muted)]">total staff</div>
                                        @if(isset($stats['after']))
                                            <div class="mt-3 space-y-1">
                                                @foreach(StaffRoleHelper::getAvailableRoles() as $role => $priority)
                                                    @if(isset($stats['after'][$role]) && $role !== 'total')
                                                        @php
                                                            $roleColor = StaffRoleHelper::getRoleColor($role);
                                                        @endphp
                                                        <div class="flex justify-between text-xs">
                                                            <span class="flex items-center space-x-2">
                                                                <span class="w-2 h-2 rounded-full {{ $roleColor['bg'] }}"></span>
                                                                <span class="{{ $roleColor['text'] }}">{{ StaffRoleHelper::getRoleLabel($role) }}</span>
                                                            </span>
                                                            <span class="text-[var(--admin-text)]">{{ $stats['after'][$role] }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    {{-- Field-level changes detail view --}}
                    @foreach($history->changes as $field => $change)
                        @if(array_key_exists('old', $change) || array_key_exists('new', $change))
                            @php
                                $fieldLabels = [
                                    'title' => 'Title',
                                    'description' => 'Description',
                                    'modes' => 'Game Modes',
                                    'rank_range_min' => 'Rank Range (Min)',
                                    'rank_range_max' => 'Rank Range (Max)',
                                    'registration_start' => 'Registration Start',
                                    'registration_end' => 'Registration End',
                                    'tournament_start' => 'Tournament Start',
                                    'tournament_end' => 'Tournament End',
                                    'is_badge' => 'Badge Tournament',
                                    'star_rating_qualifier' => 'Star Rating (Qualifier)',
                                    'star_rating_first' => 'Star Rating (First Round)',
                                    'star_rating_last' => 'Star Rating (Finals)',
                                    'discord_url' => 'Discord URL',
                                    'twitch_url' => 'Twitch URL',
                                    'spreadsheet_url' => 'Spreadsheet URL',
                                    'bracket_url' => 'Bracket URL',
                                    'registration_url' => 'Registration URL',
                                    'tcomm_url' => 'TCOMM URL',
                                ];
                                $label = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));

                                $formatValue = function($value) {
                                    if ($value === null) return '<span class="text-gray-500 italic">Empty</span>';
                                    if (is_array($value)) return implode(', ', array_map(fn($v) => strtoupper($v), $value));
                                    if (is_bool($value)) return $value ? 'Yes' : 'No';
                                    return htmlspecialchars($value);
                                };
                            @endphp

                            {{-- Check if this is a staff change (has added/removed) or field change (has diff_html) --}}
                            @if(isset($change['added']) || isset($change['removed']))
                                {{-- Staff change: Display as simple lists --}}
                                <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                                    {{-- Field Header --}}
                                    <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-bold text-[var(--admin-text)]">{{ $label }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded bg-[var(--admin-bg)] text-[var(--admin-muted)] font-mono">{{ $field }}</span>
                                        </div>
                                    </div>

                                    {{-- Staff lists --}}
                                    <div class="p-4 space-y-3">
                                        @if(isset($change['added']) && count($change['added']) > 0)
                                            <div>
                                                <span class="text-green-500 font-semibold">{{ __('admin.parse_history.added') }}</span>
                                                <ul class="list-disc list-inside mt-1">
                                                    @foreach($change['added'] as $staff)
                                                        <li>{{ $staff['username'] }} ({{ $staff['role'] }})</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if(isset($change['removed']) && count($change['removed']) > 0)
                                            <div>
                                                <span class="text-red-500 font-semibold">{{ __('admin.parse_history.removed') }}</span>
                                                <ul class="list-disc list-inside mt-1">
                                                    @foreach($change['removed'] as $staff)
                                                        <li>{{ $staff['username'] }} ({{ $staff['role'] }})</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if(isset($change['stats']))
                                            <div class="text-sm text-gray-500">
                                                Total: {{ $change['stats']['total'] ?? 0 }} |
                                                Added: {{ $change['stats']['added'] ?? 0 }} |
                                                Removed: {{ $change['stats']['removed'] ?? 0 }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @elseif(isset($change['diff_html']))
                                {{-- Field change: Use diffViewer component --}}
                                <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden"
                                     x-data="diffViewer(@js($change))">
                                    {{-- Field Header --}}
                                    <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-bold text-[var(--admin-text)]">{{ $label }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded bg-[var(--admin-bg)] text-[var(--admin-muted)] font-mono">{{ $field }}</span>
                                        </div>
                                    </div>

                                    {{-- Character-level diff --}}
                                    <div class="p-4">
                                        <div class="p-3 bg-[var(--admin-bg)] rounded border border-[var(--admin-border)] text-sm font-mono break-words diff-content" x-html="getDiffHtml()"></div>
                                    </div>
                                </div>
                            @else
                                {{-- Fallback: Simple old/new display --}}
                                <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
                                    {{-- Field Header --}}
                                    <div class="p-3 border-b border-[var(--admin-border)] bg-[var(--admin-bg)]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-bold text-[var(--admin-text)]">{{ $label }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded bg-[var(--admin-bg)] text-[var(--admin-muted)] font-mono">{{ $field }}</span>
                                        </div>
                                    </div>

                                    {{-- Simple old/new display --}}
                                    <div class="p-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <span class="text-red-500 font-semibold">Old:</span>
                                                <div class="mt-1 p-2 bg-red-50 rounded text-sm">
                                                    @if(isset($change['old']))
                                                        @if(is_array($change['old']))
                                                            <pre class="text-xs">{{ json_encode($change['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @elseif(is_bool($change['old']))
                                                            {{ $change['old'] ? 'Yes' : 'No' }}
                                                        @else
                                                            {{ $change['old'] }}
                                                        @endif
                                                    @else
                                                        <span class="text-gray-500 italic">Empty</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-green-500 font-semibold">New:</span>
                                                <div class="mt-1 p-2 bg-green-50 rounded text-sm">
                                                    @if(isset($change['new']))
                                                        @if(is_array($change['new']))
                                                            <pre class="text-xs">{{ json_encode($change['new'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @elseif(is_bool($change['new']))
                                                            {{ $change['new'] ? 'Yes' : 'No' }}
                                                        @else
                                                            {{ $change['new'] }}
                                                        @endif
                                                    @else
                                                        <span class="text-gray-500 italic">Empty</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endisset
            </div>
        </div>
    @else
        <div class="mb-8 bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] p-8 text-center">
            <x-icon name="lucide-circle-check" class="w-12 h-12 mx-auto mb-3 text-[var(--admin-muted)]" />
            <p class="text-[var(--admin-muted)]">No changes detected in this parse</p>
        </div>
    @endif

    {{-- Parsed Data Snapshot --}}
    <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
        <div x-data="{ showJson: false }">
            {{-- Header --}}
            <div class="p-4 border-b border-[var(--admin-border)] flex items-center justify-between">
                <h2 class="text-lg font-bold flex items-center space-x-2" style="font-family: 'Outfit', sans-serif;">
                    <x-icon name="lucide-code-2" class="w-5 h-5 text-[var(--osu-cyan)]" />
                    <span>{{ $history->compacted_at ? 'Compacted Parsed Data Summary' : 'Full Parsed Data Snapshot' }}</span>
                </h2>

                <button @click="showJson = !showJson"
                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--admin-bg)] text-[var(--admin-text)] hover:bg-[var(--admin-border)] transition-all border border-[var(--admin-border)]">
                    <x-icon name="lucide-chevron-down" class="w-4 h-4 mr-1.5" />
                    <span x-text="showJson ? 'Hide' : 'Show'"></span> JSON
                </button>
            </div>

            @if($history->compacted_at)
                <div class="p-4 border-b border-[var(--admin-border)] bg-amber-500/10 text-sm text-amber-200">
                    Full raw parsed data was compacted during database maintenance. The summary below preserves hashes, payload size, and top-level keys for auditability.
                </div>
            @endif

            {{-- JSON Content (Collapsible) --}}
            <div x-show="showJson" x-collapse>
                <div class="p-4 bg-[var(--admin-bg)] overflow-x-auto">
                    <pre class="text-xs font-mono text-[var(--admin-muted)]">{{ json_encode($history->parsed_data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
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
            window.location.href = '/admin/tournaments/{{ $tournament->id }}/parse-history';
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


@endsection
