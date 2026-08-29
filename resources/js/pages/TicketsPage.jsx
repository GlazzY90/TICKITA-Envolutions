import {
    useEffect,
    useState,
} from 'react';

import {
    createTicket,
    fetchSupportOptions,
    fetchTickets,
} from '../api/tickets';

import TicketTable from '../components/TicketTable';
import { useAuth } from '../contexts/AuthContext';

/*
Logic:
Provides the role-dependent ticket-list experience.
Clients can create/view tickets.
Agents can search/filter tickets from all organizations.

Structure:
One page is used for both roles because the underlying resource is the
same; role-specific controls are conditionally rendered.

DSA:
Rendering n tickets is O(n).
Filters are not applied in JavaScript; they are sent to MySQL-backed API
queries, avoiding unnecessary client-side scanning.
*/

const emptyFilters = {
    search: '',
    organization_id: '',
    status: '',
    priority: '',
};

export default function TicketsPage() {
    const {
        user,
        logout,
    } = useAuth();

    const isAgent = user.role === 'support_agent';

    const [tickets, setTickets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const [filters, setFilters] =
        useState(emptyFilters);

    const [options, setOptions] = useState({
        organizations: [],
        agents: [],
    });

    const [newTicket, setNewTicket] = useState({
        title: '',
        description: '',
        priority: 'normal',
    });

    async function loadTickets(
        selectedFilters = filters
    ) {
        setLoading(true);
        setError('');

        try {
            const cleaned = Object.fromEntries(
                Object.entries(selectedFilters)
                    .filter(([, value]) => value !== '')
            );

            setTickets(
                await fetchTickets(cleaned)
            );
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Unable to load tickets.'
            );
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadTickets(emptyFilters);

        if (isAgent) {
            fetchSupportOptions()
                .then(setOptions)
                .catch(console.error);
        }
    }, []);

    async function handleFilter(event) {
        event.preventDefault();

        await loadTickets(filters);
    }

    async function clearFilters() {
        setFilters(emptyFilters);

        await loadTickets(emptyFilters);
    }

    async function handleCreate(event) {
        event.preventDefault();

        setError('');

        try {
            await createTicket(newTicket);

            setNewTicket({
                title: '',
                description: '',
                priority: 'normal',
            });

            await loadTickets();
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Unable to create ticket.'
            );
        }
    }

    return (
        <main className="container">
            <header className="page-header">
                <div>
                    <h1>
                        {isAgent
                            ? 'Support Tickets'
                            : 'My Organization Tickets'}
                    </h1>

                    <p>
                        Logged in as {user.name}
                    </p>
                </div>

                <button onClick={logout}>
                    Logout
                </button>
            </header>

            {error && (
                <p className="error">
                    {error}
                </p>
            )}

            {!isAgent && (
                <section className="card">
                    <h2>Create Ticket</h2>

                    <form
                        className="form"
                        onSubmit={handleCreate}
                    >
                        <label>
                            Title
                            <input
                                value={newTicket.title}
                                onChange={(event) =>
                                    setNewTicket({
                                        ...newTicket,
                                        title:
                                            event.target.value,
                                    })
                                }
                                required
                            />
                        </label>

                        <label>
                            Description
                            <textarea
                                value={
                                    newTicket.description
                                }
                                onChange={(event) =>
                                    setNewTicket({
                                        ...newTicket,
                                        description:
                                            event.target.value,
                                    })
                                }
                                required
                            />
                        </label>

                        <label>
                            Priority
                            <select
                                value={newTicket.priority}
                                onChange={(event) =>
                                    setNewTicket({
                                        ...newTicket,
                                        priority:
                                            event.target.value,
                                    })
                                }
                            >
                                <option value="low">
                                    Low
                                </option>

                                <option value="normal">
                                    Normal
                                </option>

                                <option value="high">
                                    High
                                </option>
                            </select>
                        </label>

                        <button type="submit">
                            Create Ticket
                        </button>
                    </form>
                </section>
            )}

            {isAgent && (
                <section className="card">
                    <h2>Filter Tickets</h2>

                    <form
                        className="filter-grid"
                        onSubmit={handleFilter}
                    >
                        <input
                            placeholder="Search title or description"
                            value={filters.search}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    search:
                                        event.target.value,
                                })
                            }
                        />

                        <select
                            value={
                                filters.organization_id
                            }
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    organization_id:
                                        event.target.value,
                                })
                            }
                        >
                            <option value="">
                                All organizations
                            </option>

                            {options.organizations.map(
                                (organization) => (
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

                        <select
                            value={filters.status}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    status:
                                        event.target.value,
                                })
                            }
                        >
                            <option value="">
                                All statuses
                            </option>
                            <option value="open">
                                Open
                            </option>
                            <option value="in_progress">
                                In Progress
                            </option>
                            <option value="resolved">
                                Resolved
                            </option>
                            <option value="closed">
                                Closed
                            </option>
                        </select>

                        <select
                            value={filters.priority}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    priority:
                                        event.target.value,
                                })
                            }
                        >
                            <option value="">
                                All priorities
                            </option>
                            <option value="low">
                                Low
                            </option>
                            <option value="normal">
                                Normal
                            </option>
                            <option value="high">
                                High
                            </option>
                        </select>

                        <button type="submit">
                            Apply
                        </button>

                        <button
                            type="button"
                            onClick={clearFilters}
                        >
                            Clear
                        </button>
                    </form>
                </section>
            )}

            <section className="card">
                {loading ? (
                    <p>Loading tickets...</p>
                ) : (
                    <TicketTable
                        tickets={tickets}
                        showOrganization={isAgent}
                    />
                )}
            </section>
        </main>
    );
}