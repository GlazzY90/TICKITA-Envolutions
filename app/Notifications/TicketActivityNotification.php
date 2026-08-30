<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/*
Logic:
Represents one user-facing ticket activity notification.

Structure:
The notification class only defines how notification data is stored.
It does not decide who should receive it; recipient rules live in
TicketNotificationService.

DSA:
No complex algorithm. Building the payload is O(1).
*/
class TicketActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $ticketId,
        private readonly string $type,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
