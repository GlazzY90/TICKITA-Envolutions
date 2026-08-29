/*
Logic:
Displays the SLA state returned by Laravel.

Structure:
The frontend does not recalculate SLA. Laravel remains the single
source of truth for business rules.

DSA:
O(1) label lookup.
*/

const labels = {
    on_track: 'On Track',
    due_soon: 'Due Soon',
    overdue: 'Overdue',
    completed: 'Completed',
};

export default function SlaBadge({ status }) {
    return (
        <span className={`badge sla-${status}`}>
            {labels[status] ?? status}
        </span>
    );
}