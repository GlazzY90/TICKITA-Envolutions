<?php

namespace Tests\Feature;

use App\Enums\MessageVisibility;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
Logic:
Verifies public reply and internal-note security requirements.

Structure:
These are HTTP feature tests because the critical requirement is not merely
database state: client API responses must never contain internal notes.

DSA:
Conversation retrieval is O(m) after database ordering/filtering,
where m is the number of visible messages.
*/
class TicketMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_add_public_reply_to_own_ticket(): void
    {
        [, $client, $ticket] =
            $this->ticketContext();

        $this->actingAs($client, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'Additional information.',
                'visibility' => 'public',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.visibility',
                'public'
            );

        $this->assertDatabaseHas(
            'ticket_messages',
            [
                'ticket_id' => $ticket->id,
                'author_id' => $client->id,
                'body' => 'Additional information.',
                'visibility' => 'public',
            ]
        );
    }

    public function test_client_cannot_create_internal_note(): void
    {
        [, $client, $ticket] =
            $this->ticketContext();

        $this->actingAs($client, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'Secret note',
                'visibility' => 'internal',
            ]
        )->assertForbidden();
    }

    public function test_agent_can_create_internal_note(): void
    {
        [, , $ticket] =
            $this->ticketContext();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $this->actingAs($agent, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'Database connection is saturated.',
                'visibility' => 'internal',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.visibility',
                'internal'
            );
    }

    public function test_client_detail_never_contains_internal_notes(): void
    {
        [, $client, $ticket] =
            $this->ticketContext();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $agent->id,
            'body' => 'Visible reply',
            'visibility' => MessageVisibility::CLIENT_VISIBLE,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $agent->id,
            'body' => 'SECRET INTERNAL NOTE',
            'visibility' => MessageVisibility::INTERNAL,
        ]);

        $this->actingAs($client, 'web');

        $response = $this->getJson(
            "/api/tickets/{$ticket->id}"
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'body' => 'Visible reply',
            ])
            ->assertJsonMissing([
                'body' => 'SECRET INTERNAL NOTE',
            ])
            ->assertJsonMissing([
                'visibility' => 'internal',
            ]);
    }

    public function test_agent_detail_contains_internal_notes(): void
    {
        [, , $ticket] =
            $this->ticketContext();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $agent->id,
            'body' => 'SECRET INTERNAL NOTE',
            'visibility' => MessageVisibility::INTERNAL,
        ]);

        $this->actingAs($agent, 'web');

        $this->getJson(
            "/api/tickets/{$ticket->id}"
        )
            ->assertOk()
            ->assertJsonFragment([
                'body' => 'SECRET INTERNAL NOTE',
                'visibility' => 'internal',
            ]);
    }

    private function ticketContext(): array
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = Ticket::create([
            'organization_id' => $organization->id,
            'created_by' => $client->id,
            'assigned_to' => null,
            'title' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::OPEN,
            'initial_priority' => TicketPriority::NORMAL,
            'priority' => TicketPriority::NORMAL,
            'sla_due_at' => app(SlaService::class)
                ->deadlineFor(
                    TicketPriority::NORMAL,
                    now()
                ),
        ]);

        return [
            $organization,
            $client,
            $ticket,
        ];
    }
}