<?php

namespace Database\Factories;

use App\Models\AdminMaintenanceRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminMaintenanceRun>
 */
class AdminMaintenanceRunFactory extends Factory
{
    protected $model = AdminMaintenanceRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $command = fake()->randomElement([
            AdminMaintenanceRun::COMMAND_TOURNAMENTS_PARSE,
            AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT,
            AdminMaintenanceRun::COMMAND_SYNC_USER_PROFILES,
        ]);

        return [
            'command' => $command,
            'label' => $command === AdminMaintenanceRun::COMMAND_SYNC_TOURNAMENT ? 'sync:tournaments' : $command,
            'source' => 'cli',
            'status' => AdminMaintenanceRun::STATUS_COMPLETED,
            'options' => [],
            'summary' => [],
            'errors' => [],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }
}
