<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\UserNotification;
use App\Services\DiscordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserSettingsController extends Controller
{
    public function __construct(
        private DiscordService $discordService
    ) {}

    /**
     * GET /users/me/settings
     *
     * Display settings page (HTML) or return settings as JSON (API).
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        $settings = [
            'main_mode' => $user->main_mode,
            'discord_webhook_url' => $user->discord_webhook_url,
            'discord_webhook_valid' => $user->discord_webhook_valid,
            'notify_registration' => $user->notify_registration,
            'notify_stream' => $user->notify_stream,
            'in_app_notification_preferences' => array_merge(
                UserNotification::DEFAULT_PREFERENCES,
                $user->in_app_notification_preferences ?? []
            ),
        ];

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return response()->json($settings);
        }

        // Return view for web requests
        return view('settings.index', [
            'settings' => $settings,
        ]);
    }

    /**
     * PATCH /users/me/settings
     *
     * Update user settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Handle webhook URL update with validation
        if (array_key_exists('discord_webhook_url', $validated)) {
            $webhookUrl = $validated['discord_webhook_url'];

            if ($webhookUrl !== null && $webhookUrl !== $user->discord_webhook_url) {
                // Validate the new webhook
                $isValid = $this->discordService->validateWebhook($webhookUrl);

                if (! $isValid) {
                    return response()->json([
                        'message' => 'Unable to validate webhook. Check URL and permissions.',
                        'errors' => [
                            'discord_webhook_url' => ['The provided webhook URL could not be validated.'],
                        ],
                    ], 400);
                }

                $user->discord_webhook_url = $webhookUrl;
                $user->discord_webhook_valid = true;
            } elseif ($webhookUrl === null) {
                // Remove webhook
                $user->discord_webhook_url = null;
                $user->discord_webhook_valid = false; // No webhook = not valid
            }
        }

        if (array_key_exists('main_mode', $validated)) {
            $user->main_mode = $validated['main_mode'];
        }

        if (array_key_exists('notify_registration', $validated)) {
            $user->notify_registration = (bool) $validated['notify_registration'];
        }

        if (array_key_exists('notify_stream', $validated)) {
            $user->notify_stream = (bool) $validated['notify_stream'];
        }

        if (array_key_exists('in_app_notification_preferences', $validated)) {
            $preferences = array_intersect_key(
                $validated['in_app_notification_preferences'],
                UserNotification::DEFAULT_PREFERENCES
            );

            $user->in_app_notification_preferences = array_merge(
                UserNotification::DEFAULT_PREFERENCES,
                array_map('boolval', $preferences)
            );
        }

        $user->save();

        $settings = [
            'main_mode' => $user->main_mode,
            'discord_webhook_url' => $user->discord_webhook_url,
            'discord_webhook_valid' => $user->discord_webhook_valid,
            'notify_registration' => $user->notify_registration,
            'notify_stream' => $user->notify_stream,
            'in_app_notification_preferences' => array_merge(
                UserNotification::DEFAULT_PREFERENCES,
                $user->in_app_notification_preferences ?? []
            ),
        ];

        if ($request->expectsJson()) {
            return response()->json($settings);
        }

        return redirect()
            ->route('settings.index')
            ->with('success', __('settings.saved'));
    }

    /**
     * POST /notifications/webhook/test
     *
     * Send a test message to the configured webhook.
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->discord_webhook_url) {
            return response()->json([
                'message' => 'No webhook configured. Please configure a webhook first.',
            ], 400);
        }

        if (! $this->discordService->isValidWebhookFormat($user->discord_webhook_url)) {
            return response()->json([
                'message' => 'Invalid webhook URL format.',
            ], 400);
        }

        $result = $this->discordService->sendTestMessage(
            $user->discord_webhook_url,
            $user->username
        );

        if ($result['success']) {
            // Mark webhook as valid
            $user->discord_webhook_valid = true;
            $user->save();

            return response()->json([
                'message' => 'Test message sent successfully.',
            ]);
        }

        // Mark webhook as invalid
        $user->discord_webhook_valid = false;
        $user->save();

        return response()->json([
            'message' => 'Failed to send test message.',
            'errors' => [
                'webhook' => [$result['error'] ?? 'Unknown error'],
            ],
        ], 400);
    }
}
