<?php

namespace App\Http\Resources;

use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
Logic:
Defines the ticket representation used by both list and detail views.

Structure:
One resource avoids separate client/agent response shapes while
whenLoaded() allows detail-only relationships such as messages.

DSA:
Each ticket transformation is O(1), excluding messages.
A ticket list of n items is O(n).
SLA calculation is O(1) per ticket.
*/
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slaService = app(SlaService::class);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'status' => $this->status->value,
            'initial_priority' => $this->initial_priority->value,
            'priority' => $this->priority->value,

            'sla_due_at' => $this->sla_due_at?->toISOString(),
            'sla_status' => $slaService
                ->statusFor($this->resource)
                ->value,

            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),

            'organization' => $this->whenLoaded(
                'organization',
                fn () => [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ]
            ),

            'creator' => $this->whenLoaded(
                'creator',
                fn () => [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]
            ),

            'assigned_agent' => $this->whenLoaded(
                'assignedAgent',
                fn () => $this->assignedAgent
                ? [
                    'id' => $this->assignedAgent->id,
                    'name' => $this->assignedAgent->name,
                ]
                : null
            ),

            'messages' => TicketMessageResource::collection(
                $this->whenLoaded('messages')
            ),
        ];
    }
}
