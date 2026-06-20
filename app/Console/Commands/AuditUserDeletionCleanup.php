<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditUserDeletionCleanup extends Command
{
    protected $signature = 'users:deletion-cleanup-audit
                            {--user-id= : Limit counts to a single user id}
                            {--limit=20 : Number of sample rows to show per table}';

    protected $description = 'Count tournament rows that still reference soft-deleted or missing users';

    public function handle(): int
    {
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $limit = max(0, (int) $this->option('limit'));

        $this->warn('Audit only - no rows will be modified.');
        $this->newLine();

        $tables = [
            'tournament_participation_records' => ['table' => 'tournament_participation_records', 'user_column' => 'user_id'],
            'participation_record_teammates' => ['table' => 'participation_record_teammates', 'user_column' => 'user_id'],
            'tournament_winners' => ['table' => 'tournament_winners', 'user_column' => 'user_id'],
            'tournament_staff' => ['table' => 'tournament_staff', 'user_column' => 'user_id'],
        ];

        $rows = [];

        foreach ($tables as $label => $config) {
            $query = $this->brokenReferenceQuery($config['table'], $config['user_column'], $userId);
            $count = (clone $query)->count();
            $rows[] = [$label, $count];
        }

        $this->table(['Table', 'Broken references'], $rows);

        if ($limit === 0) {
            return self::SUCCESS;
        }

        foreach ($tables as $label => $config) {
            $samples = $this->brokenReferenceQuery($config['table'], $config['user_column'], $userId)
                ->select([
                    "{$config['table']}.id",
                    "{$config['table']}.{$config['user_column']} as user_id",
                    'users.username',
                    'users.deleted_at',
                ])
                ->orderBy("{$config['table']}.id")
                ->limit($limit)
                ->get()
                ->map(fn ($row): array => [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'username' => $row->username ?? '(missing)',
                    'deleted_at' => $row->deleted_at ?? '(missing)',
                ])
                ->all();

            if ($samples === []) {
                continue;
            }

            $this->newLine();
            $this->info($label.' samples');
            $this->table(['Row ID', 'User ID', 'Username', 'Deleted At'], $samples);
        }

        return self::SUCCESS;
    }

    private function brokenReferenceQuery(string $table, string $userColumn, ?int $userId): Builder
    {
        return DB::table($table)
            ->leftJoin('users', "{$table}.{$userColumn}", '=', 'users.id')
            ->whereNotNull("{$table}.{$userColumn}")
            ->where(function ($query): void {
                $query->whereNull('users.id')
                    ->orWhereNotNull('users.deleted_at');
            })
            ->when($userId !== null, fn ($query) => $query->where("{$table}.{$userColumn}", $userId));
    }
}
