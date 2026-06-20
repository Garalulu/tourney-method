<?php

namespace App\Services;

class OtrDataValidator
{
    /**
     * Validate tournament data and return array of errors
     *
     * @param  array{
     *     forum_url?: string|null,
     *     start_time?: string|null,
     *     end_time?: string|null,
     *     ruleset?: int|null,
     *     name?: mixed,
     *     lobby_size?: int|null
     * }  $otrData
     * @return array<string, string>
     */
    public function validate(array $otrData): array
    {
        $errors = [];

        // Validate forum URL
        if (! $this->validateForumUrl($otrData['forum_url'] ?? null)) {
            $errors['forum_url'] = 'Invalid forum URL format';
        }

        // Validate dates
        if (! $this->validateDates($otrData)) {
            $errors['dates'] = 'Invalid date range: end time must be after start time';
        }

        // Validate ruleset
        if (! $this->validateRuleset($otrData['ruleset'] ?? null)) {
            $errors['ruleset'] = 'Invalid ruleset value';
        }

        // Validate required fields
        if (empty($otrData['name'])) {
            $errors['name'] = 'Tournament name is required';
        }

        if ($otrData['lobby_size'] <= 0) {
            $errors['lobby_size'] = 'Lobby size must be positive';
        }

        return $errors;
    }

    /**
     * Validate forum URL format and extract topic ID
     * Accepts both forum topic URLs and wiki URLs
     */
    public function validateForumUrl(?string $url): bool
    {
        if (empty($url)) {
            return true; // Empty forum URL is allowed
        }

        // Trim whitespace and null bytes from URL (dump files have trailing spaces)
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Must be an absolute URL
        if (! str_contains($url, '://')) {
            return false;
        }

        // Accept forum topic URL or wiki URL
        $isValidForumUrl = preg_match('/\/topics\/(\d+)/', $url);
        $isValidWikiUrl = str_contains($url, 'wiki');

        return $isValidForumUrl || $isValidWikiUrl;
    }

    /**
     * Validate date range
     *
     * @param  array{start_time?: string|null, end_time?: string|null}  $otrData
     */
    public function validateDates(array $otrData): bool
    {
        if (empty($otrData['start_time']) || empty($otrData['end_time'])) {
            return false;
        }

        try {
            $startTime = new \DateTime($otrData['start_time']);
            $endTime = new \DateTime($otrData['end_time']);
        } catch (\Exception $e) {
            return false;
        }

        return $endTime > $startTime;
    }

    /**
     * Validate ruleset value
     */
    public function validateRuleset(?int $ruleset): bool
    {
        // Valid ruleset values: 0-5 (0=osu, 1=taiko, 2=catch, 3/4/5=mania)
        return $ruleset !== null && $ruleset >= 0 && $ruleset <= 5;
    }

    /**
     * Extract forum topic ID from URL
     */
    public function extractForumTopicId(?string $url): ?int
    {
        if (empty($url)) {
            return null;
        }

        // Trim whitespace and null bytes from URL (dump files have trailing spaces)
        $url = trim($url);

        if (! preg_match('/\/topics\/(\d+)/', $url, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
