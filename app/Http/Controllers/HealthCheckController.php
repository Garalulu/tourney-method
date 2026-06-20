<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Health check endpoint for Railway monitoring
     *
     * Returns application health status including database and Redis connectivity.
     * Used by Railway's healthcheck system to verify service is running.
     *
     * @return JsonResponse
     */
    public function __invoke()
    {
        $status = 'healthy';
        $checks = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status' => 'connected',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $checks['database'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Check Redis connection (only if configured and extension is available)
        try {
            $redisConfigured = config('cache.default') === 'redis';
            $redisExtensionLoaded = extension_loaded('redis');

            if ($redisConfigured && $redisExtensionLoaded) {
                Redis::ping();
                $checks['redis'] = [
                    'status' => 'connected',
                    'connection' => config('cache.default'),
                ];
            } elseif (! $redisConfigured) {
                $checks['redis'] = [
                    'status' => 'skipped',
                    'reason' => 'Redis not configured as cache driver',
                    'current_driver' => config('cache.default'),
                ];
            } else {
                $checks['redis'] = [
                    'status' => 'skipped',
                    'reason' => 'Redis PHP extension not installed',
                ];
            }
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $checks['redis'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Check queue worker (Horizon)
        try {
            $horizonStatus = app('horizon')->status();
            $checks['horizon'] = [
                'status' => $horizonStatus === 'running' ? 'running' : 'stopped',
            ];
        } catch (\Exception $e) {
            // Horizon might not be configured in all environments
            $checks['horizon'] = [
                'status' => 'not_configured',
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ];

            // Log the error for debugging
            if (config('app.debug')) {
                \Log::error('Horizon health check failed', [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $response = [
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ];

        // Return appropriate HTTP status code
        $httpStatus = $status === 'healthy' ? 200 : 503;

        return response()->json($response, $httpStatus);
    }
}
