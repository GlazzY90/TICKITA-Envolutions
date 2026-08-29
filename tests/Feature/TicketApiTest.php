<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
Logic:
Tests required ticket API behavior end-to-end through Laravel's HTTP layer.

Structure:
These are feature tests because policies, validation, Eloquent, resources,
and routes must work together.

DSA:
No application-level DSA.
Assertions operate over small test datasets.
Production filtering is delegated to MySQL.
*/
class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_only_sees_own_organization_tickets(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $ticketA = $this->createTicket(
            $organizationA,
            $clientA
        );

        $this->createTicket(
            $organizationB,
            $clientB
        );

        $this->actingAs($clientA, 'web');

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ticketA->id
            );
    }

    public function test_client_cannot_view_other_organization_ticket(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $ticketB = $this->createTicket(
            $organizationB,
            $clientB
        );

        $this->actingAs($clientA, 'web');

        $this->getJson("/api/tickets/{$ticketB->id}")
            ->assertForbidden();
    }

    public function test_client_ticket_creation_forces_own_organization(): void
    {
        Carbon::setTestNow(
            '2026-08-29 10:00:00'
        );

        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $client = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $this->actingAs($client, 'web');

        $response = $this->postJson('/api/tickets', [
            'organization_id' => $organizationB->id,
            'title' => 'Production outage',
            'description' => 'Our application is unavailable.',
            'priority' => 'high',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.organization.id',
                $organizationA->id
            )
            ->assertJsonPath(
                'data.initial_priority',
                'high'
            );

        $ticket = Ticket::firstOrFail();

        $this->assertSame(
            $organizationA->id,
            $ticket->organization_id
        );

        $this->assertSame(
            $client->id,
            $ticket->created_by
        );

        $this->assertSame(
            '2026-08-29 14:00:00',
            $ticket->sla_due_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_support_agent_can_see_all_tickets(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $this->createTicket($organizationA, $clientA);
        $this->createTicket($organizationB, $clientB);

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $this->actingAs($agent, 'web');

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_support_agent_can_filter_tickets(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientA = User::factory()
            ->forOrganization($organizationA)
            ->create();

        $clientB = User::factory()
            ->forOrganization($organizationB)
            ->create();

        $matching = $this->createTicket(
            $organizationA,
            $clientA,
            [
                'title' => 'Database connection failure',
                'priority' => TicketPriority::HIGH,
                'initial_priority' => TicketPriority::HIGH,
                'status' => TicketStatus::OPEN,
            ]
        );

        $this->createTicket(
            $organizationB,
            $clientB,
            [
                'title' => 'Small UI issue',
                'priority' => TicketPriority::LOW,
                'initial_priority' => TicketPriority::LOW,
                'status' => TicketStatus::RESOLVED,
            ]
        );

        $agent = User::factory()
            ->supportAgent()
            ->create();

        $this->actingAs($agent, 'web');

        $this->getJson(
            '/api/tickets?organization_id='
            . $organizationA->id
            . '&status=open&priority=high&search=Database'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matching->id
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

        $this->actingAs($client, 'web');

        $this->patchJson(
            "/api/tickets/{$ticket->id}",
            [
                'status' => 'resolved',
            ]
        )->assertForbidden();
    }

    public function test_agent_can_update_ticket_without_recalculating_sla(): void
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
            $client,
            [
                'initial_priority' => TicketPriority::HIGH,
                'priority' => TicketPriority::HIGH,
            ]
        );

        $originalDeadline = $ticket->sla_due_at->copy();

        $this->actingAs($agent, 'web');

        $this->patchJson(
            "/api/tickets/{$ticket->id}",
            [
                'status' => 'resolved',
                'priority' => 'low',
                'assigned_to' => $agent->id,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'resolved'
            )
            ->assertJsonPath(
                'data.priority',
                'low'
            )
            ->assertJsonPath(
                'data.assigned_agent.id',
                $agent->id
            );

        $ticket->refresh();

        $this->assertTrue(
            $originalDeadline->equalTo(
                $ticket->sla_due_at
            )
        );

        $this->assertNotNull(
            $ticket->resolved_at
        );
    }

    private function createTicket(
        Organization $organization,
        User $creator,
        array $overrides = []
    ): Ticket {
        $initialPriority = $overrides['initial_priority']
            ?? TicketPriority::NORMAL;

        $priority = $overrides['priority']
            ?? $initialPriority;

        $sla = app(SlaService::class);

        return Ticket::create(array_merge([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'assigned_to' => null,
            'title' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::OPEN,
            'initial_priority' => $initialPriority,
            'priority' => $priority,
            'sla_due_at' => $sla->deadlineFor(
                $initialPriority,
                now()
            ),
            'resolved_at' => null,
        ], $overrides));
    }
}