<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->visible()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (UserNotification $notification): array => $this->payload($notification));

        $unreadCount = $user->notifications()
            ->visible()
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->markRead();

        return response()->json([
            'notification' => $this->payload($notification->refresh()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()
            ->notifications()
            ->visible()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    public function destroyRead(Request $request): JsonResponse
    {
        $dismissedCount = $request->user()
            ->notifications()
            ->visible()
            ->whereNotNull('read_at')
            ->update(['dismissed_at' => now()]);

        return response()->json(['dismissed_count' => $dismissedCount]);
    }

    public function destroy(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->dismiss();

        return response()->json(['dismissed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'created_label' => $notification->created_at->diffForHumans(),
        ];
    }
}
