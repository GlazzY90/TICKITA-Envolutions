import {
    useEffect,
    useState,
} from 'react';

import {
    Link,
    useParams,
} from 'react-router-dom';

import {
    addTicketMessage,
    fetchSupportOptions,
    fetchTicket,
    updateTicket,
} from '../api/tickets';

import SlaBadge from '../components/SlaBadge';
import { useAuth } from '../contexts/AuthContext';

/*
Logic:
Displays ticket details, conversation, reply controls, and agent-only
ticket-management controls.

Structure:
The same page is used for both roles. The API determines what data
the user may receive; React only conditionally displays permitted controls.

DSA:
Rendering m conversation messages is O(m).
No client-side search/sorting is performed.
Messages arrive chronologically ordered from MySQL.
*/

function label(value) {
    return value
        ?.replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) =>
            letter.toUpperCase()
        );
}

export default function TicketDetailPage() {
    const { id } = useParams();

    const { user } = useAuth();

    const isAgent = user.role === 'support_agent';

    const [ticket, setTicket] = useState(null);
    const [error, setError] = useState('');

    const [message, setMessage] = useState({
        body: '',
        visibility: 'public',
    });

    const [updateForm, setUpdateForm] = useState({
        status: 'open',
        priority: 'normal',
        assigned_to: '',
    });

    const [options, setOptions] = useState({
        agents: [],
        organizations: [],
    });

    async function loadTicket() {
        try {
            const loaded = await fetchTicket(id);

            setTicket(loaded);

            setUpdateForm({
                status: loaded.status,
                priority: loaded.priority,
                assigned_to:
                    loaded.assigned_agent?.id ?? '',
            });
        } catch (requestError) {
            setError(
                requestError.response?.status === 403
                    ? 'You are not allowed to view this ticket.'
                    : 'Unable to load ticket.'
            );
        }
    }

    useEffect(() => {
        loadTicket();

        if (isAgent) {
            fetchSupportOptions()
                .then(setOptions)
                .catch(console.error);
        }
    }, [id]);

    async function handleMessage(event) {
        event.preventDefault();

        try {
            await addTicketMessage(
                id,
                message
            );

            setMessage({
                body: '',
                visibility: 'public',
            });

            await loadTicket();
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Unable to add message.'
            );
        }
    }

    async function handleUpdate(event) {
        event.preventDefault();

        try {
            await updateTicket(
                id,
                {
                    status: updateForm.status,
                    priority: updateForm.priority,
                    assigned_to:
                        updateForm.assigned_to === ''
                            ? null
                            : Number(
                                updateForm.assigned_to
                            ),
                }
            );

            await loadTicket();
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Unable to update ticket.'
            );
        }
    }

    if (error && !ticket) {
        return (
            <main className="container">
                <p className="error">
                    {error}
                </p>

                <Link to="/tickets">
                    Back to tickets
                </Link>
            </main>
        );
    }

    if (!ticket) {
        return (
            <main className="container">
                <p>Loading ticket...</p>
            </main>
        );
    }

    return (
        <main className="container">
            <p>
                <Link to="/tickets">
                    ← Back to tickets
                </Link>
            </p>

            {error && (
                <p className="error">
                    {error}
                </p>
            )}

            <section className="card">
                <h1>{ticket.title}</h1>

                <p>
                    <strong>Organization:</strong>{' '}
                    {ticket.organization?.name}
                </p>

                <p>
                    <strong>Status:</strong>{' '}
                    {label(ticket.status)}
                </p>

                <p>
                    <strong>Priority:</strong>{' '}
                    {label(ticket.priority)}
                </p>

                <p>
                    <strong>Initial priority:</strong>{' '}
                    {label(ticket.initial_priority)}
                </p>

                <p>
                    <strong>Assigned:</strong>{' '}
                    {ticket.assigned_agent?.name
                        ?? 'Unassigned'}
                </p>

                <p>
                    <strong>SLA:</strong>{' '}
                    <SlaBadge
                        status={ticket.sla_status}
                    />
                </p>

                <p>
                    <strong>SLA deadline:</strong>{' '}
                    {new Date(
                        ticket.sla_due_at
                    ).toLocaleString()}
                </p>

                <h2>Description</h2>

                <p className="pre-wrap">
                    {ticket.description}
                </p>
            </section>

            {isAgent && (
                <section className="card">
                    <h2>Support Controls</h2>

                    <form
                        className="form"
                        onSubmit={handleUpdate}
                    >
                        <label>
                            Status
                            <select
                                value={updateForm.status}
                                onChange={(event) =>
                                    setUpdateForm({
                                        ...updateForm,
                                        status:
                                            event.target.value,
                                    })
                                }
                            >
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
                        </label>

                        <label>
                            Priority
                            <select
                                value={updateForm.priority}
                                onChange={(event) =>
                                    setUpdateForm({
                                        ...updateForm,
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

                        <label>
                            Assigned Agent
                            <select
                                value={
                                    updateForm.assigned_to
                                }
                                onChange={(event) =>
                                    setUpdateForm({
                                        ...updateForm,
                                        assigned_to:
                                            event.target.value,
                                    })
                                }
                            >
                                <option value="">
                                    Unassigned
                                </option>

                                {options.agents.map(
                                    (agent) => (
                                        <option
                                            key={agent.id}
                                            value={agent.id}
                                        >
                                            {agent.name}
                                        </option>
                                    )
                                )}
                            </select>
                        </label>

                        <button type="submit">
                            Save Changes
                        </button>
                    </form>
                </section>
            )}

            <section className="card">
                <h2>Conversation</h2>

                {ticket.messages.length === 0 && (
                    <p>No replies yet.</p>
                )}

                <div className="conversation">
                    {ticket.messages.map(
                        (item) => (
                            <article
                                key={item.id}
                                className={
                                    item.visibility
                                    === 'internal'
                                        ? 'message internal'
                                        : 'message'
                                }
                            >
                                <div>
                                    <strong>
                                        {
                                            item.author
                                                .name
                                        }
                                    </strong>

                                    {' · '}

                                    {new Date(
                                        item.created_at
                                    ).toLocaleString()}

                                    {item.visibility
                                        === 'internal'
                                        && (
                                            <>
                                                {' · '}
                                                <strong>
                                                    INTERNAL
                                                </strong>
                                            </>
                                        )}
                                </div>

                                <p className="pre-wrap">
                                    {item.body}
                                </p>
                            </article>
                        )
                    )}
                </div>

                <form
                    className="form"
                    onSubmit={handleMessage}
                >
                    <label>
                        Reply
                        <textarea
                            value={message.body}
                            onChange={(event) =>
                                setMessage({
                                    ...message,
                                    body:
                                        event.target.value,
                                })
                            }
                            required
                        />
                    </label>

                    {isAgent && (
                        <label>
                            Visibility
                            <select
                                value={
                                    message.visibility
                                }
                                onChange={(event) =>
                                    setMessage({
                                        ...message,
                                        visibility:
                                            event.target.value,
                                    })
                                }
                            >
                                <option value="public">
                                    Visible to client
                                </option>

                                <option value="internal">
                                    Internal note
                                </option>
                            </select>
                        </label>
                    )}

                    <button type="submit">
                        Add Reply
                    </button>
                </form>
            </section>
        </main>
    );
}