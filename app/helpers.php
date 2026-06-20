<?php

if (! function_exists('country_flag')) {
    /**
     * Convert country code to flag emoji.
     *
     * @param  string|null  $countryCode  Two-letter ISO country code
     * @return string Flag emoji or empty string if invalid
     */
    function country_flag(?string $countryCode): string
    {
        if (! $countryCode || strlen($countryCode) !== 2) {
            return '';
        }

        $countryCode = strtoupper($countryCode);
        $flag = '';

        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(ord($countryCode[$i]) - ord('A') + 0x1F1E6);
        }

        return $flag;
    }
}
