<?php

namespace Database\Seeders;

use App\Enums\MessageVisibility;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Database\Seeder;

/*
Logic:
Creates predictable development accounts, organizations, tickets,
and conversation examples for manual testing.

Structure:
The seeder calls SlaService rather than duplicating SLA calculations.

DSA:
Creates a fixed small dataset, therefore O(1) for this development seed.
*/
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $sla = app(SlaService::class);

        $acme = Organization::factory()->create([
            'name' => 'Acme Corporation',
        ]);

        $globex = Organization::factory()->create([
            'name' => 'Globex Corporation',
        ]);

        $acmeClient = User::factory()
            ->forOrganization($acme)
            ->create([
                'name' => 'Acme Client',
                'email' => 'client@acme.test',
            ]);

        $globexClient = User::factory()
            ->forOrganization($globex)
            ->create([
                'name' => 'Globex Client',
                'email' => 'client@globex.test',
            ]);

        $agent = User::factory()
            ->supportAgent()
            ->create([
                'name' => 'Support Agent',
                'email' => 'agent@support.test',
            ]);

        $highTicket = Ticket::create([
            'organization_id' => $acme->id,
            'created_by' => $acmeClient->id,
            'assigned_to' => null,
            'title' => 'Unable to access production system',
            'description' =>
                'Our team cannot access the production application.',
            'status' => TicketStatus::OPEN,
            'initial_priority' => TicketPriority::HIGH,
            'priority' => TicketPriority::HIGH,
            'sla_due_at' => $sla->deadlineFor(
                TicketPriority::HIGH,
                now()
            ),
        ]);

        $normalTicket = Ticket::create([
            'organization_id' => $globex->id,
            'created_by' => $globexClient->id,
            'assigned_to' => $agent->id,
            'title' => 'Export function returns an error',
            'description' =>
                'CSV export fails on the reports page.',
            'status' => TicketStatus::IN_PROGRESS,
            'initial_priority' => TicketPriority::NORMAL,
            'priority' => TicketPriority::NORMAL,
            'sla_due_at' => $sla->deadlineFor(
                TicketPriority::NORMAL,
                now()
            ),
        ]);

        TicketMessage::create([
            'ticket_id' => $highTicket->id,
            'author_id' => $acmeClient->id,
            'body' => 'This started this morning.',
            'visibility' => MessageVisibility::CLIENT_VISIBLE,
        ]);

        TicketMessage::create([
            'ticket_id' => $highTicket->id,
            'author_id' => $agent->id,
            'body' => 'We are investigating the issue.',
            'visibility' => MessageVisibility::CLIENT_VISIBLE,
        ]);

        TicketMessage::create([
            'ticket_id' => $highTicket->id,
            'author_id' => $agent->id,
            'body' => 'Possible database connection saturation.',
            'visibility' => MessageVisibility::INTERNAL,
        ]);
    }
}