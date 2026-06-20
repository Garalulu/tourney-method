<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * SearchQuery
 *
 * Data Transfer Object for search filters.
 * Encapsulates all filter criteria for tournament search.
 */
final readonly class SearchQuery
{
    /**
     * Create a new SearchQuery instance.
     *
     * @param  string  $mode  Game mode filter ('osu', 'taiko', 'catch', 'mania', or '' for all)
     * @param  bool  $badgeOnly  Only show tournaments with badges
     * @param  bool  $regOpenOnly  Only show tournaments with open registration
     * @param  bool  $eligibleOnly  Only show tournaments user is eligible for
     * @param  string  $tab  Tab filter ('active', 'ended', 'all')
     * @param  int|null  $selectedYear  Year filter (e.g., 2023 or null for all years)
     */
    public function __construct(
        public string $mode = '',
        public bool $badgeOnly = false,
        public bool $regOpenOnly = false,
        public bool $eligibleOnly = false,
        public string $tab = 'active',
        public ?int $selectedYear = null,
        public ?SearchCommandCriteria $commands = null,
    ) {}

    /**
     * Create SearchQuery from array data.
     *
     * Useful for creating from Livewire component properties.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mode: $data['mode'] ?? '',
            badgeOnly: (bool) ($data['badgeOnly'] ?? false),
            regOpenOnly: (bool) ($data['regOpenOnly'] ?? false),
            eligibleOnly: (bool) ($data['eligibleOnly'] ?? false),
            tab: $data['tab'] ?? 'active',
            selectedYear: isset($data['selectedYear']) && $data['selectedYear'] !== '' ? (int) $data['selectedYear'] : null,
            commands: $data['commands'] ?? null,
        );
    }

    /**
     * Check if any filters are active.
     *
     * Excludes tab filter from this check.
     */
    public function hasFilters(): bool
    {
        return $this->mode !== ''
            || $this->badgeOnly
            || $this->regOpenOnly
            || $this->eligibleOnly
            || $this->commandCriteria()->hasCommands();
    }

    public function commandCriteria(): SearchCommandCriteria
    {
        return $this->commands ?? new SearchCommandCriteria;
    }

    /**
     * Check if search should be restricted to active tournaments only.
     */
    public function isActiveTabOnly(): bool
    {
        return $this->tab === 'active';
    }

    /**
     * Check if search should be restricted to ended tournaments only.
     */
    public function isEndedTabOnly(): bool
    {
        return $this->tab === 'ended';
    }

    /**
     * Check if search should include all tournaments (no tab restriction).
     */
    public function isAllTabs(): bool
    {
        return $this->tab === 'all' || $this->tab === '';
    }

    /**
     * Get the tab value (active, ended, or all).
     */
    public function getTab(): string
    {
        return $this->tab === '' ? 'all' : $this->tab;
    }
}
