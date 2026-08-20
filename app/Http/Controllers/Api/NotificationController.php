<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * عرض إشعارات المستخدم الحالي.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'notifications' => $notifications,
        ], 200);
    }


    /**
     * عرض إشعار محدد.
     */
    public function show(Request $request, Notification $notification)
    {
        $user = $request->user();

        // منع المستخدم من مشاهدة إشعار يخص مستخدماً آخر
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to view this notification.'
            ], 403);
        }

        return response()->json([
            'message' => 'Notification retrieved successfully.',
            'notification' => $notification,
        ], 200);
    }


    /**
     * تحديد إشعار كمقروء.
     */
    public function markAsRead(Request $request, Notification $notification)
    {
        $user = $request->user();

        // التأكد أن الإشعار يخص المستخدم الحالي
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to update this notification.'
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read successfully.',
            'notification' => $notification,
        ], 200);
    }


    /**
     * تحديد جميع إشعارات المستخدم كمقروءة.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notification::markAllAsRead($user->id);

        return response()->json([
            'message' => 'All notifications marked as read successfully.',
        ], 200);
    }


    /**
     * حذف إشعار.
     */
    public function destroy(Request $request, Notification $notification)
    {
        $user = $request->user();

        // منع حذف إشعار يخص مستخدماً آخر
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this notification.'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ], 200);
    }


    /**
     * إرجاع عدد الإشعارات غير المقروءة.
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Unread notifications count retrieved successfully.',
            'unread_count' => $count,
        ], 200);
    }
}
