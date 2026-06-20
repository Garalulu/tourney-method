/**
 * STAFF MANAGEMENT UI/UX - ENHANCED JAVASCRIPT
 *
 * Complete implementation for multi-role selection with checkbox grid,
 * user-grouped staff list, and role management.
 *
 * This replaces lines 892-1347 in review.blade.php
 */

// Helper function to get tournament ID from DOM
function getTournamentId() {
    const modal = document.getElementById('staffModal');
    if (!modal) {
        console.error('[getTournamentId] staffModal element not found');
        return null;
    }
    const id = modal.dataset.tournamentId;
    if (!id) {
        console.error('[getTournamentId] tournament-id data attribute not found');
        return null;
    }
    return id;
}

// Read CSRF token safely
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (!csrfToken) {
    console.error('CSRF token meta tag not found');
}

let selectedUserId = null;
let selectedRoles = new Set();  // NEW: Track multiple selected roles
let userExistingRoles = new Set();  // NEW: Track user's existing roles
let searchTimeout = null;

// Available roles with all 10 roles (priorities match StaffRoleHelper.php)
const availableRoles = [
    { key: 'organizer', label: 'Organizer', priority: 1 },
    { key: 'mappooler', label: 'Mappooler', priority: 2 },
    { key: 'playtester', label: 'Playtester', priority: 3 },
    { key: 'mapper', label: 'Mapper', priority: 4 },
    { key: 'gfx', label: 'GFX', priority: 5 },
    { key: 'sheeter', label: 'Sheeter', priority: 6 },
    { key: 'referee', label: 'Referee', priority: 7 },
    { key: 'streamer', label: 'Streamer', priority: 8 },
    { key: 'commentator', label: 'Commentator', priority: 9 },
    { key: 'other', label: 'Other', priority: 10 },
];

// Role colors matching StaffRoleHelper.php
const roleColors = {
    organizer: 'bg-purple-500/20 text-purple-400 border-purple-500/50',
    mappooler: 'bg-blue-500/20 text-blue-400 border-blue-500/50',
    playtester: 'bg-green-500/20 text-green-400 border-green-500/50',
    mapper: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/50',
    gfx: 'bg-pink-500/20 text-pink-400 border-pink-500/50',
    sheeter: 'bg-orange-500/20 text-orange-400 border-orange-500/50',
    referee: 'bg-red-500/20 text-red-400 border-red-500/50',
    streamer: 'bg-cyan-500/20 text-cyan-400 border-cyan-500/50',
    commentator: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50',
    other: 'bg-gray-500/20 text-gray-400 border-gray-500/50',
};

// Make all functions globally available for onclick handlers
window.openStaffModal = function() {
    document.getElementById('staffModal').classList.remove('hidden');
    document.getElementById('staffModal').classList.add('flex');
    document.getElementById('userSearch').focus();
    populateRolesGrid();  // NEW: Populate checkbox grid
};

window.closeStaffModal = function() {
    document.getElementById('staffModal').classList.add('hidden');
    document.getElementById('staffModal').classList.remove('flex');
    resetStaffModal();
};

function resetStaffModal() {
    document.getElementById('userSearch').value = '';
    document.getElementById('userSearchResults').classList.add('hidden');
    document.getElementById('selectedUser').classList.add('hidden');
    document.getElementById('staffNotes').value = '';
    selectedUserId = null;
    selectedRoles.clear();
    userExistingRoles.clear();
    updateRolesGrid();
    updateSelectedRolesSummary();
    updateAddButton();
}

function populateRolesGrid() {
    const rolesGrid = document.getElementById('rolesGrid');
    rolesGrid.innerHTML = '';

    availableRoles.forEach(role => {
        const color = roleColors[role.key];
        const isExisting = userExistingRoles.has(role.key);

        const checkbox = document.createElement('label');
        checkbox.className = `
            relative flex flex-col items-center justify-center p-3 rounded-lg border cursor-pointer
            transition-all duration-200
            hover:scale-[1.02] hover:brightness-110 bg-[var(--admin-bg)]
            border-[var(--admin-border)] ${color}
        `;

        // Pre-select existing roles and make them toggleable
        const isChecked = isExisting || selectedRoles.has(role.key);

        checkbox.innerHTML = `
            <input type="checkbox"
                   value="${role.key}"
                   data-role="${role.key}"
                   ${isChecked ? 'checked' : ''}
                   class="role-checkbox sr-only">
            <div class="flex flex-col items-center space-y-1">
                <div class="flex items-center space-x-2">
                    <span class="font-semibold text-sm">${role.label}</span>
                    ${isExisting
                        ? '<span class="text-xs opacity-75" title="Current role - deselect to remove">✓ Current</span>'
                        : ''}
                </div>
                <div class="w-3 h-3 rounded-full border-2 border-current flex items-center justify-center ${isChecked ? 'bg-current' : 'bg-transparent'}">
                    ${isChecked ? '<span class="block h-1.5 w-1.5 rounded-full bg-white"></span>' : ''}
                </div>
            </div>
        `;

        checkbox.addEventListener('change', (e) => {
            toggleRole(role.key);
        });

        rolesGrid.appendChild(checkbox);
    });

    // Update selectedRoles to include existing roles (so they're sent with the form)
    userExistingRoles.forEach(role => {
        if (!selectedRoles.has(role)) {
            selectedRoles.add(role);
        }
    });
}

