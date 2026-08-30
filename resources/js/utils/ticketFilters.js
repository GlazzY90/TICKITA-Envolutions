/*
Logic:
This file converts frontend filter state into API query parameters.

The UI may have convenient values such as:
"agent:5"

The API should receive explicit values such as:
assigned_to=5

Structure:
Keeping this translation outside TicketsPage prevents the page component
from becoming responsible for API-specific filter formatting.

DSA:
There is a fixed number of filters, so transformation is effectively O(1).
*/

export const EMPTY_TICKET_FILTERS = {
    search: '',
    organization_id: '',
    status: '',
    priority: '',
    sla_status: '',
    assignee_filter: '',
    created_from: '',
    created_to: '',
};

export function buildTicketQuery({
    filters,
    page = 1,
    perPage = 20,
}) {
    const {
        assignee_filter,
        ...query
    } = filters;

    if (
        assignee_filter === 'assigned'
    ) {
        query.assignment = 'assigned';
    }

    if (
        assignee_filter === 'unassigned'
    ) {
        query.assignment = 'unassigned';
    }

    if (
        assignee_filter.startsWith(
            'agent:'
        )
    ) {
        query.assigned_to =
            assignee_filter.replace(
                'agent:',
                ''
            );
    }

    query.search =
        query.search.trim();

    query.page = page;
    query.per_page = perPage;

    return Object.fromEntries(
        Object.entries(query)
            .filter(
                ([, value]) =>
                    value !== ''
                    && value !== null
                    && value !== undefined
            )
    );
}