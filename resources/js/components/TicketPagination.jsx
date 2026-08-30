/*
Logic:
Displays pagination controls using metadata returned by Laravel.

Structure:
Pagination is its own component so TicketsPage is not responsible for
button rendering and page-navigation rules.

DSA:
All operations are constant-time O(1).
*/

export default function TicketPagination({
    meta,
    onPageChange,
}) {
    if (
        !meta
        || meta.last_page <= 1
    ) {
        return null;
    }

    return (
        <div className="ticket-pagination">
            <span>
                Page {meta.current_page}
                {' '}of{' '}
                {meta.last_page}
            </span>

            <div>
                <button
                    type="button"
                    className="btn btn-secondary"
                    disabled={
                        meta.current_page === 1
                    }
                    onClick={() =>
                        onPageChange(
                            meta.current_page - 1
                        )
                    }
                >
                    Previous
                </button>

                <button
                    type="button"
                    className="btn btn-secondary"
                    disabled={
                        meta.current_page
                        === meta.last_page
                    }
                    onClick={() =>
                        onPageChange(
                            meta.current_page + 1
                        )
                    }
                >
                    Next
                </button>
            </div>
        </div>
    );
}