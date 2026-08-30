<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageVisibility;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filters\TicketFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\SlaService;
use App\Services\TicketNotificationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/*
Logic:
The controller coordinates the HTTP request.

It:
1. verifies authorization,
2. creates the base ticket query,
3. delegates filtering,
4. orders and paginates,
5. returns resources.

Structure:
Business/filtering rules are deliberately delegated to TicketFilter.
The controller remains easy to read even if more filters are added later.

DSA:
The controller itself performs no significant algorithm.
Database querying and pagination are delegated to MySQL/Eloquent.
*/
class TicketController extends Controller
{
    public function index(
        IndexTicketRequest $request,
        TicketFilter $filter
    ): AnonymousResourceCollection {
        Gate::authorize(
            'viewAny',
            Ticket::class
        );

        $query = Ticket::query()
            ->with([
                'organization',
                'creator',
                'assignedAgent',
            ]);

        // dump([
        //     'validated' => $request->validated(),
        // ]);

        $filter->apply(
            $query,
            $request->user(),
            $request->validated()
        );

        // dump([
        //     'sql' => $query->toSql(),
        //     'bindings' => $query->getBindings(),
        // ]);

        $tickets = $query
            ->latest('created_at')
            ->paginate(
                $request->integer(
                    'per_page',
                    20
                )
            )
            ->withQueryString();

        return TicketResource::collection(
            $tickets
        );
    }

    public function store(
        StoreTicketRequest $request,
        SlaService $slaService,
        TicketNotificationService $notifications
    ): TicketResource {
        Gate::authorize('create', Ticket::class);

        $validated = $request->validated();

        $priority = TicketPriority::from(
            $validated['priority']
        );

        $createdAt = now();

        $ticket = Ticket::create([
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            'assigned_to' => null,

            'title' => $validated['title'],
            'description' => $validated['description'],

            'status' => TicketStatus::OPEN,
            'initial_priority' => $priority,
            'priority' => $priority,

            'sla_due_at' => $slaService->deadlineFor(
                $priority,
                $createdAt
            ),

            'resolved_at' => null,
        ]);

        $notifications->ticketCreated(
            $ticket,
            $request->user()
        );

        $ticket->load([
            'organization:id,name',
            'creator:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($ticket);
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $user = request()->user();

        $ticket->load([
            'organization:id,name',
            'creator:id,name',
            'assignedAgent:id,name',

            'messages' => function ($query) use ($user) {
                $query
                    ->with('author:id,name,role')
                    ->oldest();

                if ($user->isClient()) {
                    $query->where(
                        'visibility',
                        MessageVisibility::CLIENT_VISIBLE->value
                    );
                }
            },
        ]);

        return new TicketResource($ticket);
    }

    public function update(
        UpdateTicketRequest $request,
        Ticket $ticket,
        TicketNotificationService $notifications
    ): TicketResource {
        Gate::authorize('update', $ticket);

        $validated = $request->validated();

        $originalStatus = $ticket->status;
        $originalAssignedTo = $ticket->assigned_to;

        if (array_key_exists('status', $validated)) {
            $status = TicketStatus::from(
                $validated['status']
            );

            $ticket->status = $status;

            if (
                $status === TicketStatus::RESOLVED
                || $status === TicketStatus::CLOSED
            ) {
                $ticket->resolved_at ??= now();
            } else {
                $ticket->resolved_at = null;
            }
        }

        if (array_key_exists('priority', $validated)) {
            $ticket->priority = TicketPriority::from(
                $validated['priority']
            );

            // Intentionally DO NOT recalculate sla_due_at.
        }

        if (array_key_exists('assigned_to', $validated)) {
            $ticket->assigned_to = $validated['assigned_to'];
        }

        $ticket->save();

        if (
            $ticket->status !== $originalStatus
        ) {
            $notifications->statusChanged(
                $ticket,
                $request->user()
            );
        }

        if (
            $ticket->assigned_to !== $originalAssignedTo
            && $ticket->assigned_to !== null
        ) {
            $notifications->assigned(
                $ticket
            );
        }

        $ticket->load([
            'organization:id,name',
            'creator:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($ticket);
    }
}
