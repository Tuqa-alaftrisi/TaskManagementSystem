<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * عرض إشعارات المستخدم الحالي.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'notifications' => $notifications,
            ],
        ], 200);
    }

    /**
     * عرض إشعار محدد.
     */
    public function show(
        Request $request,
        Notification $notification
    ): JsonResponse {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this notification.',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification retrieved successfully.',
            'data' => [
                'notification' => $notification,
            ],
        ], 200);
    }

    /**
     * تحديد إشعار كمقروء.
     */
    public function markAsRead(
        Request $request,
        Notification $notification
    ): JsonResponse {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this notification.',
                'data' => null,
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read successfully.',
            'data' => [
                'notification' => $notification->fresh(),
            ],
        ], 200);
    }

    /**
     * تحديد جميع إشعارات المستخدم كمقروءة.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::markAllAsRead($user->id);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read successfully.',
            'data' => null,
        ], 200);
    }

    /**
     * حذف إشعار.
     */
    public function destroy(
        Request $request,
        Notification $notification
    ): JsonResponse {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this notification.',
                'data' => null,
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
            'data' => null,
        ], 200);
    }

    /**
     * إرجاع عدد الإشعارات غير المقروءة.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread notifications count retrieved successfully.',
            'data' => [
                'unread_count' => $count,
            ],
        ], 200);
    }
}
