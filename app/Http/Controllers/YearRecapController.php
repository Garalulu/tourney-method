<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\YearRecapCache;
use App\Services\YearRecapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class YearRecapController extends Controller
{
    public function __construct(
        protected YearRecapService $recapService
    ) {}

    /**
     * Generate or retrieve year-end recap image for the current user.
     *
     * POST /users/me/recap/{year}
     */
    public function generate(int $year): JsonResponse
    {
        $requestYear = (int) $year;

        // Validate year range
        if ($requestYear < 2020 || $requestYear > 2030) {
            return response()->json([
                'message' => 'Year must be between 2020 and 2030',
            ], 400);
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        // Check if user has data for the requested year
        if (! $this->recapService->hasDataForYear($user, $requestYear)) {
            return response()->json([
                'message' => "No match data found for year {$requestYear}",
            ], 400);
        }

        try {
            $result = $this->recapService->getOrCreateRecap($user, $requestYear);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download the year-end recap image.
     *
     * GET /users/me/recap/{year}/download
     */
    public function download(int $year): StreamedResponse|JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        $cache = YearRecapCache::query()
            ->forUserAndYear($user->id, $year)
            ->first();

        if (! $cache) {
            return response()->json([
                'message' => 'Recap not generated yet. Please generate the recap first.',
            ], 404);
        }

        if (! Storage::exists($cache->image_path)) {
            return response()->json([
                'message' => 'Image file not found. Please regenerate the recap.',
            ], 404);
        }

        return Storage::download($cache->image_path, "{$user->username}_{$year}_recap.png");
    }
}
