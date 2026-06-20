<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\SearchCommandCriteria;
use App\Helpers\StaffRoleHelper;

final class SearchCommandParser
{
    /**
     * Parse fixed English search commands and return the remaining keyword.
     */
    public function parse(string $query): SearchCommandCriteria
    {
        $rank = null;
        $starRating = null;
        $countries = [];
        $staffUsername = null;
        $staffRole = null;
        $podiumUsername = null;
        $podiumPlacement = null;
        $formatTag = null;

        $keyword = (string) preg_replace_callback('/(?<!\S)rank:(\d+)(?=\s|$)/', function (array $matches) use (&$rank): string {
            $rank = (int) $matches[1];

            return ' ';
        }, $query);

        $keyword = (string) preg_replace_callback('/(?<!\S)sr:(\d+(?:\.\d+)?)(?=\s|$)/', function (array $matches) use (&$starRating): string {
            $starRating = (float) $matches[1];

            return ' ';
        }, $keyword);

        $keyword = (string) preg_replace_callback('/(?<!\S)(?:country|countries):([A-Za-z]{2}(?:,[A-Za-z]{2})*)(?=\s|$)/', function (array $matches) use (&$countries): string {
            $parsedCountries = array_map(
                fn (string $country): string => strtoupper($country),
                explode(',', $matches[1])
            );

            $countries = array_merge($countries, $parsedCountries);

            return ' ';
        }, $keyword);

        $keyword = (string) preg_replace_callback('/(?<!\S)staff:(?:"([^"]+)"|([^@\s]+))(?:@([A-Za-z]+))?(?=\s|$)/', function (array $matches) use (&$staffUsername, &$staffRole): string {
            $role = $matches[3] ?? null;

            if ($role !== null && ! array_key_exists($role, StaffRoleHelper::getAvailableRoles())) {
                return $matches[0];
            }

            $staffUsername = $matches[1] !== '' ? $matches[1] : $matches[2];
            $staffRole = $role;

            return ' ';
        }, $keyword);

        $keyword = (string) preg_replace_callback('/(?<!\S)podium:(?:"([^"]+)"|([^@\s]+))(?:@(1st|2nd|3rd|1|2|3))?(?=\s|$)/', function (array $matches) use (&$podiumUsername, &$podiumPlacement): string {
            $podiumUsername = $matches[1] !== '' ? $matches[1] : $matches[2];
            $podiumPlacement = isset($matches[3])
                ? match ($matches[3]) {
                    '1st', '1' => 1,
                    '2nd', '2' => 2,
                    '3rd', '3' => 3,
                }
            : null;

            return ' ';
        }, $keyword);

        $keyword = (string) preg_replace_callback('/(?<!\S)format:(draft|auction|world-cup|world_cup|suiji|battle-royale|battle_royale)(?=\s|$)/i', function (array $matches) use (&$formatTag): string {
            $formatTag = strtolower(str_replace('-', '_', $matches[1]));

            return ' ';
        }, $keyword);

        return new SearchCommandCriteria(
            keyword: trim((string) preg_replace('/\s+/', ' ', $keyword)),
            rank: $rank,
            starRating: $starRating,
            countries: array_values(array_unique($countries)),
            staffUsername: $staffUsername,
            staffRole: $staffRole,
            podiumUsername: $podiumUsername,
            podiumPlacement: $podiumPlacement,
            formatTag: $formatTag,
        );
    }
}
