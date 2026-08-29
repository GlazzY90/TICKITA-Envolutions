<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createTicket(
        Organization $organization,
        User $creator
    ): Ticket {
        return Ticket::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'assigned_to' => null,
            'title' => 'Test ticket',
            'description' => 'Test ticket description.',
            'status' => TicketStatus::OPEN,
            'initial_priority' => TicketPriority::NORMAL,
            'priority' => TicketPriority::NORMAL,
            'sla_due_at' => now()->addDay(),
        ]);
    }

    public function test_client_can_view_ticket_from_own_organization(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($client)->allows('view', $ticket)
        );
    }

    public function test_client_cannot_view_ticket_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $ticket = $this->createTicket(
            $organizationB,
            $clientB
        );

        $this->assertTrue(
            Gate::forUser($clientA)->denies('view', $ticket)
        );
    }

    public function test_support_agent_can_view_ticket_from_any_organization(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows('view', $ticket)
        );
    }

    public function test_client_can_create_ticket(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $this->assertTrue(
            Gate::forUser($client)->allows(
                'create',
                Ticket::class
            )
        );
    }

    public function test_support_agent_cannot_create_client_ticket(): void
    {
        $agent = User::factory()
            ->supportAgent()
            ->create();

        $this->assertTrue(
            Gate::forUser($agent)->denies(
                'create',
                Ticket::class
            )
        );
    }

    public function test_client_can_reply_to_own_organization_ticket(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($client)->allows(
                'addReply',
                $ticket
            )
        );
    }

    public function test_client_cannot_reply_to_another_organization_ticket(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $ticket = $this->createTicket(
            $organizationB,
            $clientB
        );

        $this->assertTrue(
            Gate::forUser($clientA)->denies(
                'addReply',
                $ticket
            )
        );
    }

    public function test_support_agent_can_reply_to_any_ticket(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'addReply',
                $ticket
            )
        );
    }

    public function test_client_cannot_update_ticket(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($client)->denies(
                'update',
                $ticket
            )
        );
    }

    public function test_support_agent_can_update_ticket(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'update',
                $ticket
            )
        );
    }

    public function test_client_cannot_add_internal_note(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($client)->denies(
                'addInternalNote',
                $ticket
            )
        );
    }

    public function test_support_agent_can_add_internal_note(): void
    {
        $organization = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organization)
            ->create();

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $ticket = $this->createTicket(
            $organization,
            $client
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'addInternalNote',
                $ticket
            )
        );
    }
}