function toggleRole(roleKey) {
    if (selectedRoles.has(roleKey)) {
        selectedRoles.delete(roleKey);
    } else {
        selectedRoles.add(roleKey);
    }
    updateRolesGrid();
    updateSelectedRolesSummary();
    updateAddButton();
}

function updateRolesGrid() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    checkboxes.forEach(checkbox => {
        const roleKey = checkbox.dataset.role;
        const isChecked = selectedRoles.has(roleKey);
        const isExisting = userExistingRoles.has(roleKey);

        checkbox.checked = isChecked && !isExisting;

        // Update visual state
        const label = checkbox.closest('label');
        if (isChecked) {
            label.classList.add('ring-2', 'ring-[var(--osu-cyan)]');
            label.classList.remove('border-[var(--admin-border)]');
        } else {
            label.classList.remove('ring-2', 'ring-[var(--osu-cyan)]');
            label.classList.add('border-[var(--admin-border)]');
        }
    });
}

function updateSelectedRolesSummary() {
    const summaryDiv = document.getElementById('selectedRolesSummary');

    if (selectedRoles.size === 0) {
        summaryDiv.innerHTML = '<p class="text-sm text-[var(--admin-muted)]">No roles selected</p>';
        return;
    }

    const selectedLabels = Array.from(selectedRoles)
        .sort((a, b) => {
            const roleA = availableRoles.find(r => r.key === a);
            const roleB = availableRoles.find(r => r.key === b);
            return roleA.priority - roleB.priority;
        })
        .map(key => availableRoles.find(r => r.key === key).label);

    summaryDiv.innerHTML = `
        <div class="flex items-center space-x-2 flex-wrap">
            ${selectedLabels.map(label => `
                <span class="px-2 py-1 rounded text-xs font-medium bg-[var(--osu-cyan)]/10 text-[var(--admin-bg)]">
                    ${label}
                </span>
            `).join('')}
        </div>
    `;

    summaryDiv.classList.remove('hidden');
}

