@props([
    'tournament' => null,
])

@php
    use App\Helpers\StaffRoleHelper;

    $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());
    $groupedStaff = [];

    if ($tournament && $tournament->staff) {
        foreach ($tournament->staff as $user) {
            if (! $user->pivot) {
                continue;
            }

            $userId = $user->id;

            if (! isset($groupedStaff[$userId])) {
                $groupedStaff[$userId] = [
                    'user' => $user,
                    'roles' => [],
                ];
            }

            $groupedStaff[$userId]['roles'][] = [
                'role' => $user->pivot->role,
                'staff_id' => $user->pivot->id,
            ];
        }

        foreach ($groupedStaff as &$staffGroup) {
            usort($staffGroup['roles'], function (array $a, array $b): int {
                return StaffRoleHelper::getRolePriority($a['role']) <=> StaffRoleHelper::getRolePriority($b['role']);
            });
        }
        unset($staffGroup);

        uksort($groupedStaff, function ($a, $b) use ($groupedStaff) {
            $countA = count($groupedStaff[$a]['roles']);
            $countB = count($groupedStaff[$b]['roles']);

            if ($countA !== $countB) {
                return $countB <=> $countA;
            }

            return strcasecmp($groupedStaff[$a]['user']->username, $groupedStaff[$b]['user']->username);
        });
    }
@endphp

