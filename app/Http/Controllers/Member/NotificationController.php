<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/member/notifications
     * Retrieve paginated in-app notifications with unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $member = $request->user();
        $perPage = min(50, (int) $request->query('per_page', 15));

        $notifications = $member->notifications()->paginate($perPage);
        $unreadCount = $member->unreadNotifications()->count();

        return response()->json([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * PATCH /api/member/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $member = $request->user();
        $notification = $member->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message'      => 'Notification marked as read.',
            'unread_count' => $member->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/member/notifications/read-all
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $member = $request->user();
        $member->unreadNotifications->markAsRead();

        return response()->json([
            'message'      => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}
