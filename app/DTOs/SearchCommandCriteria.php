<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class SearchCommandCriteria
{
    /**
     * @param  array<int, string>  $countries
     */
    public function __construct(
        public string $keyword = '',
        public ?int $rank = null,
        public ?float $starRating = null,
        public array $countries = [],
        public ?string $staffUsername = null,
        public ?string $staffRole = null,
        public ?string $podiumUsername = null,
        public ?int $podiumPlacement = null,
        public ?string $formatTag = null,
    ) {}

    public function hasCommands(): bool
    {
        return $this->rank !== null
            || $this->starRating !== null
            || $this->countries !== []
            || $this->staffUsername !== null
            || $this->podiumUsername !== null
            || $this->formatTag !== null;
    }
}
