<?php

namespace App\Filters;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Database\Eloquent\Builder;

/*
Logic:
TicketFilter translates validated API filter values into an Eloquent query.

The filtering process is deliberately ordered:

1. Apply authorization-related organization scope.
2. Apply exact database filters.
3. Apply text search.
4. Apply calculated SLA filtering.

Structure:
The controller delegates filtering to this class instead of containing
many unrelated if-statements.

Each filter has its own private method, making new filters easy to add
without making TicketController larger.

DSA:
Most filters become SQL WHERE clauses and are processed by MySQL.

The PHP side performs a fixed number of query-building operations: O(1).

Actual database cost depends on indexes and result size. Pagination prevents
the application from loading every matching ticket into PHP memory.
*/
class TicketFilter
{
    public function __construct(
        private readonly SlaService $slaService
    ) {}

    public function apply(
        Builder $query,
        User $user,
        array $filters
    ): Builder {
        $this->applyVisibility(
            $query,
            $user
        );

        $this->applyOrganization(
            $query,
            $user,
            $filters
        );

        $this->applyStatus(
            $query,
            $filters
        );

        $this->applyPriority(
            $query,
            $filters
        );

        $this->applyAssignment(
            $query,
            $user,
            $filters
        );

        $this->applyCreatedDate(
            $query,
            $filters
        );

        $this->applySearch(
            $query,
            $user,
            $filters
        );

        $this->applySlaStatus(
            $query,
            $filters
        );

        return $query;
    }

    /*
    Clients are restricted to their organization before any optional
    filtering is performed.

    This is a security constraint, not a UI filter.
    */
    private function applyVisibility(
        Builder $query,
        User $user
    ): void {
        if (! $user->isClient()) {
            return;
        }

        $query->where(
            'organization_id',
            $user->organization_id
        );
    }

    /*
    Organization filtering is only meaningful for support agents.

    Client-supplied organization_id values are deliberately ignored,
    because clients must never escape their own organization scope.
    */
    private function applyOrganization(
        Builder $query,
        User $user,
        array $filters
    ): void {
        if (! $user->isSupportAgent()) {
            return;
        }

        if (empty($filters['organization_id'])) {
            return;
        }

        $query->where(
            'organization_id',
            $filters['organization_id']
        );
    }

