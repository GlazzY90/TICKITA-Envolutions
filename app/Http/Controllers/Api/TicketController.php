<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageVisibility;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\SlaService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/*
Logic:
Coordinates ticket listing, creation, detail retrieval, and agent updates.

Structure:
Controllers orchestrate requests but delegate:
- validation -> Form Requests
- authorization -> TicketPolicy
- SLA logic -> SlaService
- database access -> Eloquent
- serialization -> TicketResource

DSA:
No in-memory filtering is used.
Filtering/search is delegated to MySQL.
Exact organization/status/priority filters can use indexes.
"%search%" may scan candidate rows.
List serialization is O(n).
*/
class TicketController extends Controller
{
    public function index(
        IndexTicketRequest $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();

        $query = Ticket::query()
            ->with([
                'organization:id,name',
                'creator:id,name',
                'assignedAgent:id,name',
            ]);

        if ($user->isClient()) {
            $query->where(
                'organization_id',
                $user->organization_id
            );
        }

        if (
            $user->isSupportAgent()
            && $request->filled('organization_id')
        ) {
            $query->where(
                'organization_id',
                $request->integer('organization_id')
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->string('priority')->toString()
            );
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();

            $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $tickets = $query
            ->latest()
            ->get();

        return TicketResource::collection($tickets);
    }

    public function store(
        StoreTicketRequest $request,
        SlaService $slaService
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
        Ticket $ticket
    ): TicketResource {
        Gate::authorize('update', $ticket);

        $validated = $request->validated();

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

        $ticket->load([
            'organization:id,name',
            'creator:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($ticket);
    }
}
