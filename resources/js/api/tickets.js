import http from './http';

/*
Logic:
Responsible only for HTTP communication with ticket APIs

Structure:
Components should not need to know Axios response structures.

For paginated ticket lists, this function exposes:
- tickets
- pagination metadata
- navigation links
*/

export async function fetchTickets(
    params = {}
) {
    const response = await http.get(
        '/api/tickets',
        {
            params,
        }
    );

    return {
        tickets: response.data.data,
        meta: response.data.meta,
        links: response.data.links,
    };
}

export async function createTicket(payload) {
    const response = await http.post(
        '/api/tickets',
        payload
    );

    return response.data.data;
}

export async function fetchTicket(id) {
    const response = await http.get(
        `/api/tickets/${id}`
    );

    return response.data.data;
}

export async function updateTicket(id, payload) {
    const response = await http.patch(
        `/api/tickets/${id}`,
        payload
    );

    return response.data.data;
}

export async function addTicketMessage(
    id,
    payload
) {
    const response = await http.post(
        `/api/tickets/${id}/messages`,
        payload
    );

    return response.data.data;
}

export async function fetchSupportOptions() {
    const response = await http.get(
        '/api/support/options'
    );

    return response.data;
}