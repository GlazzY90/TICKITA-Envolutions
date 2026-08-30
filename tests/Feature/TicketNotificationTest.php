<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use App\Services\SlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/*
Logic:
Verifies that important ticket actions notify the correct roles and
that internal notes never generate client notifications.

Structure:
Notification::fake() lets the test focus on recipient/event logic without
depending on database-notification persistence.

DSA:
Recipient selection uses small test collections. Production behavior is
O(n) in the number of recipients.
*/
class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_ticket_notifies_support_agents(): void
    {
        Notification::fake();

        $organization =
            Organization::factory()
                ->create();

        $client =
            User::factory()
                ->forOrganization(
                    $organization
                )
                ->create();

        $agentA =
            User::factory()
                ->supportAgent()
                ->create();

        $agentB =
            User::factory()
                ->supportAgent()
                ->create();

        $this->actingAs($client, 'web');

        $this->postJson(
            '/api/tickets',
            [
                'title' => 'Production unavailable',

                'description' => 'The application is down.',

                'priority' => 'high',
            ]
        )->assertCreated();

        Notification::assertSentTo(
            [$agentA, $agentB],
            TicketActivityNotification::class
        );
    }

    public function test_client_reply_notifies_assigned_agent(): void
    {
        Notification::fake();

        [$ticket, $client] =
            $this->ticketContext();

        $assignedAgent =
            User::factory()
                ->supportAgent()
                ->create();

        $otherAgent =
            User::factory()
                ->supportAgent()
                ->create();

        $ticket->update([
            'assigned_to' => $assignedAgent->id,
        ]);

        $this->actingAs($client, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'Any update?',

                'visibility' => 'public',
            ]
        )->assertCreated();

        Notification::assertSentTo(
            $assignedAgent,
            TicketActivityNotification::class
        );

        Notification::assertNotSentTo(
            $otherAgent,
            TicketActivityNotification::class
        );
    }

    public function test_agent_public_reply_notifies_organization_clients(): void
    {
        Notification::fake();

        [$ticket, $client] =
            $this->ticketContext();

        $secondClient =
            User::factory()
                ->forOrganization(
                    $ticket->organization
                )
                ->create();

        $otherOrganization =
            Organization::factory()
                ->create();

        $otherClient =
            User::factory()
                ->forOrganization(
                    $otherOrganization
                )
                ->create();

        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $this->actingAs($agent, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'We are investigating.',

                'visibility' => 'public',
            ]
        )->assertCreated();

        Notification::assertSentTo(
            [$client, $secondClient],
            TicketActivityNotification::class
        );

        Notification::assertNotSentTo(
            $otherClient,
            TicketActivityNotification::class
        );
    }

    public function test_internal_note_does_not_notify_client(): void
    {
        Notification::fake();

        [$ticket, $client] =
            $this->ticketContext();

        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $this->actingAs($agent, 'web');

        $this->postJson(
            "/api/tickets/{$ticket->id}/messages",
            [
                'body' => 'Private investigation.',

                'visibility' => 'internal',
            ]
        )->assertCreated();

        Notification::assertNotSentTo(
            $client,
            TicketActivityNotification::class
        );
    }

    public function test_status_change_notifies_client(): void
    {
        Notification::fake();

        [$ticket, $client] =
            $this->ticketContext();

        $agent =
            User::factory()
                ->supportAgent()
                ->create();

        $this->actingAs($agent, 'web');

        $this->patchJson(
            "/api/tickets/{$ticket->id}",
            [
                'status' => 'in_progress',
            ]
        )->assertOk();

        Notification::assertSentTo(
            $client,
            TicketActivityNotification::class
        );
    }

    public function test_assignment_notifies_assigned_agent(): void
    {
        Notification::fake();

        [$ticket] =
            $this->ticketContext();

        $actingAgent =
            User::factory()
                ->supportAgent()
                ->create();

        $assignedAgent =
            User::factory()
                ->supportAgent()
                ->create();

        $this->actingAs(
            $actingAgent,
            'web'
        );

        $this->patchJson(
            "/api/tickets/{$ticket->id}",
            [
                'assigned_to' => $assignedAgent->id,
            ]
        )->assertOk();

        Notification::assertSentTo(
            $assignedAgent,
            TicketActivityNotification::class
        );
    }

    private function ticketContext(): array
    {
        $organization =
            Organization::factory()
                ->create();

        $client =
            User::factory()
                ->forOrganization(
                    $organization
                )
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
            $ticket,
            $client,
        ];
    }
}
