<?php

namespace App\Http\Controllers;

use App\Models\SipFetchQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SipController extends Controller
{
    /**
     * Get pending SIP fetch queue items.
     *
     * @return JsonResponse
     */
    public function getQueue(Request $request)
    {
        // Security: Simple API token check
        $expectedToken = config('services.sip.api_token');
        $providedToken = $request->header('X-SIP-API-Token');

        if ($expectedToken && $providedToken !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get pending users (limit 50 per batch)
        $queues = SipFetchQueue::where('status', 'pending')
            ->limit(50)
            ->get();

        if ($queues->isEmpty()) {
            return response()->json([
                'users' => [],
            ]);
        }

        // Mark as processing
        SipFetchQueue::whereIn('id', $queues->pluck('id'))
            ->update(['status' => 'processing']);

        return response()->json([
            'users' => $queues->map(function ($queue) {
                return [
                    'queue_id' => $queue->id,
                    'osu_id' => $queue->osu_id,
                    'username' => $queue->username,
                ];
            }),
        ]);
    }

    /**
     * Receive SIP fetch results from Google Sheets.
     *
     * @return JsonResponse
     */
    public function updateSip(Request $request)
    {
        // Security check
        $expectedToken = config('services.sip.api_token');
        $providedToken = $request->header('X-SIP-API-Token');

        if ($expectedToken && $providedToken !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'results' => 'required|array',
            'results.*.queue_id' => 'required|integer',
            'results.*.sip' => 'nullable|integer',
            'results.*.error' => 'nullable|string',
        ]);

        $successCount = 0;
        $failedCount = 0;

        foreach ($validated['results'] as $result) {
            $queue = SipFetchQueue::find($result['queue_id']);

            if (! $queue) {
                continue;
            }

            if ($result['sip'] !== null) {
                // Success: Update user's SIP
                User::where('osu_id', $queue->osu_id)
                    ->update([
                        'sip' => $result['sip'],
                        'sip_updated_at' => now(),
                    ]);

                $queue->update([
                    'status' => 'completed',
                    'sip' => $result['sip'],
                ]);

                $successCount++;
            } else {
                // Failed: Log error
                $queue->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]);

                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'processed' => count($validated['results']),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }
}
