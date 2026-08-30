import {
    ArrowLeft,
    Building2,
    CalendarClock,
    Check,
    MessageSquare,
    Send,
    UserRound,
} from 'lucide-react';

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

import AppShell from '../components/AppShell';

import {
    PriorityBadge,
    SlaBadge,
    StatusBadge,
} from '../components/TicketBadges';

import {
    useAuth,
} from '../contexts/AuthContext';

/*
Logic:
Displays the ticket, its visible conversation, SLA information, message
composer, and support-agent management actions.

Structure:
Conversation and ticket metadata are separated visually because they have
different responsibilities. Agent-only mutation controls live in the
metadata panel while communication remains the main focus.

DSA:
Rendering m messages is O(m). No client-side filtering is performed;
Laravel already returns only messages the authenticated user may see.
*/

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        }
    ).format(new Date(value));
}

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) =>
            part[0].toUpperCase()
        )
        .join('');
}

export default function TicketDetailPage() {
    const { id } = useParams();
    const { user } = useAuth();

    const isAgent =
        user.role === 'support_agent';

    const [ticket, setTicket] =
        useState(null);

    const [options, setOptions] =
        useState({
            agents: [],
            organizations: [],
        });

    const [message, setMessage] =
        useState({
            body: '',
            visibility: 'public',
        });

    const [updateForm, setUpdateForm] =
        useState({
            status: '',
            priority: '',
            assigned_to: '',
        });

    const [loading, setLoading] =
        useState(true);

    const [sending, setSending] =
        useState(false);

    const [saving, setSaving] =
        useState(false);

    const [error, setError] =
        useState('');

    async function loadTicket() {
        setLoading(true);

        try {
            const loaded =
                await fetchTicket(id);

            setTicket(loaded);

            setUpdateForm({
                status: loaded.status,
                priority:
                    loaded.priority,
                assigned_to:
                    loaded.assigned_agent?.id
                    ?? '',
            });
        } catch (requestError) {
            if (
                requestError.response?.status
                === 403
            ) {
                setError(
                    'You do not have permission to view this ticket.'
                );
            } else {
                setError(
                    'Unable to load this ticket.'
                );
            }
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadTicket();

        if (isAgent) {
            fetchSupportOptions()
                .then(setOptions)
                .catch(() => {
                    setError(
                        'Unable to load support-agent options.'
                    );
                });
        }
    }, [id, isAgent]);

    async function handleMessageSubmit(
        event
    ) {
        event.preventDefault();

        setSending(true);
        setError('');

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
                ?? 'Unable to send message.'
            );
        } finally {
            setSending(false);
        }
    }

    async function handleTicketUpdate(
        event
    ) {
        event.preventDefault();

        setSaving(true);
        setError('');

        try {
            await updateTicket(
                id,
                {
                    status:
                        updateForm.status,

                    priority:
                        updateForm.priority,

                    assigned_to:
                        updateForm.assigned_to
                        === ''
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
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return (
            <AppShell>
                <div className="detail-loading">
                    Loading ticket...
                </div>
            </AppShell>
        );
    }

    if (!ticket) {
        return (
            <AppShell>
                <div className="panel error-page">
                    <h2>
                        Ticket unavailable
                    </h2>

                    <p>{error}</p>

                    <Link
                        className="btn btn-secondary"
                        to="/tickets"
                    >
                        Back to Tickets
                    </Link>
                </div>
            </AppShell>
        );
    }

    return (
        <AppShell>
            <div className="detail-breadcrumb">
                <Link to="/tickets">
                    <ArrowLeft size={16} />
                    Tickets
                </Link>

                <span>/</span>

                <span>
                    #{String(ticket.id).padStart(
                        4,
                        '0'
                    )}
                </span>
            </div>

            {error && (
                <div className="alert alert-error">
                    {error}
                </div>
            )}

            <div className="ticket-detail-heading">
                <div>
                    <p className="ticket-number">
                        TICKET #
                        {String(ticket.id).padStart(
                            4,
                            '0'
                        )}
                    </p>

                    <h1>{ticket.title}</h1>

                    <div className="heading-badges">
                        <StatusBadge
                            status={ticket.status}
                        />

                        <PriorityBadge
                            priority={ticket.priority}
                        />

                        <SlaBadge
                            status={ticket.sla_status}
                        />
                    </div>
                </div>
            </div>

            <div className="ticket-detail-layout">
                <main className="detail-main-column">
                    <section className="panel ticket-description-card">
                        <h2>Issue Description</h2>

                        <p>
                            {ticket.description}
                        </p>
                    </section>

                    <section className="panel conversation-panel">
                        <div className="section-title">
                            <div>
                                <MessageSquare
                                    size={19}
                                />

                                <h2>
                                    Conversation
                                </h2>
                            </div>

                            <span>
                                {
                                    ticket.messages
                                        .length
                                }{' '}
                                message
                                {ticket.messages
                                    .length === 1
                                    ? ''
                                    : 's'}
                            </span>
                        </div>

                        <div className="conversation-list">
                            {ticket.messages.length
                                === 0 && (
                                <div className="empty-conversation">
                                    No replies yet.
                                </div>
                            )}

                            {ticket.messages.map(
                                (item) => (
                                    <article
                                        className={
                                            item.visibility
                                            === 'internal'
                                                ? 'conversation-message internal-message'
                                                : 'conversation-message'
                                        }
                                        key={item.id}
                                    >
                                        <div className="message-avatar">
                                            {initials(
                                                item.author
                                                    .name
                                            )}
                                        </div>

                                        <div className="message-content">
                                            <header>
                                                <div>
                                                    <strong>
                                                        {
                                                            item.author
                                                                .name
                                                        }
                                                    </strong>

                                                    <span className="author-role">
                                                        {item.author
                                                            .role
                                                            ===
                                                        'support_agent'
                                                            ? 'Support Agent'
                                                            : 'Client'}
                                                    </span>

                                                    {item.visibility
                                                        ===
                                                        'internal'
                                                        && (
                                                            <span className="internal-label">
                                                                Internal
                                                                note
                                                            </span>
                                                        )}
                                                </div>

                                                <time>
                                                    {formatDate(
                                                        item.created_at
                                                    )}
                                                </time>
                                            </header>

                                            <p>
                                                {
                                                    item.body
                                                }
                                            </p>
                                        </div>
                                    </article>
                                )
                            )}
                        </div>

                        <form
                            className="reply-composer"
                            onSubmit={
                                handleMessageSubmit
                            }
                        >
                            {isAgent && (
                                <div className="composer-tabs">
                                    <button
                                        type="button"
                                        className={
                                            message.visibility
                                            === 'public'
                                                ? 'composer-tab active'
                                                : 'composer-tab'
                                        }
                                        onClick={() =>
                                            setMessage({
                                                ...message,
                                                visibility:
                                                    'public',
                                            })
                                        }
                                    >
                                        Public Reply
                                    </button>

                                    <button
                                        type="button"
                                        className={
                                            message.visibility
                                            === 'internal'
                                                ? 'composer-tab internal active'
                                                : 'composer-tab internal'
                                        }
                                        onClick={() =>
                                            setMessage({
                                                ...message,
                                                visibility:
                                                    'internal',
                                            })
                                        }
                                    >
                                        Internal Note
                                    </button>
                                </div>
                            )}

                            <textarea
                                placeholder={
                                    message.visibility
                                    === 'internal'
                                        ? 'Write an internal note visible only to support agents...'
                                        : 'Write your reply...'
                                }
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

                            <div className="composer-footer">
                                <span>
                                    {message.visibility
                                    === 'internal'
                                        ? 'Only support agents can see this note.'
                                        : 'This reply will be visible to the client.'}
                                </span>

                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={sending}
                                >
                                    <Send size={16} />

                                    {sending
                                        ? 'Sending...'
                                        : message.visibility
                                        === 'internal'
                                            ? 'Add Note'
                                            : 'Send Reply'}
                                </button>
                            </div>
                        </form>
                    </section>
                </main>

                <aside className="detail-side-column">
                    <section className="panel ticket-info-panel">
                        <h2>
                            Ticket Information
                        </h2>

                        <div className="info-row">
                            <Building2 size={17} />

                            <div>
                                <span>
                                    Organization
                                </span>

                                <strong>
                                    {
                                        ticket.organization
                                            ?.name
                                    }
                                </strong>
                            </div>
                        </div>

                        <div className="info-row">
                            <UserRound size={17} />

                            <div>
                                <span>
                                    Requester
                                </span>

                                <strong>
                                    {
                                        ticket.creator
                                            ?.name
                                    }
                                </strong>
                            </div>
                        </div>

                        <div className="info-row">
                            <UserRound size={17} />

                            <div>
                                <span>
                                    Assigned agent
                                </span>

                                <strong>
                                    {ticket
                                        .assigned_agent
                                        ?.name
                                        ?? 'Unassigned'}
                                </strong>
                            </div>
                        </div>

                        <div className="info-row">
                            <CalendarClock
                                size={17}
                            />

                            <div>
                                <span>
                                    Created
                                </span>

                                <strong>
                                    {formatDate(
                                        ticket.created_at
                                    )}
                                </strong>
                            </div>
                        </div>
                    </section>

                    <section className="panel sla-panel">
                        <div className="panel-title-row">
                            <h2>SLA</h2>

                            <SlaBadge
                                status={
                                    ticket.sla_status
                                }
                            />
                        </div>

                        <div className="sla-detail">
                            <span>
                                Initial priority
                            </span>

                            <PriorityBadge
                                priority={
                                    ticket.initial_priority
                                }
                            />
                        </div>

                        <div className="sla-detail vertical">
                            <span>
                                Deadline
                            </span>

                            <strong>
                                {formatDate(
                                    ticket.sla_due_at
                                )}
                            </strong>
                        </div>

                        <p className="sla-note">
                            SLA is based on the
                            ticket's initial priority
                            and is not recalculated
                            when priority changes.
                        </p>
                    </section>

                    {isAgent && (
                        <section className="panel support-controls">
                            <h2>
                                Support Controls
                            </h2>

                            <form
                                onSubmit={
                                    handleTicketUpdate
                                }
                            >
                                <label className="field">
                                    <span>Status</span>

                                    <select
                                        value={
                                            updateForm.status
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setUpdateForm({
                                                ...updateForm,
                                                status:
                                                    event
                                                        .target
                                                        .value,
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

                                <label className="field">
                                    <span>
                                        Current Priority
                                    </span>

                                    <select
                                        value={
                                            updateForm.priority
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setUpdateForm({
                                                ...updateForm,
                                                priority:
                                                    event
                                                        .target
                                                        .value,
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

                                <label className="field">
                                    <span>
                                        Assigned Agent
                                    </span>

                                    <select
                                        value={
                                            updateForm.assigned_to
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setUpdateForm({
                                                ...updateForm,
                                                assigned_to:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                    >
                                        <option value="">
                                            Unassigned
                                        </option>

                                        {options.agents.map(
                                            (
                                                agent
                                            ) => (
                                                <option
                                                    key={
                                                        agent.id
                                                    }
                                                    value={
                                                        agent.id
                                                    }
                                                >
                                                    {
                                                        agent.name
                                                    }
                                                </option>
                                            )
                                        )}
                                    </select>
                                </label>

                                <button
                                    type="submit"
                                    className="btn btn-primary btn-full"
                                    disabled={saving}
                                >
                                    <Check size={16} />

                                    {saving
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </button>
                            </form>
                        </section>
                    )}
                </aside>
            </div>
        </AppShell>
    );
}