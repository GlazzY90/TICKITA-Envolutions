import {
    Filter,
    Plus,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';

import {
    useEffect,
    useState,
} from 'react';

import {
    fetchSupportOptions,
    fetchTickets,
} from '../api/tickets';

import AppShell from '../components/AppShell';
import CreateTicketModal from '../components/CreateTicketModal';
import TicketPagination from '../components/TicketPagination';
import TicketTable from '../components/TicketTable';

import {
    useAuth,
} from '../contexts/AuthContext';

import {
    buildTicketQuery,
    EMPTY_TICKET_FILTERS,
} from '../utils/ticketFilters';

/*
Logic:
This page coordinates the ticket-list UI.

It:
1. Stores the user's current filter selections.
2. Converts UI filters into API query parameters through buildTicketQuery().
3. Requests one paginated ticket page from Laravel.
4. Stores tickets and pagination metadata.
5. Lets users search, filter, clear filters, and move between pages.

The page does NOT perform ticket filtering itself.
Filtering, authorization, and search execution remain on the Laravel backend.

Structure:
- TicketsPage manages UI state.
- ticketFilters.js converts UI state into API parameters.
- tickets.js handles HTTP communication.
- TicketFilter.php performs backend filtering.
- TicketPagination handles pagination presentation.
- TicketTable handles ticket-list presentation.

This keeps the page focused on coordination rather than business logic.

DSA:
No expensive client-side algorithm is used.
Only the current page of tickets is rendered, so rendering is O(n),
where n is at most the configured page size (20 by default).
Search and filtering are delegated to MySQL.
*/

const statusTabs = [
    {
        value: '',
        label: 'All',
    },
    {
        value: 'open',
        label: 'Open',
    },
    {
        value: 'in_progress',
        label: 'In Progress',
    },
    {
        value: 'resolved',
        label: 'Resolved',
    },
    {
        value: 'closed',
        label: 'Closed',
    },
];

export default function TicketsPage() {
    const { user } = useAuth();

    const isAgent =
        user.role === 'support_agent';

    /*
    Main ticket results.

    Only the current page of tickets is stored here.
    */
    const [tickets, setTickets] =
        useState([]);

    /*
    UI filter state.

    EMPTY_TICKET_FILTERS is defined in ticketFilters.js
    so filter defaults are not duplicated across components.
    */
    const [filters, setFilters] =
        useState(
            EMPTY_TICKET_FILTERS
        );

    /*
    Laravel pagination metadata.

    Example:
    {
        current_page: 1,
        last_page: 3,
        per_page: 20,
        total: 53
    }
    */
    const [
        pagination,
        setPagination,
    ] = useState(null);

    /*
    Support agents need organizations and agents
    for their filter dropdowns.

    Clients do not request these options.
    */
    const [options, setOptions] =
        useState({
            organizations: [],
            agents: [],
        });

    /*
    Controls whether the optional advanced filter
    section is visible.
    */
    const [
        showAdvancedFilters,
        setShowAdvancedFilters,
    ] = useState(false);

    const [loading, setLoading] =
        useState(true);

    const [error, setError] =
        useState('');

    const [
        createOpen,
        setCreateOpen,
    ] = useState(false);

    /*
    Logic:
    Loads one page of tickets using the selected filters.

    selectedFilters is passed separately because React state updates
    are asynchronous. This allows us to immediately query using a
    newly-created filter object without waiting for setFilters().
    */
    async function loadTickets(
        page = 1,
        selectedFilters = filters
    ) {
        setLoading(true);
        setError('');

        try {
            /*
            UI state is converted into API parameters here.

            Example:

            assignee_filter = "agent:4"

            becomes:

            assigned_to = 4
            */
            const params =
                buildTicketQuery({
                    filters:
                        selectedFilters,

                    page,

                    perPage: 20,
                });

            /*
            fetchTickets() now returns:

            {
                tickets,
                meta,
                links
            }

            rather than only an array.
            */
            const result =
                await fetchTickets(
                    params
                );

            setTickets(
                result.tickets
            );

            setPagination(
                result.meta
            );
        } catch (requestError) {
            setError(
                requestError.response
                    ?.data?.message
                ?? 'Unable to load tickets.'
            );
        } finally {
            setLoading(false);
        }
    }

    /*
    Initial page load.

    Everyone receives tickets.

    Only support agents request organization/agent
    options because clients cannot use those filters.
    */
    useEffect(() => {
        loadTickets(
            1,
            EMPTY_TICKET_FILTERS
        );

        if (isAgent) {
            fetchSupportOptions()
                .then(setOptions)
                .catch(() => {
                    setError(
                        'Unable to load support options.'
                    );
                });
        }
    }, [isAgent]);

    /*
    Search/filter submissions always return to page 1.

    Otherwise a user could be on page 4, apply a filter
    that only has one page, and receive an empty result.
    */
    async function handleSearch(
        event
    ) {
        event.preventDefault();

        await loadTickets(
            1,
            filters
        );
    }

    /*
    Status tabs apply immediately.

    We create updatedFilters first because setFilters()
    does not update React state synchronously.
    */
    async function handleStatusTab(
        status
    ) {
        const updatedFilters = {
            ...filters,
            status,
        };

        setFilters(
            updatedFilters
        );

        await loadTickets(
            1,
            updatedFilters
        );
    }

    /*
    Restores every filter to its default value and
    reloads the first page.
    */
    async function clearFilters() {
        setFilters(
            EMPTY_TICKET_FILTERS
        );

        await loadTickets(
            1,
            EMPTY_TICKET_FILTERS
        );
    }

    /*
    Pagination keeps the existing filters.

    Example:

    status=open
    priority=high
    page=2

    Changing pages does not lose the active filters.
    */
    async function handlePageChange(
        page
    ) {
        await loadTickets(
            page,
            filters
        );

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    }

    /*
    Determines whether the Clear button should appear.
    */
    const hasFilters =
        Object.values(filters)
            .some(
                (value) =>
                    value !== ''
            );

    /*
    Laravel's pagination total represents the total
    number of tickets matching the current query,
    not only tickets on the current page.
    */
    const totalTickets =
        pagination?.total
        ?? tickets.length;

    return (
        <AppShell>
            <div className="page-heading">
                <div>
                    <p className="eyebrow">
                        SUPPORT
                    </p>

                    <h1>
                        {isAgent
                            ? 'Tickets'
                            : 'My Tickets'}
                    </h1>

                    <p className="page-subtitle">
                        {isAgent
                            ? 'Manage support requests across all organizations.'
                            : 'Track and manage support requests from your organization.'}
                    </p>
                </div>

                {!isAgent && (
                    <button
                        className="btn btn-primary"
                        type="button"
                        onClick={() =>
                            setCreateOpen(
                                true
                            )
                        }
                    >
                        <Plus size={17} />

                        New Ticket
                    </button>
                )}
            </div>

            {error && (
                <div className="alert alert-error">
                    {error}
                </div>
            )}

            {/* Status is exposed as quick-access tabs. */}
            <div className="status-tabs">
                {statusTabs.map(
                    (tab) => (
                        <button
                            key={
                                tab.value
                            }
                            type="button"
                            className={
                                filters.status
                                === tab.value
                                    ? 'status-tab active'
                                    : 'status-tab'
                            }
                            onClick={() =>
                                handleStatusTab(
                                    tab.value
                                )
                            }
                        >
                            {tab.label}
                        </button>
                    )
                )}
            </div>

            <section className="panel">
                {/* Main search/filter toolbar */}
                <form
                    className="ticket-toolbar"
                    onSubmit={
                        handleSearch
                    }
                >
                    <div className="search-control">
                        <Search size={17} />

                        <input
                            type="search"
                            placeholder={
                                isAgent
                                    ? 'Search ticket, organization, requester or agent...'
                                    : 'Search tickets...'
                            }
                            value={
                                filters.search
                            }
                            onChange={(
                                event
                            ) =>
                                setFilters({
                                    ...filters,

                                    search:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        />
                    </div>

                    {/* Priority filter */}
                    <div className="toolbar-select">
                        <Filter size={15} />

                        <select
                            value={
                                filters.priority
                            }
                            onChange={(
                                event
                            ) =>
                                setFilters({
                                    ...filters,

                                    priority:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        >
                            <option value="">
                                All priorities
                            </option>

                            <option value="high">
                                High
                            </option>

                            <option value="normal">
                                Normal
                            </option>

                            <option value="low">
                                Low
                            </option>
                        </select>
                    </div>

                    {/*
                    Organization filtering is only exposed
                    to support agents.

                    Backend authorization still enforces this
                    independently of the frontend.
                    */}
                    {isAgent && (
                        <div className="toolbar-select organization-filter">
                            <select
                                value={
                                    filters
                                        .organization_id
                                }
                                onChange={(
                                    event
                                ) =>
                                    setFilters({
                                        ...filters,

                                        organization_id:
                                            event
                                                .target
                                                .value,
                                    })
                                }
                            >
                                <option value="">
                                    All organizations
                                </option>

                                {options.organizations
                                    .map(
                                        (
                                            organization
                                        ) => (
                                            <option
                                                key={
                                                    organization.id
                                                }
                                                value={
                                                    organization.id
                                                }
                                            >
                                                {
                                                    organization.name
                                                }
                                            </option>
                                        )
                                    )}
                            </select>
                        </div>
                    )}

                    {/* Toggles the less frequently used filters. */}
                    <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={() =>
                            setShowAdvancedFilters(
                                (
                                    current
                                ) =>
                                    !current
                            )
                        }
                    >
                        <SlidersHorizontal
                            size={15}
                        />

                        More Filters
                    </button>

                    {/* Submit all currently selected filters. */}
                    <button
                        className="btn btn-secondary"
                        type="submit"
                    >
                        Apply
                    </button>

                    {hasFilters && (
                        <button
                            type="button"
                            className="clear-filter-button"
                            onClick={
                                clearFilters
                            }
                        >
                            <X size={15} />

                            Clear
                        </button>
                    )}
                </form>

                {/*
                Advanced filters remain collapsible so the main
                ticket interface stays readable.
                */}
                {showAdvancedFilters && (
                    <div className="advanced-filter-panel">
                        {/* SLA filter */}
                        <label className="compact-filter">
                            <span>
                                SLA Status
                            </span>

                            <select
                                value={
                                    filters
                                        .sla_status
                                }
                                onChange={(
                                    event
                                ) =>
                                    setFilters({
                                        ...filters,

                                        sla_status:
                                            event
                                                .target
                                                .value,
                                    })
                                }
                            >
                                <option value="">
                                    All SLA statuses
                                </option>

                                <option value="on_track">
                                    On Track
                                </option>

                                <option value="due_soon">
                                    Due Soon
                                </option>

                                <option value="overdue">
                                    Overdue
                                </option>

                                <option value="completed">
                                    Completed
                                </option>
                            </select>
                        </label>

                        {/*
                        Assignment filtering is an agent-only feature.

                        UI values:
                        assigned
                        unassigned
                        agent:ID

                        ticketFilters.js converts these into
                        backend API parameters.
                        */}
                        {isAgent && (
                            <label className="compact-filter">
                                <span>
                                    Assignment
                                </span>

                                <select
                                    value={
                                        filters
                                            .assignee_filter
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setFilters({
                                            ...filters,

                                            assignee_filter:
                                                event
                                                    .target
                                                    .value,
                                        })
                                    }
                                >
                                    <option value="">
                                        All assignments
                                    </option>

                                    <option value="assigned">
                                        Assigned
                                    </option>

                                    <option value="unassigned">
                                        Unassigned
                                    </option>

                                    {options.agents
                                        .map(
                                            (
                                                agent
                                            ) => (
                                                <option
                                                    key={
                                                        agent.id
                                                    }
                                                    value={
                                                        `agent:${agent.id}`
                                                    }
                                                >
                                                    Assigned
                                                    to{' '}
                                                    {
                                                        agent.name
                                                    }
                                                </option>
                                            )
                                        )}
                                </select>
                            </label>
                        )}

                        {/* Beginning of creation-date range */}
                        <label className="compact-filter">
                            <span>
                                Created From
                            </span>

                            <input
                                type="date"
                                value={
                                    filters
                                        .created_from
                                }
                                onChange={(
                                    event
                                ) =>
                                    setFilters({
                                        ...filters,

                                        created_from:
                                            event
                                                .target
                                                .value,
                                    })
                                }
                            />
                        </label>

                        {/* End of creation-date range */}
                        <label className="compact-filter">
                            <span>
                                Created To
                            </span>

                            <input
                                type="date"
                                min={
                                    filters
                                        .created_from
                                    || undefined
                                }
                                value={
                                    filters
                                        .created_to
                                }
                                onChange={(
                                    event
                                ) =>
                                    setFilters({
                                        ...filters,

                                        created_to:
                                            event
                                                .target
                                                .value,
                                    })
                                }
                            />
                        </label>
                    </div>
                )}

                {/* Result count uses Laravel pagination metadata. */}
                <div className="panel-header-small">
                    <div>
                        <strong>
                            {totalTickets}
                        </strong>{' '}
                        ticket
                        {totalTickets === 1
                            ? ''
                            : 's'}
                    </div>
                </div>

                {loading ? (
                    <div className="ticket-skeleton-list">
                        <div className="ticket-skeleton" />
                        <div className="ticket-skeleton" />
                        <div className="ticket-skeleton" />
                    </div>
                ) : (
                    <>
                        <TicketTable
                            tickets={
                                tickets
                            }
                            showOrganization={
                                isAgent
                            }
                        />

                        <TicketPagination
                            meta={
                                pagination
                            }
                            onPageChange={
                                handlePageChange
                            }
                        />
                    </>
                )}
            </section>

            {/*
            Ticket creation remains a client-only UI action.

            After creation, reload page 1 using the currently
            selected filters.
            */}
            {!isAgent && (
                <CreateTicketModal
                    open={
                        createOpen
                    }
                    onClose={() =>
                        setCreateOpen(
                            false
                        )
                    }
                    onCreated={() =>
                        loadTickets(
                            1,
                            filters
                        )
                    }
                />
            )}
        </AppShell>
    );
}