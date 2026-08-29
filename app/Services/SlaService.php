<?php

namespace App\Services;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/*
Logic:
Centralizes SLA deadline and SLA-state calculations.

Structure:
SLA is business logic, so it does not belong in a controller
or React. Keeping it here ensures ticket creation, API responses,
seeders, and tests all use the same rules.

DSA:
All operations are O(1). Priority lookup uses a match expression
and SLA classification uses a fixed number of datetime comparisons.
*/
class SlaService
{
    public function hoursFor(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::HIGH => 4,
            TicketPriority::NORMAL => 24,
            TicketPriority::LOW => 72,
        };
    }

    public function deadlineFor(
        TicketPriority $priority,
        CarbonInterface $createdAt
    ): CarbonImmutable {
        return CarbonImmutable::instance($createdAt)
            ->addHours($this->hoursFor($priority));
    }

    public function statusFor(
        Ticket $ticket,
        ?CarbonInterface $now = null
    ): SlaStatus {
        if (
            $ticket->status === TicketStatus::RESOLVED
            || $ticket->status === TicketStatus::CLOSED
        ) {
            return SlaStatus::COMPLETED;
        }

        $now = $now
          ? CarbonImmutable::instance($now)
          : CarbonImmutable::now();

        $deadline = CarbonImmutable::instance(
            $ticket->sla_due_at
        );

        if ($now->greaterThan($deadline)) {
            return SlaStatus::OVERDUE;
        }

        $dueSoonHours = intdiv(
            $this->hoursFor($ticket->initial_priority),
            4
        );

        $dueSoonAt = $deadline->subHours($dueSoonHours);

        if ($now->greaterThanOrEqualTo($dueSoonAt)) {
            return SlaStatus::DUE_SOON;
        }

        return SlaStatus::ON_TRACK;
    }
}
