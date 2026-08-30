<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\StoreTicketMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\Ticket;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\Gate;

/*
Logic:
Creates public ticket replies or agent-only internal notes.

Structure:
Replies and internal notes share the same ticket_messages table because
both are chronological conversation entries. Visibility distinguishes them.

DSA:
Creating one message is O(1) from the application's perspective.
Conversation ordering is performed by MySQL when the ticket is retrieved.
*/
class TicketMessageController extends Controller
{
    public function store(
        StoreTicketMessageRequest $request,
        Ticket $ticket,
        TicketNotificationService $notifications
    ): TicketMessageResource {
        $validated = $request->validated();

        $visibility = MessageVisibility::from(
            $validated['visibility']
            ?? MessageVisibility::CLIENT_VISIBLE->value
        );

        if ($visibility === MessageVisibility::INTERNAL) {
            Gate::authorize('addInternalNote', $ticket);
        } else {
            Gate::authorize('addReply', $ticket);
        }

        $message = $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'body' => $validated['body'],
            'visibility' => $visibility,
        ]);

        if (
            $visibility
            === MessageVisibility::CLIENT_VISIBLE
        ) {
            if ($request->user()->isClient()) {
                $notifications->clientReplied(
                    $ticket,
                    $request->user()
                );
            } else {
                $notifications->agentReplied(
                    $ticket,
                    $request->user()
                );
            }
        }

        $message->load('author:id,name,role');

        return new TicketMessageResource($message);
    }
}
