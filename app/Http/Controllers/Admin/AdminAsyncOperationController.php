<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAsyncOperation;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

class AdminAsyncOperationController extends Controller
{
    public function show(AdminAsyncOperation $operation): JsonResponse
    {
        $operation->load('tournament');

        return response()->json([
            'id' => $operation->id,
            'type' => $operation->type,
            'status' => $operation->status,
            'total' => $operation->total,
            'completed' => $operation->completed,
            'failed' => $operation->failed,
            'percent' => $operation->percent(),
            'message' => $operation->message,
            'errors' => $operation->errors ?? [],
            'result' => $operation->result ?? [],
            'fragments' => $this->fragments($operation),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function fragments(AdminAsyncOperation $operation): array
    {
        if ($operation->status !== AdminAsyncOperation::STATUS_COMPLETED || ! $operation->tournament) {
            return [];
        }

        /** @phpstan-ignore-next-line Dynamic Eloquent scope. */
        $tournament = Tournament::withStaffSortedByRole()->find($operation->tournament_id);

        if (! $tournament) {
            return [];
        }

        $tournament->load(['winners.user']);

        $fragments = [];
        $refresh = $operation->result['refresh'] ?? [];

        if (in_array('staff', $refresh, true)) {
            $fragments['staff'] = view('components.admin.tournaments.tournament-staff-management', [
                'tournament' => $tournament,
            ])->render();
        }

        if (in_array('podium', $refresh, true)) {
            $fragments['podium'] = view('components.admin.tournaments.tournament-podium-management', [
                'tournament' => $tournament,
            ])->render();
        }

        return $fragments;
    }
}
