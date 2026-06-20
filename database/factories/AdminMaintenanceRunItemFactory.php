<?php

namespace Database\Factories;

use App\Models\AdminMaintenanceRun;
use App\Models\AdminMaintenanceRunItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminMaintenanceRunItem>
 */
class AdminMaintenanceRunItemFactory extends Factory
{
    protected $model = AdminMaintenanceRunItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_maintenance_run_id' => AdminMaintenanceRun::factory(),
            'item_type' => AdminMaintenanceRunItem::TYPE_TOURNAMENT,
            'action' => AdminMaintenanceRunItem::ACTION_SYNCED,
            'status' => AdminMaintenanceRunItem::STATUS_COMPLETED,
            'tournament_title' => fake()->sentence(3),
            'metadata' => [],
        ];
    }
}
