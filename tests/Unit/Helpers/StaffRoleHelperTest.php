<?php

use App\Helpers\StaffRoleHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Helper is stateless, no setup needed
});

test('get role color returns correct color classes for organizer', function () {
    $color = StaffRoleHelper::getRoleColor('organizer');

    expect($color)->toBe([
        'bg' => 'bg-purple-500/20',
        'text' => 'text-purple-400',
        'border' => 'border-purple-500/30',
    ]);
});

test('get role color returns correct color classes for all roles', function () {
    $roles = [
        'organizer' => 'purple',
        'mappooler' => 'blue',
        'playtester' => 'green',
        'mapper' => 'indigo',
        'gfx' => 'pink',
        'sheeter' => 'orange',
        'referee' => 'red',
        'streamer' => 'cyan',
        'commentator' => 'yellow',
        'other' => 'gray',
    ];

    foreach ($roles as $role => $colorBase) {
        $color = StaffRoleHelper::getRoleColor($role);

        expect($color['bg'])->toContain($colorBase);
        expect($color['text'])->toContain($colorBase);
        expect($color['border'])->toContain($colorBase);
    }
});

test('get role priority returns correct order numbers', function () {
    expect(StaffRoleHelper::getRolePriority('organizer'))->toBe(1);
    expect(StaffRoleHelper::getRolePriority('mappooler'))->toBe(2);
    expect(StaffRoleHelper::getRolePriority('playtester'))->toBe(3);
    expect(StaffRoleHelper::getRolePriority('mapper'))->toBe(4);
    expect(StaffRoleHelper::getRolePriority('gfx'))->toBe(5);
    expect(StaffRoleHelper::getRolePriority('sheeter'))->toBe(6);
    expect(StaffRoleHelper::getRolePriority('referee'))->toBe(7);
    expect(StaffRoleHelper::getRolePriority('streamer'))->toBe(8);
    expect(StaffRoleHelper::getRolePriority('commentator'))->toBe(9);
    expect(StaffRoleHelper::getRolePriority('other'))->toBe(10);
});

test('get role label returns formatted label', function () {
    expect(StaffRoleHelper::getRoleLabel('organizer'))->toBe('Organizer');
    expect(StaffRoleHelper::getRoleLabel('mappooler'))->toBe('Mappooler');
    expect(StaffRoleHelper::getRoleLabel('other'))->toBe('Other');
});

test('sort staff by role orders by priority correctly', function () {
    $staff = [
        ['username' => 'user1', 'role' => 'commentator'],
        ['username' => 'user2', 'role' => 'organizer'],
        ['username' => 'user3', 'role' => 'mapper'],
        ['username' => 'user4', 'role' => 'other'],
    ];

    $sorted = StaffRoleHelper::sortStaffByRole($staff);

    expect($sorted[0]['role'])->toBe('organizer');
    expect($sorted[1]['role'])->toBe('mapper');
    expect($sorted[2]['role'])->toBe('commentator');
    expect($sorted[3]['role'])->toBe('other');
});

test('sort staff by role preserves original order within same role', function () {
    $staff = [
        ['username' => 'user1', 'role' => 'mapper'],
        ['username' => 'user2', 'role' => 'mapper'],
        ['username' => 'user3', 'role' => 'organizer'],
    ];

    $sorted = StaffRoleHelper::sortStaffByRole($staff);

    expect($sorted[0]['role'])->toBe('organizer');
    expect($sorted[1]['username'])->toBe('user1');
    expect($sorted[2]['username'])->toBe('user2');
});

test('get available roles returns all roles with priorities', function () {
    $roles = StaffRoleHelper::getAvailableRoles();

    expect($roles)->toHaveCount(10);
    expect($roles['organizer'])->toBe(1);
    expect($roles['other'])->toBe(10);
});

test('sort staff by role handles empty array', function () {
    $sorted = StaffRoleHelper::sortStaffByRole([]);

    expect($sorted)->toBeEmpty();
});

test('get role color handles unknown role gracefully', function () {
    $color = StaffRoleHelper::getRoleColor('unknown_role');

    // Should return default 'other' color
    expect($color['bg'])->toBe('bg-gray-500/20');
    expect($color['text'])->toBe('text-gray-400');
});

test('get role priority handles unknown role gracefully', function () {
    $priority = StaffRoleHelper::getRolePriority('unknown_role');

    // Should return lowest priority
    expect($priority)->toBe(999);
});
