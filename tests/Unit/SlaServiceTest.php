<?php

namespace Tests\Unit;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\SlaService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/*
Logic:
Tests the SLA rules independently of HTTP and database behavior.

Structure:
SLA calculations are pure business logic, so these belong in a unit test.

DSA:
Each tested SLA calculation is O(1).
*/
class SlaServiceTest extends TestCase
{
    private SlaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SlaService;
    }

    public function test_deadline_is_calculated_from_priority(): void
    {
        $createdAt = CarbonImmutable::parse(
            '2026-08-29 10:00:00'
        );

        $this->assertSame(
            '2026-08-29 14:00:00',
            $this->service
                ->deadlineFor(
                    TicketPriority::HIGH,
                    $createdAt
                )
                ->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-08-30 10:00:00',
            $this->service
                ->deadlineFor(
                    TicketPriority::NORMAL,
                    $createdAt
                )
                ->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-09-01 10:00:00',
            $this->service
                ->deadlineFor(
                    TicketPriority::LOW,
                    $createdAt
                )
                ->format('Y-m-d H:i:s')
        );
    }

    public function test_sla_status_can_be_on_track(): void
    {
        $ticket = $this->ticket(
            deadline: '2026-08-29 14:00:00'
        );

        $status = $this->service->statusFor(
            $ticket,
            CarbonImmutable::parse('2026-08-29 12:00:00')
        );

        $this->assertSame(
            SlaStatus::ON_TRACK,
            $status
        );
    }

    public function test_sla_status_can_be_due_soon(): void
    {
        $ticket = $this->ticket(
            deadline: '2026-08-29 14:00:00'
        );

        $status = $this->service->statusFor(
            $ticket,
            CarbonImmutable::parse('2026-08-29 13:30:00')
        );

        $this->assertSame(
            SlaStatus::DUE_SOON,
            $status
        );
    }

    public function test_sla_status_can_be_overdue(): void
    {
        $ticket = $this->ticket(
            deadline: '2026-08-29 14:00:00'
        );

        $status = $this->service->statusFor(
            $ticket,
            CarbonImmutable::parse('2026-08-29 14:01:00')
        );

        $this->assertSame(
            SlaStatus::OVERDUE,
            $status
        );
    }

    public function test_resolved_ticket_is_completed(): void
    {
        $ticket = $this->ticket(
            deadline: '2026-08-29 14:00:00',
            status: TicketStatus::RESOLVED
        );

        $status = $this->service->statusFor(
            $ticket,
            CarbonImmutable::parse('2026-08-30 10:00:00')
        );

        $this->assertSame(
            SlaStatus::COMPLETED,
            $status
        );
    }

    private function ticket(
        string $deadline,
        TicketStatus $status = TicketStatus::OPEN
    ): Ticket {
        $ticket = new Ticket;

        $ticket->status = $status;
        $ticket->initial_priority = TicketPriority::HIGH;
        $ticket->priority = TicketPriority::HIGH;
        $ticket->sla_due_at = CarbonImmutable::parse($deadline);

        return $ticket;
    }
}
