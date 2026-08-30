import {
    X,
} from 'lucide-react';

import {
    useState,
} from 'react';

import {
    createTicket,
} from '../api/tickets';

/*
Logic:
Provides the client's new-ticket workflow inside a modal without taking
the user away from the ticket list.

Structure:
Ticket creation is separated from TicketsPage because the form has its
own state, validation feedback, submit lifecycle, and presentation.

DSA:
No meaningful DSA. Form operations are constant-time O(1).
*/

const initialForm = {
    title: '',
    description: '',
    priority: 'normal',
};

export default function CreateTicketModal({
    open,
    onClose,
    onCreated,
}) {
    const [form, setForm] = useState(initialForm);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    if (!open) {
        return null;
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setSubmitting(true);
        setError('');

        try {
            await createTicket(form);

            setForm(initialForm);

            await onCreated();

            onClose();
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Unable to create ticket.'
            );
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div
            className="modal-backdrop"
            onMouseDown={onClose}
        >
            <div
                className="modal-card"
                onMouseDown={(event) =>
                    event.stopPropagation()
                }
            >
                <header className="modal-header">
                    <div>
                        <h2>Create New Ticket</h2>

                        <p>
                            Describe the issue so the
                            support team can help you.
                        </p>
                    </div>

                    <button
                        type="button"
                        className="icon-button"
                        onClick={onClose}
                    >
                        <X size={19} />
                    </button>
                </header>

                <form
                    className="ticket-form"
                    onSubmit={handleSubmit}
                >
                    {error && (
                        <div className="alert alert-error">
                            {error}
                        </div>
                    )}

                    <label className="field">
                        <span>Subject</span>

                        <input
                            type="text"
                            placeholder="Brief summary of your issue"
                            value={form.title}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    title: event.target.value,
                                })
                            }
                            maxLength={255}
                            required
                        />
                    </label>

                    <label className="field">
                        <span>Description</span>

                        <textarea
                            placeholder="Describe what happened, what you expected, and any useful context..."
                            value={form.description}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    description:
                                        event.target.value,
                                })
                            }
                            required
                        />
                    </label>

                    <label className="field">
                        <span>Priority</span>

                        <select
                            value={form.priority}
                            onChange={(event) =>
                                setForm({
                                    ...form,
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

                        <small>
                            SLA deadline is calculated
                            from this initial priority.
                        </small>
                    </label>

                    <div className="modal-actions">
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={onClose}
                        >
                            Cancel
                        </button>

                        <button
                            className="btn btn-primary"
                            type="submit"
                            disabled={submitting}
                        >
                            {submitting
                                ? 'Creating...'
                                : 'Create Ticket'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}