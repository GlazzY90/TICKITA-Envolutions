import {
    ArrowRight,
    UserRound,
} from 'lucide-react';

import {
    Link,
} from 'react-router-dom';

import {
    PriorityBadge,
    SlaBadge,
    StatusBadge,
} from './TicketBadges';

/*
Logic:
Presents tickets as modern compact support-ticket rows instead of a
traditional dense HTML table.

Structure:
The component remains reusable for both clients and support agents.
Agent-specific organization information is enabled through a prop.

DSA:
React maps over n tickets once, therefore rendering complexity is O(n).
*/

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(
        undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        }
    ).format(new Date(value));
}

export default function TicketTable({
    tickets,
    showOrganization = false,
}) {
    if (tickets.length === 0) {
        return (
            <div className="empty-state">
                <div className="empty-state-icon">
                    <UserRound size={24} />
                </div>

                <h3>No tickets found</h3>

                <p>
                    Try changing your filters or search.
                </p>
            </div>
        );
    }

    return (
        <div className="ticket-list">
            {tickets.map((ticket) => (
                <Link
                    className="ticket-row"
                    to={`/tickets/${ticket.id}`}
                    key={ticket.id}
                >
                    <div className="ticket-row-main">
                        <div className="ticket-title-line">
                            <h3>{ticket.title}</h3>

                            <div className="ticket-row-badges">
                                <PriorityBadge
                                    priority={ticket.priority}
                                />

                                <StatusBadge
                                    status={ticket.status}
                                />
                            </div>
                        </div>

                        <p className="ticket-meta">
                            <span>
                                #{String(ticket.id).padStart(4, '0')}
                            </span>

                            <span>•</span>

                            {showOrganization && (
                                <>
                                    <span>
                                        {ticket.organization?.name}
                                    </span>

                                    <span>•</span>
                                </>
                            )}

                            <span>
                                Created {formatDate(
                                    ticket.created_at
                                )}
                            </span>
                        </p>

                        <p className="ticket-description-preview">
                            {ticket.description}
                        </p>
                    </div>

                    <div className="ticket-row-side">
                        <SlaBadge
                            status={ticket.sla_status}
                        />

                        <div className="ticket-assignment">
                            {ticket.assigned_agent
                                ? ticket.assigned_agent.name
                                : 'Unassigned'}
                        </div>

                        <ArrowRight
                            className="ticket-arrow"
                            size={18}
                        />
                    </div>
                </Link>
            ))}
        </div>
    );
}