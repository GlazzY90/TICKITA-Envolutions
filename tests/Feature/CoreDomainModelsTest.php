<?php

namespace Tests\Feature;

use App\Enums\MessageVisibility;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreDomainModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_user_belongs_to_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()
            ->forOrganization($organization)
            ->create();

        $this->assertTrue(
            $user->organization->is($organization)
        );

        $this->assertSame(
            UserRole::CLIENT,
            $user->role
        );
    }

    public function test_support_agent_can_exist_without_an_organization(): void
    {
        $agent = User::factory()
            ->supportAgent()
            ->create();

        $this->assertNull($agent->organization_id);

        $this->assertSame(
            UserRole::SUPPORT_AGENT,
            $agent->role
        );
    }

    public function test_ticket_relationships_are_configured(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $ticket = Ticket::create([
            'organization_id' => $organization->id,
            'created_by' => $client->id,
            'assigned_to' => $agent->id,
            'title' => 'Unable to log in',
            'description' => 'The user cannot access the portal.',
            'status' => TicketStatus::OPEN,
            'initial_priority' => TicketPriority::HIGH,
            'priority' => TicketPriority::HIGH,
            'sla_due_at' => now()->addHours(4),
        ]);

        $this->assertTrue(
            $ticket->organization->is($organization)
        );

        $this->assertTrue(
            $ticket->creator->is($client)
        );

        $this->assertTrue(
            $ticket->assignedAgent->is($agent)
        );

        $this->assertSame(
            TicketStatus::OPEN,
            $ticket->status
        );

        $this->assertSame(
            TicketPriority::HIGH,
            $ticket->priority
        );
    }

    public function test_ticket_message_relationship_and_visibility_cast_are_configured(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = Ticket::create([
            'organization_id' => $organization->id,
            'created_by' => $client->id,
            'title' => 'Unable to log in',
            'description' => 'The user cannot access the portal.',
            'status' => TicketStatus::OPEN,
            'initial_priority' => TicketPriority::NORMAL,
            'priority' => TicketPriority::NORMAL,
            'sla_due_at' => now()->addDay(),
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $client->id,
            'body' => 'Here is some additional information.',
            'visibility' => MessageVisibility::CLIENT_VISIBLE,
        ]);

        $this->assertTrue(
            $message->ticket->is($ticket)
        );

        $this->assertTrue(
            $message->author->is($client)
        );

        $this->assertSame(
            MessageVisibility::CLIENT_VISIBLE,
            $message->visibility
        );
    }
}