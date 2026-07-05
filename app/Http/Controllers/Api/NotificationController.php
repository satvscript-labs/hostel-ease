<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard alert feed for the mobile app (tenant-scoped to the active branch).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::forUser($request->user())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'level' => $n->level,
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        $unread = Notification::forUser($request->user())->unread()->count();

        return response()->json([
            'unread_count' => $unread,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        // Notification has no TenantScope — verify branch ownership explicitly.
        $notification = Notification::findOrFail($notification);
        abort_unless($notification->hostel_id === \App\Support\Tenant::id(), 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::forUser($request->user())->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read.']);
    }
}
