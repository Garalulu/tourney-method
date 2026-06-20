<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use App\Models\OsuMatch;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminAuditLogPresenter
{
    /**
     * @var array<string, array{label: string, aliases: list<string>}>
     */
    private const ENTITY_TYPES = [
        'tournament' => [
            'label' => 'Tournament',
            'aliases' => [Tournament::class, 'Tournament', 'tournament', '0'],
        ],
        'staff' => [
            'label' => 'Staff',
            'aliases' => [TournamentStaff::class, 'TournamentStaff', 'staff', '1'],
        ],
        'user' => [
            'label' => 'User',
            'aliases' => [User::class, 'User', 'user', '2'],
        ],
        'match' => [
            'label' => 'Match',
            'aliases' => [OsuMatch::class, 'OsuMatch', 'Match', 'match', '3'],
        ],
        'discord_destination' => [
            'label' => 'Discord Destination',
            'aliases' => [DiscordChannel::class, 'DiscordChannel', 'discord_destination'],
        ],
        'discord_server' => [
            'label' => 'Discord Server',
            'aliases' => [DiscordServer::class, 'DiscordServer', 'discord_server'],
        ],
    ];

    private const ACTION_LABELS = [
        'tournament.created' => 'Tournament Created',
        'tournament.updated' => 'Tournament Updated',
        'tournament.approved' => 'Tournament Approved',
        'tournament.rejected' => 'Tournament Rejected',
        'tournament.deleted' => 'Tournament Deleted',
        'tournament.restored' => 'Tournament Restored',
        'tournament.copied' => 'Tournament Copied',
        'tournament.reparsed' => 'Tournament Reparsed',
        'tournament.correction_reviewed' => 'Tournament Correction Reviewed',
        'tournament.reparse_resolved' => 'Reparse Conflicts Resolved',
        'tournament.parse_history_deleted' => 'Parse History Deleted',
        'tournament.banner_refreshed' => 'Banner Refreshed',
        'tournament.staff_added' => 'Staff Added',
        'tournament.staff_bulk_added' => 'Bulk Staff Added',
        'tournament.staff_role_updated' => 'Staff Role Updated',
        'tournament.staff_roles_replaced' => 'Staff Roles Replaced',
        'tournament.staff_removed' => 'Staff Removed',
        'tournament.staff_role_bulk_removed' => 'Staff Role Bulk Removed',
        'tournament.podium_winner_added' => 'Podium Winner Added',
        'tournament.podium_winners_bulk_added' => 'Bulk Podium Winners Added',
        'tournament.podium_winner_updated' => 'Podium Winner Updated',
        'tournament.podium_winner_removed' => 'Podium Winner Removed',
        'tournament.badge_url_added' => 'Badge URL Added',
        'tournament.badge_url_removed' => 'Badge URL Removed',
        'tournament.host_changed' => 'Host Changed',
        'staff.approve' => 'Staff Approved',
        'staff.reject' => 'Staff Rejected',
        'match.approve' => 'Match Approved',
        'match.reject' => 'Match Rejected',
        'match.edit' => 'Match Edited',
        'role_updated' => 'User Role Updated',
        'user_sync' => 'User Synced',
        'discord_destination.created' => 'Discord Destination Created',
        'discord_destination.updated' => 'Discord Destination Updated',
        'discord_destination.deleted' => 'Discord Destination Deleted',
        'discord_destination.tested' => 'Discord Destination Tested',
        'discord_server.created' => 'Discord Server Created',
        'discord_server.updated' => 'Discord Server Updated',
        'discord_server.deleted' => 'Discord Server Deleted',
        '0' => 'Verification',
        '1' => 'Rejection',
        '2' => 'Update',
    ];

    private const ACTION_ALIASES = [
        '0' => ['tournament.approved', 'staff.approve', 'match.approve'],
        '1' => ['tournament.rejected', 'staff.reject', 'match.reject'],
        '2' => ['tournament.updated', 'match.edit', 'role_updated', 'tournament.staff_role_updated', 'tournament.staff_roles_replaced'],
    ];

    /**
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @return Collection<int, array<string, mixed>>
     */
    public static function presentMany(EloquentCollection|Collection $logs): Collection
    {
        $tournaments = self::loadTournamentTargets($logs);
        $staff = self::loadStaffTargets($logs);
        $users = self::loadUserTargets($logs);
        $matches = self::loadMatchTargets($logs);

        return collect($logs)->map(fn (AdminAuditLog $log): array => self::present(
            log: $log,
            tournaments: $tournaments,
            staff: $staff,
            users: $users,
            matches: $matches,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function entityOptions(): array
    {
        return collect(self::ENTITY_TYPES)
            ->mapWithKeys(fn (array $config, string $key): array => [$key => $config['label']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        $options = [];

        foreach (self::ACTION_LABELS as $value => $label) {
            $options[(string) $value] = $label;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function entityAliases(string $entityType): array
    {
        $canonical = self::canonicalEntityType($entityType);

        if ($canonical && isset(self::ENTITY_TYPES[$canonical])) {
            return self::ENTITY_TYPES[$canonical]['aliases'];
        }

        return [$entityType];
    }

    /**
     * @return list<string>
     */
    public static function actionAliases(string $action): array
    {
        return self::ACTION_ALIASES[$action] ?? [$action];
    }

    public static function entityLabel(string $entityType): string
    {
        $canonical = self::canonicalEntityType($entityType);

        if ($canonical && isset(self::ENTITY_TYPES[$canonical])) {
            return self::ENTITY_TYPES[$canonical]['label'];
        }

        return Str::headline(class_basename($entityType));
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::headline(str_replace(['.', '_'], ' ', $action));
    }

    public static function canonicalEntityType(string $entityType): ?string
    {
        foreach (self::ENTITY_TYPES as $key => $config) {
            if (in_array($entityType, $config['aliases'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int|string, Tournament>  $tournaments
     * @param  Collection<int|string, TournamentStaff>  $staff
     * @param  Collection<int|string, User>  $users
     * @param  Collection<int|string, OsuMatch>  $matches
     * @return array<string, mixed>
     */
    private static function present(
        AdminAuditLog $log,
        Collection $tournaments,
        Collection $staff,
        Collection $users,
        Collection $matches
    ): array {
        $details = $log->details ?? [];
        $target = self::target($log, $details, $tournaments, $staff, $users, $matches);
        $changes = self::changes($log);
        $bulkItems = self::bulkItems($log);
        $detailRows = self::detailRows($log, $changes, $bulkItems);

        return [
            'id' => $log->id,
            'admin' => $log->admin,
            'action_label' => self::actionLabel($log->action),
            'action_code' => $log->action,
            'entity_label' => self::entityLabel($log->entity_type),
            'entity_code' => $log->entity_type,
            'target' => $target,
            'changes' => $changes,
            'bulk_items' => $bulkItems,
            'detail_rows' => $detailRows,
            'details' => $details,
            'created_at' => $log->created_at,
            'ip_address' => $log->ip_address,
        ];
    }

    /**
     * @param  Collection<int|string, Tournament>  $tournaments
     * @param  Collection<int|string, TournamentStaff>  $staff
     * @param  Collection<int|string, User>  $users
     * @param  Collection<int|string, OsuMatch>  $matches
     * @param  array<string, mixed>  $details
     * @param  Collection<int|string, TournamentStaff>  $staff
     * @param  Collection<int|string, OsuMatch>  $matches
     * @return array{label: string, sublabel: string|null, url: string|null, missing: bool}
     */
    private static function target(
        AdminAuditLog $log,
        array $details,
        Collection $tournaments,
        Collection $staff,
        Collection $users,
        Collection $matches
    ): array {
        $tournamentId = self::tournamentId($log, $details, $staff, $matches);

        if ($tournamentId !== null) {
            /** @var Tournament|null $tournament */
            $tournament = $tournaments->get($tournamentId);

            return [
                'label' => $tournament ? "{$tournament->title} #{$tournament->id}" : "Deleted Tournament #{$tournamentId}",
                'sublabel' => 'Tournament',
                'url' => $tournament ? route('admin.tournaments.show', $tournament) : null,
                'missing' => $tournament === null,
            ];
        }

        $canonical = self::canonicalEntityType($log->entity_type);
        if ($canonical === 'user') {
            /** @var User|null $user */
            $user = $users->get($log->entity_id);

            return [
                'label' => $user ? "{$user->username} #{$user->id}" : "Deleted User #{$log->entity_id}",
                'sublabel' => 'User',
                'url' => null,
                'missing' => $user === null,
            ];
        }

        if ($canonical === 'match') {
            /** @var OsuMatch|null $match */
            $match = $matches->get($log->entity_id);

            return [
                'label' => $match ? "{$match->name} #{$match->id}" : "Deleted Match #{$log->entity_id}",
                'sublabel' => 'Match',
                'url' => null,
                'missing' => $match === null,
            ];
        }

        return [
            'label' => self::entityLabel($log->entity_type)." #{$log->entity_id}",
            'sublabel' => self::entityLabel($log->entity_type),
            'url' => null,
            'missing' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  Collection<int|string, TournamentStaff>  $staff
     * @param  Collection<int|string, OsuMatch>  $matches
     */
    private static function tournamentId(AdminAuditLog $log, array $details, Collection $staff, Collection $matches): ?int
    {
        $canonical = self::canonicalEntityType($log->entity_type);

        if ($canonical === 'tournament') {
            return $log->entity_id;
        }

        if (isset($details['tournament_id']) && is_numeric($details['tournament_id'])) {
            return (int) $details['tournament_id'];
        }

        if ($canonical === 'staff') {
            /** @var TournamentStaff|null $staffRole */
            $staffRole = $staff->get($log->entity_id);

            return $staffRole?->tournament_id;
        }

        if ($canonical === 'match') {
            /** @var OsuMatch|null $match */
            $match = $matches->get($log->entity_id);

            return $match?->tournament_id;
        }

        return null;
    }

    /**
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private static function changes(AdminAuditLog $log): array
    {
        $details = $log->details ?? [];
        $changes = $details['changes'] ?? null;

        if (! in_array($log->action, ['tournament.updated', '2'], true) || ! is_array($changes)) {
            return [];
        }

        $rows = [];
        foreach ($changes as $field => $value) {
            if (! is_array($value) || ! array_key_exists('old', $value) || ! array_key_exists('new', $value)) {
                $rows[] = [
                    'field' => (string) $field,
                    'before' => null,
                    'after' => $value,
                ];

                continue;
            }

            $rows[] = [
                'field' => (string) $field,
                'before' => $value['old'],
                'after' => $value['new'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{requested: list<string>, successes: list<array<string, mixed>>, failures: list<array<string, mixed>>, total: int|null, success_count: int|null, failed_count: int|null}
     */
    private static function bulkItems(AdminAuditLog $log): array
    {
        $details = $log->details ?? [];

        if (! in_array($log->action, ['tournament.staff_bulk_added', 'tournament.podium_winners_bulk_added'], true)) {
            return [
                'requested' => [],
                'successes' => [],
                'failures' => [],
                'total' => null,
                'success_count' => null,
                'failed_count' => null,
            ];
        }

        return [
            'requested' => array_values(array_filter($details['requested_usernames'] ?? [], 'is_string')),
            'successes' => array_values(array_filter($details['successes'] ?? [], 'is_array')),
            'failures' => array_values(array_filter($details['failures'] ?? [], 'is_array')),
            'total' => isset($details['total_count']) ? (int) $details['total_count'] : null,
            'success_count' => isset($details['success_count']) ? (int) $details['success_count'] : null,
            'failed_count' => isset($details['failed_count']) ? (int) $details['failed_count'] : null,
        ];
    }

    /**
     * @param  list<array{field: string, before: mixed, after: mixed}>  $changes
     * @param  array<string, mixed>  $bulkItems
     * @return list<array{label: string, value: string}>
     */
    private static function detailRows(AdminAuditLog $log, array $changes, array $bulkItems): array
    {
        $details = $log->details ?? [];

        if ($changes !== [] || $bulkItems['successes'] !== [] || $bulkItems['failures'] !== []) {
            return [];
        }

        $preferred = [
            'role',
            'roles',
            'old_role',
            'new_role',
            'placement',
            'username',
            'rejection_reason',
            'reason',
            'deleted_count',
            'operation_id',
            'transaction_id',
            'correction_id',
            'status',
        ];

        $rows = [];
        foreach ($preferred as $key) {
            if (array_key_exists($key, $details)) {
                $rows[] = [
                    'label' => Str::headline($key),
                    'value' => self::formatValue($details[$key]),
                ];
            }
        }

        return $rows;
    }

    public static function fieldLabel(string $field): string
    {
        return Str::headline($field);
    }

    public static function formatValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return 'Empty';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        return (string) $value;
    }

    /**
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @return Collection<int|string, Tournament>
     */
    private static function loadTournamentTargets(EloquentCollection|Collection $logs): Collection
    {
        /** @var Collection<int, int> $ids */
        $ids = collect();
        /** @var Collection<int, int> $staffIds */
        $staffIds = collect();
        /** @var Collection<int, int> $matchIds */
        $matchIds = collect();

        foreach ($logs as $log) {
            $details = $log->details ?? [];
            $canonical = self::canonicalEntityType($log->entity_type);

            if ($canonical === 'tournament') {
                $ids->push($log->entity_id);
            }

            if (isset($details['tournament_id']) && is_numeric($details['tournament_id'])) {
                $ids->push((int) $details['tournament_id']);
            }

            if ($canonical === 'staff') {
                $staffIds->push($log->entity_id);
            }

            if ($canonical === 'match') {
                $matchIds->push($log->entity_id);
            }
        }

        if ($staffIds->isNotEmpty()) {
            TournamentStaff::query()
                ->whereIn('id', $staffIds->unique()->values()->all())
                ->pluck('tournament_id')
                ->each(fn (int $id) => $ids->push($id));
        }

        if ($matchIds->isNotEmpty()) {
            OsuMatch::query()
                ->whereIn('id', $matchIds->unique()->values()->all())
                ->whereNotNull('tournament_id')
                ->pluck('tournament_id')
                ->each(fn (int $id) => $ids->push($id));
        }

        return Tournament::query()
            ->whereIn('id', $ids->unique()->values()->all())
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @return Collection<int|string, TournamentStaff>
     */
    private static function loadStaffTargets(EloquentCollection|Collection $logs): Collection
    {
        return self::loadTargets($logs, 'staff', TournamentStaff::class);
    }

    /**
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @return Collection<int|string, User>
     */
    private static function loadUserTargets(EloquentCollection|Collection $logs): Collection
    {
        return self::loadTargets($logs, 'user', User::class);
    }

    /**
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @return Collection<int|string, OsuMatch>
     */
    private static function loadMatchTargets(EloquentCollection|Collection $logs): Collection
    {
        return self::loadTargets($logs, 'match', OsuMatch::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentCollection<int, AdminAuditLog>|Collection<int, AdminAuditLog>  $logs
     * @param  class-string<TModel>  $model
     * @return Collection<int|string, TModel>
     */
    private static function loadTargets(EloquentCollection|Collection $logs, string $canonical, string $model): Collection
    {
        $ids = collect($logs)
            ->filter(fn (AdminAuditLog $log): bool => self::canonicalEntityType($log->entity_type) === $canonical)
            ->pluck('entity_id')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        /** @var Collection<int|string, TModel> $targets */
        $targets = $model::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $targets;
    }
}
