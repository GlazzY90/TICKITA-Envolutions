<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Support\Facades\Notification;

/*
Logic:
Centralizes who receives notifications for ticket activity.

Structure:
Recipient-selection rules are business logic and would make controllers
too complicated if repeated there. Controllers tell this service what
happened; this service determines who needs to know.

DSA:
Recipient lookup is delegated to indexed MySQL queries.
Sending to n recipients is O(n) because one database notification record
is stored for each recipient.
*/
class TicketNotificationService
{
    public function ticketCreated(
        Ticket $ticket,
        User $actor
    ): void {
        $agents = User::query()
            ->where(
                'role',
                UserRole::SUPPORT_AGENT->value
            )
            ->get();

        Notification::send(
            $agents,
            new TicketActivityNotification(
                ticketId: $ticket->id,
                type: 'ticket_created',
                title: 'New support ticket',
                message: "{$actor->name} created \"{$ticket->title}\".",
            )
        );
    }

    public function clientReplied(
        Ticket $ticket,
        User $actor
    ): void {
        if ($ticket->assigned_to !== null) {
            $recipient = User::query()
                ->whereKey($ticket->assigned_to)
                ->where(
                    'role',
                    UserRole::SUPPORT_AGENT->value
                )
                ->first();

            if ($recipient) {
                $recipient->notify(
                    new TicketActivityNotification(
                        ticketId: $ticket->id,
                        type: 'client_reply',
                        title: 'New client reply',
                        message: "{$actor->name} replied to \"{$ticket->title}\".",
                    )
                );
            }

            return;
        }

        $agents = User::query()
            ->where(
                'role',
                UserRole::SUPPORT_AGENT->value
            )
            ->get();

        Notification::send(
            $agents,
            new TicketActivityNotification(
                ticketId: $ticket->id,
                type: 'client_reply',
                title: 'New client reply',
                message: "{$actor->name} replied to \"{$ticket->title}\".",
            )
        );
    }

    public function agentReplied(
        Ticket $ticket,
        User $actor
    ): void {
        $clients = $this->organizationClients(
            $ticket
        );

        Notification::send(
            $clients,
            new TicketActivityNotification(
                ticketId: $ticket->id,
                type: 'agent_reply',
                title: 'New support reply',
                message: "{$actor->name} replied to \"{$ticket->title}\".",
            )
        );
    }

    public function statusChanged(
        Ticket $ticket,
        User $actor
    ): void {
        $clients = $this->organizationClients(
            $ticket
        );

        $status = str_replace(
            '_',
            ' ',
            $ticket->status->value
        );

        Notification::send(
            $clients,
            new TicketActivityNotification(
                ticketId: $ticket->id,
                type: 'status_changed',
                title: 'Ticket status updated',
                message: "{$actor->name} changed \"{$ticket->title}\" to {$status}.",
            )
        );
    }

    public function assigned(
        Ticket $ticket
    ): void {
        if ($ticket->assigned_to === null) {
            return;
        }

        $agent = User::query()
            ->whereKey($ticket->assigned_to)
            ->where(
                'role',
                UserRole::SUPPORT_AGENT->value
            )
            ->first();

        if (! $agent) {
            return;
        }

        $agent->notify(
            new TicketActivityNotification(
                ticketId: $ticket->id,
                type: 'ticket_assigned',
                title: 'Ticket assigned to you',
                message: "You were assigned to \"{$ticket->title}\".",
            )
        );
    }

    private function organizationClients(
        Ticket $ticket
    ) {
        return User::query()
            ->where(
                'organization_id',
                $ticket->organization_id
            )
            ->where(
                'role',
                UserRole::CLIENT->value
            )
            ->get();
    }
}
