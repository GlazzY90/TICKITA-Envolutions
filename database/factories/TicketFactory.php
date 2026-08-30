<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */

/*
Logic:
Provides reusable default test data for Ticket models.

Structure:
Tests only override the fields relevant to the behavior they are testing.
Common ticket fields stay here instead of being duplicated across tests.

DSA:
No algorithm is involved. Each factory invocation creates one fixed-size
ticket record, so application-side work is O(1).
*/
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),

            'created_by' => User::factory(),

            'assigned_to' => null,

            'title' => fake()->sentence(5),

            'description' => fake()->paragraph(),

            'status' => TicketStatus::OPEN,

            'initial_priority' => TicketPriority::NORMAL,

            'priority' => TicketPriority::NORMAL,

            'sla_due_at' => now()->addHours(24),

            'resolved_at' => null,
        ];
    }

    /*
    Logic:
    Convenience state for tests that need a specific assigned support agent.

    Instead of repeating:
        ['assigned_to' => $agent->id]
    throughout the test suite, callers can use:
        Ticket::factory()->assignedTo($agent)->create()
    */
    public function assignedTo(User $agent): static
    {
        return $this->state(fn () => [
            'assigned_to' => $agent->id,
        ]);
    }

    /*
    Logic:
    Creates an unresolved ticket whose SLA deadline has already passed.
    Useful for SLA/filtering tests.
    */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::OPEN,
            'sla_due_at' => now()->subHour(),
        ]);
    }
}
