import http from './http';

/*
Logic:
Provides frontend access to the authenticated user's notifications.

Structure:
HTTP details remain separate from NotificationBell presentation logic.

DSA:
No custom algorithm. Operations delegate to HTTP endpoints.
*/

export async function fetchNotifications() {
    const response = await http.get(
        '/api/notifications'
    );

    return response.data;
}

export async function markNotificationRead(id) {
    await http.post(
        `/api/notifications/${id}/read`
    );
}

export async function markAllNotificationsRead() {
    await http.post(
        '/api/notifications/read-all'
    );
}