import {
    AlertCircle,
    CheckCircle2,
    Clock3,
} from 'lucide-react';

/*
Logic:
Normalizes ticket status, priority, and SLA values into consistent
visual badges.

Structure:
The badge system is centralized so ticket lists and detail pages use
the exact same colors, labels, and meanings.

DSA:
Each value is retrieved from a fixed lookup object in O(1).
*/

const statusLabels = {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
};

const priorityLabels = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
};

const slaLabels = {
    on_track: 'On Track',
    due_soon: 'Due Soon',
    overdue: 'Overdue',
    completed: 'Completed',
};

export function StatusBadge({ status }) {
    return (
        <span className={`badge badge-status-${status}`}>
            {statusLabels[status] ?? status}
        </span>
    );
}

export function PriorityBadge({ priority }) {
    return (
        <span className={`badge badge-priority-${priority}`}>
            {priorityLabels[priority] ?? priority}
        </span>
    );
}

export function SlaBadge({ status }) {
    const Icon = status === 'overdue'
        ? AlertCircle
        : status === 'completed'
            ? CheckCircle2
            : Clock3;

    return (
        <span className={`sla-badge sla-${status}`}>
            <Icon size={14} />

            {slaLabels[status] ?? status}
        </span>
    );
}