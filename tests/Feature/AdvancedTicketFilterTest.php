<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
Logic:
Verifies filtering through the HTTP API rather than testing only private
TicketFilter methods.

This confirms that validation, authentication, TicketFilter, Eloquent,
pagination and resource serialization work together correctly.

Structure:
Advanced filtering tests live separately from core ticket API tests.

DSA:
Tests use small deterministic datasets. Production query performance is
handled primarily by MySQL and pagination.
*/
class AdvancedTicketFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_escape_organization_scope_using_filter(): void
    {
        $ownOrganization =
            Organization::factory()
                ->create();

        $otherOrganization =
            Organization::factory()
                ->create();

        $client =
            User::factory()
                ->forOrganization(
                    $ownOrganization
                )
                ->create();

        $ownTicket =
            Ticket::factory()
                ->create([
                    'organization_id' => $ownOrganization->id,
                ]);

        Ticket::factory()
            ->create([
                'organization_id' => $otherOrganization->id,
            ]);

        $this->actingAs(
            $client
        );

        $response =
            $this->getJson(
                "/api/tickets?organization_id={$otherOrganization->id}"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ownTicket->id
            );
    }

    public function test_agent_can_filter_unassigned_tickets(): void
    {
        $agent = User::factory()
            ->supportAgent()
            ->create();

        // dump([
        //     'role' => $agent->role,
        //     'is_client' => $agent->isClient(),
        //     'is_support_agent' => $agent->isSupportAgent(),
        // ]);

        $this->actingAs($agent);

        $unassigned = Ticket::factory()
            ->create([
                'assigned_to' => null,
            ]);

        Ticket::factory()
            ->create([
                'assigned_to' => $agent->id,
            ]);

        $response = $this->getJson(
            '/api/tickets?assignment=unassigned'
        );

        $response->dump();

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $unassigned->id
            );
    }

    public function test_agent_can_filter_by_specific_agent(): void
    {
        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $otherAgent =
            User::factory()
                ->supportAgent()
                ->create();

        $expectedTicket =
            Ticket::factory()
                ->create([
                    'assigned_to' => $otherAgent->id,
                ]);

        Ticket::factory()
            ->create([
                'assigned_to' => $agent->id,
            ]);

        $this->actingAs(
            $agent
        );

        $this->getJson(
            "/api/tickets?assigned_to={$otherAgent->id}"
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $expectedTicket->id
            );
    }

    public function test_agent_can_search_organization_name(): void
    {
        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $acme =
            Organization::factory()
                ->create([
                    'name' => 'Acme Corporation',
                ]);

        $globex =
            Organization::factory()
                ->create([
                    'name' => 'Globex Corporation',
                ]);

        $expectedTicket =
            Ticket::factory()
                ->create([
                    'organization_id' => $acme->id,
                ]);

        Ticket::factory()
            ->create([
                'organization_id' => $globex->id,
            ]);

        $this->actingAs(
            $agent
        );

        $this->getJson(
            '/api/tickets?search=Acme'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $expectedTicket->id
            );
    }

    public function test_ticket_can_be_searched_by_formatted_id(): void
    {
        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $ticket =
            Ticket::factory()
                ->create();

        $this->actingAs(
            $agent
        );

        $search =
            str_pad(
                $ticket->id,
                4,
                '0',
                STR_PAD_LEFT
            );

        $this->getJson(
            "/api/tickets?search=%23{$search}"
        )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ticket->id,
            ]);
    }

    public function test_agent_can_filter_by_created_date(): void
    {
        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $oldTicket =
            Ticket::factory()
                ->create([
                    'created_at' => '2026-07-01 10:00:00',
                ]);

        $recentTicket =
            Ticket::factory()
                ->create([
                    'created_at' => '2026-08-20 10:00:00',
                ]);

        $this->actingAs(
            $agent
        );

        $this->getJson(
            '/api/tickets?created_from=2026-08-01'
        )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $recentTicket->id,
            ])
            ->assertJsonMissing([
                'id' => $oldTicket->id,
            ]);
    }

    public function test_agent_can_filter_overdue_tickets(): void
    {
        Carbon::setTestNow(
            '2026-08-30 12:00:00'
        );

        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $overdueTicket =
            Ticket::factory()
                ->create([
                    'status' => TicketStatus::OPEN,

                    'initial_priority' => TicketPriority::NORMAL,

                    'priority' => TicketPriority::NORMAL,

                    'sla_due_at' => '2026-08-30 10:00:00',
                ]);

        Ticket::factory()
            ->create([
                'status' => TicketStatus::OPEN,

                'initial_priority' => TicketPriority::NORMAL,

                'priority' => TicketPriority::NORMAL,

                'sla_due_at' => '2026-08-31 12:00:00',
            ]);

        $this->actingAs(
            $agent
        );

        $this->getJson(
            '/api/tickets?sla_status=overdue'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $overdueTicket->id
            );

        Carbon::setTestNow();
    }

    public function test_ticket_results_are_paginated(): void
    {
        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        Ticket::factory()
            ->count(25)
            ->create();

        $this->actingAs(
            $agent
        );

        $this->getJson(
            '/api/tickets?per_page=20'
        )
            ->assertOk()
            ->assertJsonCount(
                20,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                25
            );
    }
}
