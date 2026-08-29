import {
    Link,
} from 'react-router-dom';

import SlaBadge from './SlaBadge';

/*
Logic:
Renders ticket collections for both clients and agents.

Structure:
The same ticket table is reused because both roles need nearly identical
list information; agent-only organization/assignment columns are conditional.

DSA:
Rendering n tickets is O(n).
*/

function formatValue(value) {
    return value
        ?.replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) =>
            letter.toUpperCase()
        );
}

export default function TicketTable({
    tickets,
    showOrganization = false,
}) {
    if (tickets.length === 0) {
        return <p>No tickets found.</p>;
    }

    return (
        <div className="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>

                        {showOrganization && (
                            <th>Organization</th>
                        )}

                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>SLA</th>
                    </tr>
                </thead>

                <tbody>
                    {tickets.map((ticket) => (
                        <tr key={ticket.id}>
                            <td>
                                <Link
                                    to={`/tickets/${ticket.id}`}
                                >
                                    {ticket.title}
                                </Link>
                            </td>

                            {showOrganization && (
                                <td>
                                    {ticket.organization?.name}
                                </td>
                            )}

                            <td>
                                {formatValue(ticket.status)}
                            </td>

                            <td>
                                {formatValue(ticket.priority)}
                            </td>

                            <td>
                                {ticket.assigned_agent?.name
                                    ?? 'Unassigned'}
                            </td>

                            <td>
                                <SlaBadge
                                    status={ticket.sla_status}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}