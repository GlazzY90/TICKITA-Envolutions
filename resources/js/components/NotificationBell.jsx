import {
    Bell,
    CheckCheck,
} from 'lucide-react';

import {
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    useNavigate,
} from 'react-router-dom';

import {
    fetchNotifications,
    markAllNotificationsRead,
    markNotificationRead,
} from '../api/notifications';

/*
Logic:
Displays recent notifications, unread count, and navigation to the
related ticket.

Structure:
The bell owns only notification UI state. Laravel remains the source
of truth for stored/read notification state.

DSA:
Rendering n notifications is O(n), with n limited to 20.
Relative-time formatting performs a fixed number of comparisons: O(1).
Polling occurs once every 30 seconds.
*/

function relativeTime(dateString) {
    const diff =
        Date.now()
        - new Date(
            dateString
        ).getTime();

    const minute = 60 * 1000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (diff < minute) {
        return 'Just now';
    }

    if (diff < hour) {
        return `${Math.floor(
            diff / minute
        )}m ago`;
    }

    if (diff < day) {
        return `${Math.floor(
            diff / hour
        )}h ago`;
    }

    return `${Math.floor(
        diff / day
    )}d ago`;
}

export default function NotificationBell() {
    const navigate = useNavigate();

    const containerRef = useRef(null);

    const [open, setOpen] =
        useState(false);

    const [notifications, setNotifications] =
        useState([]);

    const [unreadCount, setUnreadCount] =
        useState(0);

    async function loadNotifications() {
        try {
            const response =
                await fetchNotifications();

            setNotifications(
                response.data
            );

            setUnreadCount(
                response.unread_count
            );
        } catch {
            // Notifications are supplementary UI.
            // Avoid breaking the entire page if refresh fails.
        }
    }

    useEffect(() => {
        loadNotifications();

        const interval =
            window.setInterval(
                loadNotifications,
                30000
            );

        return () => {
            window.clearInterval(
                interval
            );
        };
    }, []);

    useEffect(() => {
        function handleOutsideClick(event) {
            if (
                containerRef.current
                && !containerRef.current
                    .contains(event.target)
            ) {
                setOpen(false);
            }
        }

        document.addEventListener(
            'mousedown',
            handleOutsideClick
        );

        return () => {
            document.removeEventListener(
                'mousedown',
                handleOutsideClick
            );
        };
    }, []);

    async function handleNotificationClick(
        notification
    ) {
        if (!notification.read_at) {
            await markNotificationRead(
                notification.id
            );

            setNotifications(
                (current) =>
                    current.map((item) =>
                        item.id
                        === notification.id
                            ? {
                                ...item,
                                read_at:
                                    new Date()
                                        .toISOString(),
                            }
                            : item
                    )
            );

            setUnreadCount(
                (count) =>
                    Math.max(
                        0,
                        count - 1
                    )
            );
        }

        setOpen(false);

        if (notification.ticket_id) {
            navigate(
                `/tickets/${notification.ticket_id}`
            );
        }
    }

    async function handleMarkAllRead() {
        await markAllNotificationsRead();

        setNotifications(
            (current) =>
                current.map((item) => ({
                    ...item,
                    read_at:
                        item.read_at
                        ?? new Date()
                            .toISOString(),
                }))
        );

        setUnreadCount(0);
    }

    return (
        <div
            className="notification-wrapper"
            ref={containerRef}
        >
            <button
                className="notification-button"
                type="button"
                aria-label="Notifications"
                onClick={() =>
                    setOpen(
                        (current) => !current
                    )
                }
            >
                <Bell size={19} />

                {unreadCount > 0 && (
                    <span className="notification-count">
                        {unreadCount > 9
                            ? '9+'
                            : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="notification-dropdown">
                    <header className="notification-header">
                        <div>
                            <h3>
                                Notifications
                            </h3>

                            <span>
                                {unreadCount}{' '}
                                unread
                            </span>
                        </div>

                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={
                                    handleMarkAllRead
                                }
                            >
                                <CheckCheck
                                    size={14}
                                />

                                Mark all read
                            </button>
                        )}
                    </header>

                    <div className="notification-list">
                        {notifications.length
                            === 0 && (
                            <div className="notification-empty">
                                <Bell
                                    size={23}
                                />

                                <strong>
                                    No notifications
                                </strong>

                                <span>
                                    New ticket activity
                                    will appear here.
                                </span>
                            </div>
                        )}

                        {notifications.map(
                            (notification) => (
                                <button
                                    type="button"
                                    key={
                                        notification.id
                                    }
                                    className={
                                        notification.read_at
                                            ? 'notification-item'
                                            : 'notification-item unread'
                                    }
                                    onClick={() =>
                                        handleNotificationClick(
                                            notification
                                        )
                                    }
                                >
                                    <span className="notification-dot" />

                                    <div>
                                        <div className="notification-item-heading">
                                            <strong>
                                                {
                                                    notification.title
                                                }
                                            </strong>

                                            <time>
                                                {relativeTime(
                                                    notification.created_at
                                                )}
                                            </time>
                                        </div>

                                        <p>
                                            {
                                                notification.message
                                            }
                                        </p>
                                    </div>
                                </button>
                            )
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}