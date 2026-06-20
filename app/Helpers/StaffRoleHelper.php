<?php

namespace App\Helpers;

use App\Domain\Tournaments\Staff\StaffRoleOrder;

class StaffRoleHelper
{
    /**
     * Role priority order for sorting
     */
    /**
     * Role-specific Tailwind CSS color classes
     */
    private const ROLE_COLORS = [
        'organizer' => [
            'bg' => 'bg-purple-500/20',
            'text' => 'text-purple-400',
            'border' => 'border-purple-500/30',
        ],
        'mappooler' => [
            'bg' => 'bg-blue-500/20',
            'text' => 'text-blue-400',
            'border' => 'border-blue-500/30',
        ],
        'playtester' => [
            'bg' => 'bg-green-500/20',
            'text' => 'text-green-400',
            'border' => 'border-green-500/30',
        ],
        'mapper' => [
            'bg' => 'bg-indigo-500/20',
            'text' => 'text-indigo-400',
            'border' => 'border-indigo-500/30',
        ],
        'gfx' => [
            'bg' => 'bg-pink-500/20',
            'text' => 'text-pink-400',
            'border' => 'border-pink-500/30',
        ],
        'sheeter' => [
            'bg' => 'bg-orange-500/20',
            'text' => 'text-orange-400',
            'border' => 'border-orange-500/30',
        ],
        'referee' => [
            'bg' => 'bg-red-500/20',
            'text' => 'text-red-400',
            'border' => 'border-red-500/30',
        ],
        'streamer' => [
            'bg' => 'bg-cyan-500/20',
            'text' => 'text-cyan-400',
            'border' => 'border-cyan-500/30',
        ],
        'commentator' => [
            'bg' => 'bg-yellow-500/20',
            'text' => 'text-yellow-400',
            'border' => 'border-yellow-500/30',
        ],
        'other' => [
            'bg' => 'bg-gray-500/20',
            'text' => 'text-gray-400',
            'border' => 'border-gray-500/30',
        ],
    ];

    /**
     * Get color classes for a specific role
     *
     * @return array{bg: string, text: string, border: string}
     */
    public static function getRoleColor(string $role): array
    {
        return self::ROLE_COLORS[$role] ?? self::ROLE_COLORS['other'];
    }

    /**
     * Get priority number for sorting (lower = higher priority)
     */
    public static function getRolePriority(string $role): int
    {
        return StaffRoleOrder::priority($role);
    }

    /**
     * Get formatted role label
     */
    public static function getRoleLabel(string $role): string
    {
        return ucfirst($role);
    }

    /**
     * Sort staff array by role priority
     *
     * @param  array<int, array{username: string, role: string}>  $staff
     * @return array<int, array{username: string, role: string}>
     */
    public static function sortStaffByRole(array $staff): array
    {
        if (empty($staff)) {
            return [];
        }

        usort($staff, function ($a, $b) {
            $priorityA = self::getRolePriority($a['role']);
            $priorityB = self::getRolePriority($b['role']);

            return $priorityA <=> $priorityB;
        });

        return $staff;
    }

    /**
     * Sort role names by priority.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public static function sortRoles(array $roles): array
    {
        $roles = array_values($roles);

        usort($roles, fn (string $a, string $b): int => self::getRolePriority($a) <=> self::getRolePriority($b));

        return $roles;
    }

    /**
     * Get all available roles with their priorities
     *
     * @return array<string, int>
     */
    public static function getAvailableRoles(): array
    {
        return StaffRoleOrder::PRIORITIES;
    }

    /**
     * Build a portable SQL CASE expression for role-priority ordering.
     */
    public static function roleOrderSql(string $qualifiedColumn = 'tournament_staff.role'): string
    {
        return StaffRoleOrder::sql($qualifiedColumn);
    }
}
