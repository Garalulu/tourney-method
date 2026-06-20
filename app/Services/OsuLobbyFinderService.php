<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OsuLobbyFinderService
{
    private const DEFAULT_LIMIT = 10;

    private const UPSTREAM_SCAN_LIMIT = 200;

    /**
     * @return array{lobbies: array<int, array<string, mixed>>, total: int, limit: int, offset: int, error: string|null, all_lobbies?: array<int, array<string, mixed>>}
     */
    public function search(
        string $query,
        int $offset = 0,
        int $limit = self::DEFAULT_LIMIT,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null
    ): array {
        $query = trim($query);
        $offset = max(0, $offset);
        $limit = min(10, max(1, $limit));

        if ($query === '') {
            return $this->emptyResult($limit, $offset);
        }

        try {
            if ($startsAt instanceof CarbonInterface || $endsAt instanceof CarbonInterface) {
                return $this->searchWithDateWindow($query, $offset, $limit, $startsAt, $endsAt);
            }

            return $this->searchSinglePage($query, $offset, $limit);
        } catch (Throwable $exception) {
            Log::warning('osu!LobbyFinder search exception', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return $this->failedResult($limit, $offset);
        }
    }

    /**
     * @return array{lobbies: array<int, array<string, mixed>>, total: int, limit: int, offset: int, error: string|null}
     */
    private function searchSinglePage(string $query, int $offset, int $limit): array
    {
        $data = $this->fetchPage($query, $offset, $limit);
        if ($data === null) {
            return $this->failedResult($limit, $offset);
        }

        $lobbies = collect($data['lobbies'] ?? [])
            ->filter(fn ($lobby): bool => is_array($lobby))
            ->map(fn (array $lobby): ?array => $this->normalizeLobby($lobby))
            ->filter()
            ->values()
            ->all();

        return [
            'lobbies' => $lobbies,
            'total' => max(0, (int) ($data['total'] ?? count($lobbies))),
            'limit' => max(1, (int) ($data['limit'] ?? $limit)),
            'offset' => max(0, (int) ($data['offset'] ?? $offset)),
            'error' => null,
        ];
    }

    /**
     * @return array{lobbies: array<int, array<string, mixed>>, total: int, limit: int, offset: int, error: string|null, all_lobbies: array<int, array<string, mixed>>}
     */
    private function searchWithDateWindow(string $query, int $offset, int $limit, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt): array
    {
        $matched = [];
        $upstreamOffset = 0;
        $upstreamTotal = null;
        $exhausted = false;

        while (! $exhausted) {
            $data = $this->fetchPage($query, $upstreamOffset, self::UPSTREAM_SCAN_LIMIT);
            if ($data === null) {
                return $this->failedResult($limit, $offset) + ['all_lobbies' => []];
            }

            $lobbies = collect($data['lobbies'] ?? [])
                ->filter(fn ($lobby): bool => is_array($lobby))
                ->values();
            $upstreamTotal ??= max(0, (int) ($data['total'] ?? 0));

            foreach ($lobbies as $lobby) {
                if ($this->isWithinDateWindow($lobby['created_at'] ?? null, $startsAt, $endsAt)) {
                    $normalized = $this->normalizeLobby($lobby);
                    if ($normalized !== null) {
                        $matched[] = $normalized;
                    }
                }
            }

            $upstreamOffset += self::UPSTREAM_SCAN_LIMIT;
            $exhausted = $lobbies->count() < self::UPSTREAM_SCAN_LIMIT
                || ($upstreamTotal !== null && $upstreamOffset >= $upstreamTotal)
                || $this->pageReachedStartBoundary($lobbies->all(), $startsAt);
        }

        return [
            'lobbies' => array_slice($matched, $offset, $limit),
            'all_lobbies' => $matched,
            'total' => count($matched),
            'limit' => $limit,
            'offset' => $offset,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $query, int $offset, int $limit): ?array
    {
        $response = Http::acceptJson()
            ->timeout(5)
            ->retry(1, 150)
            ->get($this->endpoint(), $this->queryParams($query, $offset, $limit));

        if (! $response->successful()) {
            Log::warning('osu!LobbyFinder search failed', [
                'status' => $response->status(),
                'query' => $query,
                'offset' => $offset,
            ]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{limit: int, offset: int, name: string, is_tournament: string}
     */
    private function queryParams(string $query, int $offset, int $limit): array
    {
        return [
            'limit' => $limit,
            'offset' => $offset,
            'name' => $query,
            'is_tournament' => 'true',
        ];
    }

    private function isWithinDateWindow(mixed $value, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt): bool
    {
        if ($startsAt === null && $endsAt === null) {
            return true;
        }

        $createdAt = $this->createdAt($value);
        if (! $createdAt instanceof Carbon) {
            return false;
        }

        if ($startsAt instanceof CarbonInterface && $createdAt->lt($startsAt)) {
            return false;
        }

        if ($endsAt instanceof CarbonInterface && $createdAt->gt($endsAt)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $lobbies
     */
    private function pageReachedStartBoundary(array $lobbies, ?CarbonInterface $startsAt): bool
    {
        if (! $startsAt instanceof CarbonInterface) {
            return false;
        }

        $oldestCreatedAt = collect($lobbies)
            ->filter(fn ($lobby): bool => is_array($lobby))
            ->map(fn (array $lobby): ?Carbon => $this->createdAt($lobby['created_at'] ?? null))
            ->filter()
            ->last();

        return $oldestCreatedAt instanceof Carbon && $oldestCreatedAt->lt($startsAt);
    }

    /**
     * @param  array<string, mixed>  $lobby
     * @return array<string, mixed>|null
     */
    private function normalizeLobby(array $lobby): ?array
    {
        $lobbyId = (int) ($lobby['lobby_id'] ?? 0);
        if ($lobbyId <= 0) {
            return null;
        }

        return [
            'lobby_id' => $lobbyId,
            'lobby_name' => trim((string) ($lobby['lobby_name'] ?? "MP {$lobbyId}")),
            'created_at_display' => $this->createdAtDisplay($lobby['created_at'] ?? null),
            'mp_link' => "https://osu.ppy.sh/community/matches/{$lobbyId}",
        ];
    }

    private function createdAtDisplay(mixed $value): ?string
    {
        $date = $this->createdAt($value);
        if (! $date instanceof Carbon) {
            return null;
        }

        return $date
            ->timezone(config('app.timezone'))
            ->format('M d');
    }

    private function createdAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            $date = preg_match('/[+-]\d{2}:\d{2}$/', $value)
                ? Carbon::parse($value)
                : Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC');

            return $date->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.osulobbyfinder.base_url'), '/').'/lobbies';
    }

    /**
     * @return array{lobbies: array<int, array<string, mixed>>, total: int, limit: int, offset: int, error: string|null}
     */
    private function emptyResult(int $limit, int $offset): array
    {
        return [
            'lobbies' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
            'error' => null,
        ];
    }

    /**
     * @return array{lobbies: array<int, array<string, mixed>>, total: int, limit: int, offset: int, error: string}
     */
    private function failedResult(int $limit, int $offset): array
    {
        return [
            'lobbies' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
            'error' => __('users.participation.lobby_search.error'),
        ];
    }
}
