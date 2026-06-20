<?php

namespace App\Services;

class UnitedNationsGeoschemeService
{
    public function __construct(
        private CldrService $cldrService
    ) {}

    /**
     * @return array<int, array{code: string, name: string, english_name: string, flag: string}>
     */
    public function countryOptions(string $locale = 'en'): array
    {
        $countries = $this->cldrService->getCountries($locale);
        $englishCountries = $locale === 'en' ? $countries : $this->cldrService->getCountries('en');

        $options = [];

        foreach ($countries as $code => $name) {
            if (! $this->isSelectableTerritoryCode($code)) {
                continue;
            }

            $options[] = [
                'code' => $code,
                'name' => $name,
                'english_name' => $englishCountries[$code] ?? $name,
                'flag' => country_flag($code),
            ];
        }

        usort($options, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $options;
    }

    /**
     * @return array<int, array{code: string, name: string, type: string, countries: array<int, string>, count: int}>
     */
    public function templates(): array
    {
        $groups = config('un_geoscheme.groups', []);
        $customTemplates = config('un_geoscheme.custom_templates', []);
        $templates = [];

        foreach ($groups as $code => $group) {
            $countries = $this->expandCountries((string) $code, $groups);

            $templates[] = [
                'code' => (string) $code,
                'name' => $group['name'],
                'type' => $group['type'],
                'countries' => $countries,
                'count' => count($countries),
            ];
        }

        foreach ($customTemplates as $code => $template) {
            $countries = $this->expandCountries((string) $code, $customTemplates);

            $templates[] = [
                'code' => (string) $code,
                'name' => $template['name'],
                'type' => $template['type'],
                'countries' => $countries,
                'count' => count($countries),
            ];
        }

        usort($templates, function (array $a, array $b): int {
            $typeOrder = ['continent' => 1, 'intermediary' => 2, 'subregion' => 3, 'custom' => 4];
            $typeCompare = ($typeOrder[$a['type']] ?? 99) <=> ($typeOrder[$b['type']] ?? 99);

            return $typeCompare !== 0 ? $typeCompare : strcasecmp($a['name'], $b['name']);
        });

        return $templates;
    }

    /**
     * @param  array<string, array{name: string, type: string, children: array<int, string>}>  $groups
     * @return array<int, string>
     */
    private function expandCountries(string $code, array $groups): array
    {
        if (! isset($groups[$code])) {
            return $this->isSelectableTerritoryCode($code) ? [$code] : [];
        }

        $countries = [];

        foreach ($groups[$code]['children'] as $child) {
            $countries = array_merge($countries, $this->expandCountries($child, $groups));
        }

        $countries = array_values(array_unique($countries));
        sort($countries);

        return $countries;
    }

    private function isSelectableTerritoryCode(string $code): bool
    {
        return preg_match('/^[A-Z]{2}$/', $code) === 1
            && $this->cldrService->isValidTerritoryCode($code);
    }
}