<div
    class="space-y-3"
    id="staffList"
    data-tournament-staff-list="{{ $tournament->id }}"
    x-data="{
        staffSearch: '',
        roleFilter: 'all',
        availableRoles: @js($availableRoles),
        editingUser: null,
        savingUser: null,
        editRoles: [],
        matches(username, roles) {
            const query = this.staffSearch.trim().toLowerCase();
            const searchMatches = query === '' || username.toLowerCase().includes(query);
            const roleMatches = this.roleFilter === 'all' || roles.includes(this.roleFilter);

            return searchMatches && roleMatches;
        },
        beginEdit(userId, roles) {
            this.editingUser = userId;
            this.editRoles = [...roles];
        },
        toggleEditRole(role) {
            this.editRoles = this.editRoles.includes(role)
                ? this.editRoles.filter(item => item !== role)
                : [...this.editRoles, role];
        },
        saveRoles(userId, url) {
            this.savingUser = userId;

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                },
                body: JSON.stringify({ roles: this.editRoles }),
            })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || data.error || `HTTP ${response.status}`);
                    }

                    this.editingUser = null;

                    if (typeof refreshStaffList === 'function') {
                        refreshStaffList();
                    }

                    if (typeof showSuccessMessage === 'function') {
                        showSuccessMessage('Staff roles updated');
                    }
                })
                .catch(error => {
                    alert(error.message || 'Failed to update staff roles');
                })
                .finally(() => {
                    this.savingUser = null;
                });
        },
        bulkDeleteSelectedRole() {
            if (this.roleFilter === 'all') {
                alert('Select a role before using bulk delete.');
                return;
            }

            const label = this.availableRoles.includes(this.roleFilter)
                ? this.roleFilter.charAt(0).toUpperCase() + this.roleFilter.slice(1)
                : this.roleFilter;

            if (!confirm(`Remove the ${label} role from all staff in this tournament? Other roles for those users will remain.`)) {
                return;
            }

            fetch(`{{ url('/admin/tournaments/'.$tournament->id.'/staff/roles') }}/${encodeURIComponent(this.roleFilter)}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                },
            })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || data.error || `HTTP ${response.status}`);
                    }

                    this.roleFilter = 'all';

                    if (typeof refreshStaffList === 'function') {
                        refreshStaffList();
                    }

                    if (typeof showSuccessMessage === 'function') {
                        showSuccessMessage(`Removed ${data.deleted_count} staff role assignment(s)`);
                    }
                })
                .catch(error => {
                    alert(error.message || 'Failed to bulk delete role');
                });
        }
    }"
>
    <div class="sticky top-0 z-10 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <input
                    type="search"
                    x-model="staffSearch"
                    placeholder="Search staff username..."
                    class="w-full rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 pr-9 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)]"
                >
                <x-icon name="lucide-search" class="absolute right-3 top-2.5 h-4 w-4 text-[var(--admin-muted)]" />
            </div>

            <select
                x-model="roleFilter"
                class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--osu-cyan)]"
            >
                <option value="all">All roles</option>
                @foreach($availableRoles as $role)
                    <option value="{{ $role }}">{{ StaffRoleHelper::getRoleLabel($role) }}</option>
                @endforeach
            </select>

            <button
                type="button"
                x-show="roleFilter !== 'all'"
                x-transition
                class="rounded-lg border border-[var(--danger)] px-3 py-2 text-sm font-semibold text-[var(--danger)] transition hover:bg-[var(--danger)]/10"
                @click="bulkDeleteSelectedRole()"
            >
                Delete Selected Role
            </button>
        </div>
    </div>

    <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
        @forelse($groupedStaff as $userId => $data)
            @php
                $roles = collect($data['roles'])->pluck('role')->values()->all();
                $roleList = implode(',', $roles);
            @endphp

            <div
                x-data="{ expanded: false }"
                x-show="matches(@js($data['user']->username), @js($roles))"
                class="rounded-lg border border-[var(--admin-border)] bg-[var(--admin-surface)] transition-all hover:border-[var(--osu-cyan)]"
                data-user-id="{{ $userId }}"
                data-username="{{ strtolower($data['user']->username) }}"
                data-roles="{{ $roleList }}"
            >
                <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="expanded = !expanded">
                        <img
                            src="{{ $data['user']->avatar_url }}"
                            alt="{{ $data['user']->username }}"
                            class="h-10 w-10 shrink-0 rounded-full ring-2 ring-[var(--admin-border)]"
                        >
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-semibold text-[var(--admin-text)]">{{ $data['user']->username }}</span>
                                @if($data['user']->osu_id === $tournament->host_osu_id)
                                    <span class="rounded-full bg-[var(--osu-pink)]/10 px-2 py-0.5 text-xs font-bold uppercase text-[var(--osu-pink)]">(Host)</span>
                                @endif
                            </span>
                            <span class="block text-xs text-[var(--admin-muted)]">#{{ $data['user']->osu_id }}</span>
                        </span>
                    </button>

                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        @foreach($roles as $role)
                            @php($roleColor = StaffRoleHelper::getRoleColor($role))
                            <span class="rounded border px-2 py-0.5 text-xs font-medium {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }}">
                                {{ StaffRoleHelper::getRoleLabel($role) }}
                            </span>
                        @endforeach

                        @if($data['user']->osu_id !== $tournament->host_osu_id && in_array('organizer', $roles, true))
                            <button
                                type="button"
                                onclick="makeHost({{ $data['user']->id }}, '{{ e($data['user']->username) }}')"
                                class="rounded bg-[var(--osu-pink)] px-2.5 py-1 text-xs font-bold text-white shadow-sm transition hover:brightness-110"
                            >
                                Make Host
                            </button>
                        @endif

                        <button
                            type="button"
                            class="rounded border border-[var(--admin-border)] px-2.5 py-1 text-xs font-semibold text-[var(--admin-text)] transition hover:bg-[var(--admin-bg)]"
                            @click="beginEdit({{ $userId }}, @js($roles)); expanded = true"
                        >
                            Edit Roles
                        </button>

                        <button type="button" class="p-1 text-[var(--admin-muted)]" @click="expanded = !expanded" aria-label="Toggle staff details">
                            <x-icon name="lucide-chevron-down" id="expandIcon-{{ $userId }}" class="h-5 w-5 transition-transform duration-200" x-bind:class="{ 'rotate-180': expanded }" />
                        </button>
                    </div>
                </div>

                <div
                    x-show="expanded"
                    x-transition
                    class="border-t border-[var(--admin-border)] p-3"
                    id="userRoles-{{ $userId }}"
                    style="display: none;"
                >
                    <div x-show="editingUser === {{ $userId }}" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-5">
                            @foreach($availableRoles as $role)
                                @php($roleColor = StaffRoleHelper::getRoleColor($role))
                                <button
                                    type="button"
                                    class="rounded-lg border p-2 text-sm font-medium transition hover:brightness-110"
                                    :class="editRoles.includes('{{ $role }}')
                                        ? 'ring-2 ring-[var(--osu-cyan)] {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }}'
                                        : 'border-[var(--admin-border)] bg-[var(--admin-bg)] text-[var(--admin-muted)]'"
                                    @click="toggleEditRole('{{ $role }}')"
                                >
                                    {{ StaffRoleHelper::getRoleLabel($role) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-lg border border-[var(--admin-border)] px-4 py-2 text-sm font-medium text-[var(--admin-text)] hover:bg-[var(--admin-bg)]"
                                @click="editingUser = null"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-[var(--osu-cyan)] px-4 py-2 text-sm font-semibold text-[var(--admin-bg)] hover:brightness-110 disabled:opacity-50"
                                :disabled="savingUser === {{ $userId }}"
                                @click="saveRoles({{ $userId }}, '{{ route('admin.tournaments.staff.roles.replace', [$tournament, $data['user']]) }}')"
                                x-text="savingUser === {{ $userId }} ? 'Saving...' : 'Save Roles'"
                            ></button>
                        </div>
                    </div>

                    <div x-show="editingUser !== {{ $userId }}" class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            @foreach($roles as $role)
                                @php($roleColor = StaffRoleHelper::getRoleColor($role))
                                <span class="rounded border px-2 py-1 text-sm font-medium {{ $roleColor['bg'] }} {{ $roleColor['text'] }} {{ $roleColor['border'] }}">
                                    {{ StaffRoleHelper::getRoleLabel($role) }}
                                </span>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-2 rounded-lg border border-[var(--admin-border)] bg-[var(--admin-bg)] p-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-[var(--admin-muted)]">
                                Edit this user's roles together with the checklist.
                            </p>
                            <button
                                type="button"
                                class="rounded-lg bg-[var(--osu-cyan)] px-4 py-2 text-sm font-semibold text-[var(--admin-bg)] hover:brightness-110"
                                @click="beginEdit({{ $userId }}, @js($roles))"
                            >
                                Edit Roles
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-[var(--admin-muted)]">No staff assigned yet</p>
        @endforelse
    </div>
</div>
