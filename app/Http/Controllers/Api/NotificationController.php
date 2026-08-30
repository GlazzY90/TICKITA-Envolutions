<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
Logic:
Lets the authenticated user retrieve and mark their own notifications.

Structure:
Every notification lookup begins from the authenticated user's
notification relationship. This prevents one user from reading or
modifying another user's notifications.

DSA:
Retrieving the latest 20 is bounded.
Mark-all-read uses one SQL UPDATE rather than iterating through rows.
*/
class NotificationController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $notifications = $request
            ->user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,

                'type' => $notification
                    ->data['type']
                    ?? null,

                'ticket_id' => $notification
                    ->data['ticket_id']
                    ?? null,

                'title' => $notification
                    ->data['title']
                    ?? 'Notification',

                'message' => $notification
                    ->data['message']
                    ?? '',

                'read_at' => $notification
                    ->read_at
                    ?->toISOString(),

                'created_at' => $notification
                    ->created_at
                    ?->toISOString(),
            ]);

        return response()->json([
            'data' => $notifications,

            'unread_count' => $request
                ->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    public function markAsRead(
        Request $request,
        string $notificationId
    ): JsonResponse {
        $notification = $request
            ->user()
            ->notifications()
            ->findOrFail(
                $notificationId
            );

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(
        Request $request
    ): JsonResponse {
        $request
            ->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Notifications marked as read.',
        ]);
    }
}
