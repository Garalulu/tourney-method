<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Punic\Territory;

/**
 * Service for accessing Unicode CLDR data.
 *
 * Provides localized country/territory names with caching.
 */
class CldrService
{
    /**
     * Cache TTL in seconds (24 hours).
     */
    private const CACHE_TTL = 86400;

    /**
     * Get country name in specific locale.
     *
     * @param  string  $code  Territory code (ISO 3166-1 alpha-2)
     * @param  string  $locale  Locale code (e.g., 'en', 'ko', 'ja')
     * @return string Localized country name, or the code if not found
     */
    public function getCountryName(string $code, string $locale = 'en'): string
    {
        try {
            // Map variants to 'zh' for Punic
            $locale = str_starts_with($locale, 'zh') ? 'zh' : $locale;

            return Territory::getName($code, $locale);
        } catch (\Exception $e) {
            // Fallback to code if territory not found
            return $code;
        }
    }

    /**
     * Get all countries in specific locale.
     *
     * @param  string  $locale  Locale code (e.g., 'en', 'ko', 'ja')
     * @return array<string, string> Associative array [code => name]
     */
    public function getCountries(string $locale = 'en'): array
    {
        $locale = str_starts_with($locale, 'zh') ? 'zh' : $locale;

        return Cache::remember("cldr:countries:{$locale}", self::CACHE_TTL, function () use ($locale) {
            try {
                return Territory::getCountries($locale);
            } catch (\Exception $e) {
                // Fallback to empty array if error
                return [];
            }
        });
    }

    /**
     * Validate territory code.
     *
     * Checks if the code is a valid ISO 3166-1 alpha-2 territory code.
     *
     * @param  string  $code  Territory code to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidTerritoryCode(string $code): bool
    {
        // Basic format validation: 2 uppercase letters
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return false;
        }

        try {
            // Attempt to get the territory name (any locale)
            $name = Territory::getName($code, 'en');

            // If we get back the code itself, it's not a valid territory
            return $name !== $code;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get native country name (country's own language).
     *
     * @param  string  $code  Territory code
     * @return string Native country name, or the code if not found
     */
    public function getNativeCountryName(string $code): string
    {
        // Map common territory codes to their native locales
        $nativeLocales = [
            'KR' => 'ko',
            'US' => 'en',
            'JP' => 'ja',
            'CN' => 'zh',
            'GB' => 'en',
            'DE' => 'de',
            'FR' => 'fr',
            'ES' => 'es',
            'IT' => 'it',
            'PT' => 'pt',
            'RU' => 'ru',
            'BR' => 'pt',
            'TW' => 'zh',
            'HK' => 'zh',
        ];

        $locale = $nativeLocales[$code] ?? 'en';

        return $this->getCountryName($code, $locale);
    }
}
