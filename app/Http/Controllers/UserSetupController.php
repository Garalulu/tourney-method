<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteSetupRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UserSetupController extends Controller
{
    /**
     * Show the setup form.
     */
    public function show(): View|RedirectResponse
    {
        $user = auth()->user();

        // If user has already manually selected their mode via OAuth setup, redirect to dashboard
        if (! $user->needsManualModeSetup()) {
            return redirect()->route('dashboard')
                ->with('info', 'You have already completed setup.');
        }

        return view('auth.setup');
    }

    /**
     * Complete user setup.
     *
     * Per OpenAPI spec: POST /users/setup
     * Returns User schema with setup_complete: true
     */
    public function store(CompleteSetupRequest $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        // Update user's main mode and mark as manually selected
        $user->main_mode = $request->validated('main_mode');
        $user->main_mode_source = 'oauth_setup';
        $user->save();

        // Return JSON for API requests per OpenAPI spec
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $user->id,
                'osu_id' => $user->osu_id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'country_code' => $user->country_code,
                'main_mode' => $user->main_mode,
                'role' => $user->role,
                'setup_complete' => $user->hasCompletedSetup(),
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Setup complete! Welcome to Tourney Method.');
    }
}
