<?php

namespace App\Domain\Tournaments\Staff;

class StaffRoleOrder
{
    /**
     * @var array<string, int>
     */
    public const PRIORITIES = [
        'organizer' => 1,
        'mappooler' => 2,
        'playtester' => 3,
        'mapper' => 4,
        'gfx' => 5,
        'sheeter' => 6,
        'referee' => 7,
        'streamer' => 8,
        'commentator' => 9,
        'other' => 10,
    ];

    public static function priority(string $role): int
    {
        return self::PRIORITIES[$role] ?? 999;
    }

    public static function sql(string $qualifiedColumn = 'tournament_staff.role'): string
    {
        $cases = collect(self::PRIORITIES)
            ->map(
                fn (int $priority, string $role): string => sprintf(
                    "WHEN %s = '%s' THEN %d",
                    $qualifiedColumn,
                    str_replace("'", "''", $role),
                    $priority,
                )
            )
            ->implode(' ');

        return "CASE {$cases} ELSE 999 END";
    }
}
