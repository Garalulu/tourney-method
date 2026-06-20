@props([
    'tournament' => null,
])

@php
    use App\Helpers\StaffRoleHelper;
    $latestParseHistory = $tournament->parseHistories()->first();
@endphp

@if($latestParseHistory && !empty($latestParseHistory->changes))
    <div class="mb-6 bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] overflow-hidden">
        <div class="p-4 border-b border-[var(--admin-border)] flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="p-2 rounded-lg bg-blue-500/10 border border-blue-500/30">
                    <x-icon name="lucide-file-text" class="w-5 h-5 text-blue-400" />
                </div>
                <div>
                    <h3 class="text-lg font-bold" style="font-family: 'Outfit', sans-serif;">
                        Latest Parse Changes
                    </h3>
                    <p class="text-xs text-[var(--admin-muted)]">
                        Parsed {{ ($latestParseHistory->parsed_at ?? $latestParseHistory->created_at)?->diffForHumans() ?? 'just now' }} ·
                        @isset($latestParseHistory->changes['staff'])
                            @php
                                $staffChanges = $latestParseHistory->changes['staff'];
                                $addedCount = count($staffChanges['added'] ?? []);
                                $removedCount = count($staffChanges['removed'] ?? []);
                                $roleChangedCount = count($staffChanges['role_changed'] ?? []);
                            @endphp
                            <span class="text-blue-400">
                                @if($addedCount > 0)
                                    {{ $addedCount }} added
                                @endif
                                @if($roleChangedCount > 0)
                                    {{ $removedCount > 0 || $addedCount > 0 ? ', ' : '' }}{{ $roleChangedCount }} role changes
                                @endif
                                @if($removedCount > 0)
                                    {{ $addedCount > 0 || $roleChangedCount > 0 ? ', ' : '' }}{{ $removedCount }} removed
                                @endif
                                @if($addedCount === 0 && $removedCount === 0 && $roleChangedCount === 0)
                                    No staff changes
                                @endif
                            </span>
                        @else
                            <span class="text-blue-400">{{ count($latestParseHistory->changes) }} fields updated</span>
                        @endisset
                        @if($latestParseHistory->compacted_at)
                            &middot; <span class="text-amber-400">compacted</span>
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('admin.tournaments.parse-history', $tournament) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--admin-bg)] text-[var(--admin-text)] hover:bg-[var(--admin-border)] transition-all border border-[var(--admin-border)]">
                View All History
                <x-icon name="lucide-chevron-right" class="w-4 h-4 ml-1.5" />
            </a>
        </div>

        <div class="p-4 space-y-3 max-h-[400px] overflow-y-auto">
            @isset($latestParseHistory->changes['staff'])
                {{-- Staff changes display --}}
                @php
                    $staffChanges = $latestParseHistory->changes['staff'];
                    $added = StaffRoleHelper::sortStaffByRole($staffChanges['added'] ?? []);
                    $removed = StaffRoleHelper::sortStaffByRole($staffChanges['removed'] ?? []);
                    $roleChanged = StaffRoleHelper::sortStaffByRole($staffChanges['role_changed'] ?? []);
                    $stats = $staffChanges['stats'] ?? [];
                @endphp

                @if(count($added) > 0)
                    <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-green-400">Staff Added ({{ count($added) }})</span>
                        </div>
                        <div class="space-y-1 max-h-[200px] overflow-y-auto">
                            @foreach($added as $staff)
                                @php
                                    $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                @endphp
                                <div class="flex items-center justify-between text-sm p-2 bg-[var(--admin-surface)] rounded">
                                    <span class="text-[var(--admin-text)]">{{ $staff['username'] }}</span>
                                    <span class="px-2 py-0.5 rounded {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }} border text-xs font-medium">{{ StaffRoleHelper::getRoleLabel($staff['role']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($roleChanged) > 0)
                    <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-yellow-400">Role Changes ({{ count($roleChanged) }})</span>
                        </div>
                        <div class="space-y-1 max-h-[200px] overflow-y-auto">
                            @foreach($roleChanged as $change)
                                @php
                                    $oldRoleColor = StaffRoleHelper::getRoleColor($change['old_role']);
                                    $newRoleColor = StaffRoleHelper::getRoleColor($change['new_role']);
                                @endphp
                                <div class="flex items-center justify-between text-sm p-2 bg-[var(--admin-surface)] rounded">
                                    <span class="text-[var(--admin-text)]">{{ $change['username'] }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-0.5 rounded {{ $oldRoleColor['bg'] }} {{ $oldRoleColor['text'] }} {{ $oldRoleColor['border'] }} border text-xs line-through">{{ StaffRoleHelper::getRoleLabel($change['old_role']) }}</span>
                                        <span class="text-gray-500">→</span>
                                        <span class="px-2 py-0.5 rounded {{ $newRoleColor['bg'] }} {{ $newRoleColor['text'] }} {{ $newRoleColor['border'] }} border text-xs">{{ StaffRoleHelper::getRoleLabel($change['new_role']) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($removed) > 0)
                    <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-red-400">Staff Removed ({{ count($removed) }})</span>
                        </div>
                        <div class="space-y-1 max-h-[200px] overflow-y-auto">
                            @foreach($removed as $staff)
                                @php
                                    $roleColor = StaffRoleHelper::getRoleColor($staff['role']);
                                @endphp
                                <div class="flex items-center justify-between text-sm p-2 bg-[var(--admin-surface)] rounded">
                                    <span class="text-[var(--admin-text)] line-through">{{ $staff['username'] }}</span>
                                    <span class="px-2 py-0.5 rounded {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }} border text-xs line-through">{{ StaffRoleHelper::getRoleLabel($staff['role']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(isset($stats['before'], $stats['after']))
                    <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                        <div class="text-sm font-semibold text-[var(--admin-text)] mb-2">Statistics</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-[var(--admin-muted)] text-xs">Before</div>
                                <div class="text-[var(--admin-text)] font-medium">{{ $stats['before']['total'] ?? 0 }} staff</div>
                                @if(isset($stats['before']))
                                    @foreach(StaffRoleHelper::getAvailableRoles() as $role => $priority)
                                        @if(isset($stats['before'][$role]) && $role !== 'total')
                                            @php
                                                $roleColor = StaffRoleHelper::getRoleColor($role);
                                            @endphp
                                            <div class="flex items-center justify-between text-xs py-0.5">
                                                <span class="flex items-center space-x-2">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $roleColor['bg'] }}"></span>
                                                    <span class="{{ $roleColor['text'] }}">{{ StaffRoleHelper::getRoleLabel($role) }}</span>
                                                </span>
                                                <span class="text-[var(--admin-text)]">{{ $stats['before'][$role] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                            <div>
                                <div class="text-[var(--admin-muted)] text-xs">After</div>
                                <div class="text-[var(--admin-text)] font-medium">{{ $stats['after']['total'] ?? 0 }} staff</div>
                                @if(isset($stats['after']))
                                    @foreach(StaffRoleHelper::getAvailableRoles() as $role => $priority)
                                        @if(isset($stats['after'][$role]) && $role !== 'total')
                                            @php
                                                $roleColor = StaffRoleHelper::getRoleColor($role);
                                            @endphp
                                            <div class="flex items-center justify-between text-xs py-0.5">
                                                <span class="flex items-center space-x-2">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $roleColor['bg'] }}"></span>
                                                    <span class="{{ $roleColor['text'] }}">{{ StaffRoleHelper::getRoleLabel($role) }}</span>
                                                </span>
                                                <span class="text-[var(--admin-text)]">{{ $stats['after'][$role] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Field-level changes display (old format) --}}
                @foreach($latestParseHistory->changes as $field => $change)
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
                        @endphp

                        @if(isset($change['diff_html']))
                            {{-- Use diffViewer for fields with diff_html --}}
                            <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)] hover:border-[var(--admin-border)] transition-all"
                                 x-data="diffViewer(@js($change))">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-[var(--admin-text)]">{{ $label }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-[var(--admin-surface)] text-[var(--admin-muted)] font-mono">{{ $field }}</span>
                                </div>

                                <div class="space-y-2">
                                    {{-- Character-level diff (backend-generated) --}}
                                    <div class="p-3 bg-[var(--admin-surface)] rounded border border-[var(--admin-border)] text-sm font-mono break-words diff-content" x-html="getDiffHtml()"></div>
                                </div>
                            </div>
                        @else
                            {{-- Fallback: Simple old/new display for fields without diff_html --}}
                            <div class="p-3 bg-[var(--admin-bg)] rounded-lg border border-[var(--admin-border)]">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-[var(--admin-text)]">{{ $label }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-[var(--admin-surface)] text-[var(--admin-muted)] font-mono">{{ $field }}</span>
                                </div>

                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-red-400 font-medium">Old:</span>
                                        <div class="mt-1 text-[var(--admin-text)]">
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
                                        <span class="text-green-400 font-medium">New:</span>
                                        <div class="mt-1 text-[var(--admin-text)]">
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
                        @endif
                    @endif
                @endforeach
            @endisset
        </div>
    </div>
@elseif($tournament->parse_count > 0)
    <div class="mb-6 bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 text-[var(--admin-muted)]">
                <x-icon name="lucide-circle-check" class="w-6 h-6" />
                <div class="text-sm">
                    <p class="font-medium">No changes in latest parse</p>
                    <p class="text-xs mt-0.5">Tournament was re-parsed but all values remained the same</p>
                </div>
            </div>
            <a href="{{ route('admin.tournaments.parse-history', $tournament) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--admin-bg)] text-[var(--admin-text)] hover:bg-[var(--admin-border)] transition-all border border-[var(--admin-border)]">
                View All History
                <x-icon name="lucide-chevron-right" class="w-4 h-4 ml-1.5" />
            </a>
        </div>
    </div>
@endif