    private function applyStatus(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['status'])) {
            return;
        }

        $query->where(
            'status',
            $filters['status']
        );
    }

    private function applyPriority(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['priority'])) {
            return;
        }

        $query->where(
            'priority',
            $filters['priority']
        );
    }

    /*
    Support agents may filter tickets by:

    - a specific agent,
    - any assigned ticket,
    - unassigned tickets.

    A specific assigned_to value takes precedence over the generic
    assigned/unassigned filter.
    */
    private function applyAssignment(
        Builder $query,
        User $user,
        array $filters
    ): void {
        if (! $user->isSupportAgent()) {
            return;
        }

        if (! empty($filters['assigned_to'])) {
            $query->where(
                'assigned_to',
                $filters['assigned_to']
            );

            return;
        }

        $assignment =
          $filters['assignment'] ?? null;

        if ($assignment === 'assigned') {
            $query->whereNotNull(
                'assigned_to'
            );
        }

        if ($assignment === 'unassigned') {
            $query->whereNull(
                'assigned_to'
            );
        }
    }

    /*
    Date filtering remains in SQL.

    whereDate is clear for this prototype because users choose calendar
    dates rather than precise timestamps.
    */
    private function applyCreatedDate(
        Builder $query,
        array $filters
    ): void {
        if (! empty($filters['created_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $filters['created_from']
            );
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $filters['created_to']
            );
        }
    }

    /*
    Search uses one grouped OR expression.

    Example:

    status = open
    AND
    (
        title contains "Acme"
        OR description contains "Acme"
        OR organization contains "Acme"
    )

    Grouping prevents OR conditions from accidentally bypassing filters
    or organization restrictions.
    */
    private function applySearch(
        Builder $query,
        User $user,
        array $filters
    ): void {
        $search = trim(
            $filters['search'] ?? ''
        );

        if ($search === '') {
            return;
        }

        $ticketId =
          $this->extractTicketId(
              $search
          );

        $query->where(
            function (Builder $searchQuery) use ($search, $ticketId, $user) {
                $searchQuery
                    ->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );

                if ($ticketId !== null) {
                    $searchQuery->orWhere(
                        'id',
                        $ticketId
                    );
                }

                if (! $user->isSupportAgent()) {
                    return;
                }

                $searchQuery
                    ->orWhereHas(
                        'organization',
                        function (Builder $organizationQuery) use ($search) {
                            $organizationQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    )
                    ->orWhereHas(
                        'creator',
                        function (Builder $creatorQuery) use ($search) {
                            $creatorQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    )
                    ->orWhereHas(
                        'assignedAgent',
                        function (Builder $agentQuery) use ($search) {
                            $agentQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            }
        );
    }

    /*
    Allows searches like:

    15
    #15
    #0015

    to find ticket ID 15.
    */
    private function extractTicketId(
        string $search
    ): ?int {
        if (
            ! preg_match(
                '/^#?0*(\d+)$/',
                $search,
                $matches
            )
        ) {
            return null;
        }

        return (int) $matches[1];
    }

    /*
    SLA filtering stays inside SQL instead of calling ->get() first and
    filtering a PHP Collection.

    That is important for scalability because pagination can now happen
    after the SLA condition has already narrowed the query.
    */
    private function applySlaStatus(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['sla_status'])) {
            return;
        }

        $slaStatus =
          SlaStatus::from(
              $filters['sla_status']
          );

        match ($slaStatus) {
            SlaStatus::COMPLETED => $this->applyCompletedSla(
                $query
            ),

            SlaStatus::OVERDUE => $this->applyOverdueSla(
                $query
            ),

            SlaStatus::DUE_SOON => $this->applyDueSoonSla(
                $query
            ),

            SlaStatus::ON_TRACK => $this->applyOnTrackSla(
                $query
            ),
        };
    }

    private function applyCompletedSla(
        Builder $query
    ): void {
        $query->whereIn(
            'status',
            $this->completedStatuses()
        );
    }

    private function applyOverdueSla(
        Builder $query
    ): void {
        $query
            ->whereNotIn(
                'status',
                $this->completedStatuses()
            )
            ->where(
                'sla_due_at',
                '<',
                now()
            );
    }

    private function applyDueSoonSla(
        Builder $query
    ): void {
        $now = now();

        $query
            ->whereNotIn(
                'status',
                $this->completedStatuses()
            )
            ->where(
                'sla_due_at',
                '>=',
                $now
            )
            ->where(
                function (Builder $priorityQuery) use ($now) {
                    foreach (
                        TicketPriority::cases() as $priority
                    ) {
                        $cutoff =
                          $now->copy()->addMinutes(
                              $this->dueSoonMinutes(
                                  $priority
                              )
                          );

                        $priorityQuery->orWhere(
                            function (Builder $query) use ($priority, $cutoff) {
                                $query
                                    ->where(
                                        'initial_priority',
                                        $priority->value
                                    )
                                    ->where(
                                        'sla_due_at',
                                        '<=',
                                        $cutoff
                                    );
                            }
                        );
                    }
                }
            );
    }

    private function applyOnTrackSla(
        Builder $query
    ): void {
        $now = now();

        $query
            ->whereNotIn(
                'status',
                $this->completedStatuses()
            )
            ->where(
                function (Builder $priorityQuery) use ($now) {
                    foreach (
                        TicketPriority::cases() as $priority
                    ) {
                        $cutoff =
                          $now->copy()->addMinutes(
                              $this->dueSoonMinutes(
                                  $priority
                              )
                          );

                        $priorityQuery->orWhere(
                            function (Builder $query) use ($priority, $cutoff) {
                                $query
                                    ->where(
                                        'initial_priority',
                                        $priority->value
                                    )
                                    ->where(
                                        'sla_due_at',
                                        '>',
                                        $cutoff
                                    );
                            }
                        );
                    }
                }
            );
    }

    /*
    "Due soon" is the final 25% of the original SLA period.

    The SLA duration still comes from SlaService, keeping priority durations
    centralized rather than hardcoding 4/24/72 hours again in this class.
    */
    private function dueSoonMinutes(
        TicketPriority $priority
    ): int {
        $slaHours =
          $this->slaService->hoursFor(
              $priority
          );

        return (int) round(
            $slaHours
            * 60
            * 0.25
        );
    }

    private function completedStatuses(): array
    {
        return [
            TicketStatus::RESOLVED->value,
            TicketStatus::CLOSED->value,
        ];
    }
}