window.searchUsers = function(query) {
    clearTimeout(searchTimeout);

    if (query.length < 3) {
        document.getElementById('userSearchResults').classList.add('hidden');
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`/api/users/search?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('userSearchResults');
                resultsDiv.innerHTML = '';

                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div class="p-4 text-center text-[var(--admin-muted)]">No users found</div>';
                } else {
                    data.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center space-x-3 p-3 hover:bg-[var(--admin-bg)] cursor-pointer transition-colors border-b border-[var(--admin-border)] last:border-b-0';
                        div.onclick = () => selectUser(user);

                        const unregisteredBadge = !user.registered ?
                            '<span class="ml-2 px-2 py-0.5 rounded text-xs bg-yellow-500/10 text-yellow-600 border border-yellow-500/30">Not Registered</span>' : '';

                        const avatarUrl = user.avatar_url || 'https://a.ppy.sh/';
                        div.innerHTML = `
                            <img src="${avatarUrl}" alt="${user.username}" class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <div class="font-semibold text-[var(--admin-text)]">${user.username}${unregisteredBadge}</div>
                                <div class="text-xs text-[var(--admin-muted)]">${user.osu_id ? '#' + user.osu_id : 'No osu! ID yet'}</div>
                            </div>
                        `;
                        resultsDiv.appendChild(div);
                    });
                }

                resultsDiv.classList.remove('hidden');
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }, 300);
};

window.selectUser = async function(user) {
    selectedUserId = user.id;
    selectedRoles.clear();  // NEW: Clear previous role selections
    document.getElementById('userSearchResults').classList.add('hidden');
    document.getElementById('userSearch').value = user.username;

    // Show loading state
    showUserSyncLoading();

    try {
        // Fetch fresh data from osu! API
        const response = await fetch(`/api/users/${encodeURIComponent(user.username)}/sync`);

        // Log response status for debugging
        console.log(`Sync response status: ${response.status} for user: ${user.username}`);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
            console.error('Sync failed:', errorData);
            throw new Error(`Failed to sync user: ${errorData.error || 'Unknown error'}`);
        }

        const freshData = await response.json();
        console.log('Sync successful, fresh data:', freshData);

        // Update UI with fresh data
        displayUserWithFreshData(freshData, user.registered);

        // Only fetch existing roles if user is registered AND has valid numeric ID
        if (user.registered && freshData.id && typeof freshData.id === 'number' && freshData.id > 0) {
            fetchUserExistingRoles(freshData.id);
        } else {
            // For unregistered users or invalid IDs, clear existing roles
            console.log('Unregistered user or invalid ID - skipping existing roles fetch');
            userExistingRoles.clear();
            populateRolesGrid();
        }

        // Clear and reset role selection UI
        document.getElementById('selectedRolesSummary').classList.add('hidden');
        updateAddButton();
    } catch (error) {
        console.error('Sync error:', error);

        // Fallback to original search result data
        displayUserWithOriginalData(user);

        // Only fetch existing roles if user is registered AND has valid numeric ID
        // Unregistered users have string IDs like "new:username" which will cause database errors
        if (user.registered && user.id && typeof user.id === 'number' && user.id > 0) {
            fetchUserExistingRoles(user.id);
        } else {
            // For unregistered users or invalid IDs, clear existing roles
            console.log('Unregistered user or invalid ID - skipping existing roles fetch');
            userExistingRoles.clear();
            populateRolesGrid();
        }

        // Clear and reset role selection UI
        document.getElementById('selectedRolesSummary').classList.add('hidden');
        updateAddButton();
    } finally {
        hideUserSyncLoading();
    }
};

function showUserSyncLoading() {
    const selectedUserDiv = document.getElementById('selectedUser');
    selectedUserDiv.classList.remove('hidden');

    // Show loading indicator
    const avatar = document.getElementById('selectedUserAvatar');
    const username = document.getElementById('selectedUsername');
    const userId = document.getElementById('selectedUserId');

    avatar.removeAttribute('src');
    avatar.classList.add('animate-pulse', 'bg-[var(--admin-border)]');
    username.textContent = 'Fetching osu! profile...';
    userId.textContent = 'Please wait...';

    // Disable role selection during sync
    disableRoleSelection(true);
}

function hideUserSyncLoading() {
    // Re-enable role selection
    disableRoleSelection(false);
}

function displayUserWithFreshData(freshData, wasRegistered) {
    const avatar = document.getElementById('selectedUserAvatar');
    avatar.classList.remove('animate-pulse', 'bg-[var(--admin-border)]');
    avatar.src = freshData.avatar_url || 'https://a.ppy.sh/';
    document.getElementById('selectedUsername').textContent = freshData.username + (!wasRegistered ? ' (Not Registered)' : '');
    document.getElementById('selectedUserId').textContent = freshData.osu_id ? `#${freshData.osu_id}` : 'Will be added when they register';

    // Update selectedUserId with the actual database ID
    selectedUserId = freshData.id;

    // Show warning if user is not registered
    const warningDiv = document.getElementById('unregisteredWarning');
    if (!wasRegistered) {
        warningDiv.classList.remove('hidden');
    } else {
        warningDiv.classList.add('hidden');
    }
}

function displayUserWithOriginalData(user) {
    const avatar = document.getElementById('selectedUserAvatar');
    avatar.classList.remove('animate-pulse', 'bg-[var(--admin-border)]');
    avatar.src = user.avatar_url || 'https://a.ppy.sh/';
    document.getElementById('selectedUsername').textContent = user.username + (!user.registered ? ' (Not Registered)' : '');
    document.getElementById('selectedUserId').textContent = user.osu_id ? `#${user.osu_id}` : 'Will be added when they register';

    // Show warning if user is not registered
    const warningDiv = document.getElementById('unregisteredWarning');
    if (!user.registered) {
        warningDiv.classList.remove('hidden');
    } else {
        warningDiv.classList.add('hidden');
    }
}

function disableRoleSelection(disable) {
    // Disable all role checkboxes during sync
    const checkboxes = document.querySelectorAll('input[type="checkbox"][id^="role_"]');
    checkboxes.forEach(checkbox => {
        checkbox.disabled = disable;
    });

    // Disable the add button
    const addButton = document.getElementById('addStaffButton');
    if (addButton) {
        addButton.disabled = disable;
    }
}

function fetchUserExistingRoles(userId) {
    const tournamentId = getTournamentId();

    // Validate tournament ID - must be numeric string, not null/undefined/string "null"
    if (!tournamentId || !/^\d+$/.test(String(tournamentId))) {
        console.error('Cannot fetch user roles: invalid tournament ID', tournamentId);
        userExistingRoles.clear();
        populateRolesGrid();
        return;
    }

    // Validate user ID - must be a number, not null/undefined/string "null"
    if (!userId || typeof userId !== 'number' || userId <= 0) {
        console.warn('Cannot fetch user roles: invalid user ID', userId);
        userExistingRoles.clear();
        populateRolesGrid();
        return;
    }

    fetch(`/admin/tournaments/${tournamentId}/staff?user_id=${userId}`)
        .then(response => {
            if (!response.ok) {
                console.warn('Failed to fetch existing roles:', response.status);
                userExistingRoles.clear();
                populateRolesGrid();
                return Promise.resolve({ staff: [] });
            }
            return response.json();
        })
        .then(data => {
            userExistingRoles.clear();

            // Store existing roles
            if (data.staff && data.staff.length > 0) {
                data.staff.forEach(staff => {
                    userExistingRoles.add(staff.role);
                });
            }

            // Update roles grid to show existing roles
            populateRolesGrid();
        })
        .catch(error => {
            console.error('Error fetching user roles:', error);
            userExistingRoles.clear();
            populateRolesGrid();
        });
}

function updateAddButton() {
    const button = document.getElementById('addStaffBtn');
    button.disabled = !selectedUserId || selectedRoles.size === 0;
}

// NEW: Make a staff member the host
window.makeHost = function(userId, username) {
    if (!confirm(`Make ${username} the tournament host? This will replace the current host.`)) {
        return;
    }

    const tournamentId = getTournamentId();
    if (!tournamentId) {
        console.error('Cannot update host: tournament ID not available');
        return;
    }

    fetch(`/admin/tournaments/${tournamentId}/make-host/${userId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshStaffList();
        } else {
            alert(data.message || 'Failed to update host');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update host');
    });
};

// UPDATED: Add staff with roles array
window.addStaff = function() {
    const notes = document.getElementById('staffNotes').value;
    const roles = Array.from(selectedRoles);

    const tournamentId = getTournamentId();
    if (!tournamentId) {
        console.error('Cannot add staff: tournament ID not available');
        return;
    }

    // Show loading state
    const addButton = document.getElementById('addStaffBtn');
    const originalText = addButton.textContent;
    addButton.disabled = true;
    addButton.textContent = 'Adding...';

    fetch(`/admin/tournaments/${tournamentId}/staff`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            user_id: selectedUserId,
            roles: roles,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            addButton.disabled = false;
            addButton.textContent = originalText;
        } else {
            // Success: Close modal and refresh staff list
            closeStaffModal();
            refreshStaffList();
            showSuccessMessage('Staff added successfully');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add staff');
        addButton.disabled = false;
        addButton.textContent = originalText;
    });
}

// NEW: Helper function to refresh staff list via AJAX
// Make it global so it can be called from inline scripts
window.refreshStaffList = function() {
    const tournamentId = getTournamentId();
    const staffListContainer = document.querySelector('[data-tournament-staff-list]');

    if (!staffListContainer) {
        console.error('Staff list container not found');
        return;
    }

    // Track currently expanded user cards before refresh
    const expandedUserIds = [];
    document.querySelectorAll('[data-user-id]').forEach(card => {
        const expandIcon = card.querySelector('[class*="rotate-180"]');
        if (expandIcon || card.querySelector('[x-show="expanded"]')?.style.display !== 'none') {
            expandedUserIds.push(card.getAttribute('data-user-id'));
        }
    });

    // Fetch the staff component HTML
    fetch(`/admin/tournaments/${tournamentId}/staff/component`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // Create a temporary div to parse the response
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            // Extract the staff list from the response
            const newStaffList = tempDiv.querySelector('[data-tournament-staff-list]');
            if (newStaffList) {
                staffListContainer.innerHTML = newStaffList.innerHTML;

                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(staffListContainer);
                }

                // Restore expanded state after DOM update
                if (expandedUserIds.length > 0) {
                    // Wait for Alpine.js to initialize, then expand
                    setTimeout(() => {
                        expandedUserIds.forEach(userId => {
                            const card = document.querySelector(`[data-user-id="${userId}"]`);
                            if (card) {
                                const rolesDiv = card.querySelector(`#userRoles-${userId}`);
                                const icon = card.querySelector(`#expandIcon-${userId}`);
                                if (rolesDiv && icon) {
                                    rolesDiv.style.display = 'block';
                                    rolesDiv.classList.remove('hidden');
                                    icon.classList.add('rotate-180');
                                }
                            }
                        });
                    }, 50);
                }
            } else {
                console.error('Staff list not found in response');
            }
        })
        .catch(error => {
            console.error('Error refreshing staff list:', error);
        });
};

// NEW: Show success toast message
window.showSuccessMessage = function(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[100] flex items-center space-x-2 animate-fade-in';
    toast.innerHTML = `
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-xs font-bold">OK</span>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
