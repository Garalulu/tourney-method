<?php

use App\Models\DiscordChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Mapping from old format rank ranges to new threshold values.
     *
     * @var array<string, int>
     */
    private array $rankRangeToThreshold = [
        '1-999' => 1,
        '1000-4999' => 1000,
        '5000-9999' => 5000,
        '10000-49999' => 10000,
        '50000+' => 50000,
    ];

    /**
     * Run the migrations.
     * Converts old role_mappings format to new threshold format.
     *
     * Old format: {"1-999": "123", "50000+": "456", ...}
     * New format: [{"threshold": 1, "role_id": "123"}, {"threshold": 50000, "role_id": "456"}, ...]
     */
    public function up(): void
    {
        $channels = DiscordChannel::all();

        foreach ($channels as $channel) {
            if (empty($channel->role_mappings)) {
                continue;
            }

            $mappings = $channel->role_mappings;

            // Skip if already in new format (has threshold key)
            if (is_array($mappings) && isset($mappings[0]['threshold'])) {
                Log::info('Skipping channel - already in new format', [
                    'channel' => $channel->channel_name,
                ]);

                continue;
            }

            // Convert old format to new format
            $newMappings = $this->convertToNewFormat($mappings);

            if (! empty($newMappings)) {
                $channel->role_mappings = $newMappings;
                $channel->save();

                Log::info('Converted role mappings', [
                    'channel' => $channel->channel_name,
                    'old_count' => count($mappings),
                    'new_count' => count($newMappings),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     * Converts new threshold format back to old rank range format.
     */
    public function down(): void
    {
        $channels = DiscordChannel::all();

        foreach ($channels as $channel) {
            if (empty($channel->role_mappings)) {
                continue;
            }

            $mappings = $channel->role_mappings;

            // Skip if not in new format
            if (! is_array($mappings) || ! isset($mappings[0]['threshold'])) {
                continue;
            }

            // Convert new format back to old format
            $oldMappings = $this->convertToOldFormat($mappings);

            $channel->role_mappings = $oldMappings;
            $channel->save();

            Log::info('Reverted role mappings', [
                'channel' => $channel->channel_name,
            ]);
        }
    }

    /**
     * Convert old rank range format to new threshold format.
     *
     * @param  array<string, string|null>  $oldFormat
     * @return array<int, array{threshold: int, role_id: string}>
     */
    private function convertToNewFormat(array $oldFormat): array
    {
        $newFormat = [];

        foreach ($this->rankRangeToThreshold as $rankRange => $threshold) {
            $roleId = $oldFormat[$rankRange] ?? null;

            // Skip if no role ID set
            if ($roleId === null || $roleId === '') {
                continue;
            }

            $newFormat[] = [
                'threshold' => $threshold,
                'role_id' => $roleId,
            ];
        }

        // Sort by threshold ascending
        usort($newFormat, fn ($a, $b) => $a['threshold'] <=> $b['threshold']);

        return $newFormat;
    }

    /**
     * Convert new threshold format back to old rank range format.
     *
     * @param  array<int, array{threshold: int, role_id: string}>  $newFormat
     * @return array<string, string|null>
     */
    private function convertToOldFormat(array $newFormat): array
    {
        $oldFormat = [];

        // Build reverse lookup
        $thresholdToRange = array_flip($this->rankRangeToThreshold);

        foreach ($newFormat as $mapping) {
            $threshold = $mapping['threshold'];
            $roleId = $mapping['role_id'];

            if (! isset($thresholdToRange[$threshold])) {
                Log::warning('Unknown threshold in new format', [
                    'threshold' => $threshold,
                ]);

                continue;
            }

            $rankRange = $thresholdToRange[$threshold];
            $oldFormat[$rankRange] = $roleId;
        }

        // Ensure all old keys exist with null if missing
        foreach (array_keys($this->rankRangeToThreshold) as $rankRange) {
            if (! isset($oldFormat[$rankRange])) {
                $oldFormat[$rankRange] = null;
            }
        }

        return $oldFormat;
    }
};
