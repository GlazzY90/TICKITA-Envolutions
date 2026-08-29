import http from './http';

/*
Logic:
Provides all frontend operations related to support tickets.

Structure:
The React pages deal with user interaction, while this module owns
endpoint URLs and HTTP details.

DSA:
No custom DSA. Axios serializes query parameters and Laravel/MySQL
perform filtering.
*/

export async function fetchTickets(filters = {}) {
    const response = await http.get(
        '/api/tickets',
        {
            params: filters,
        }
    );

    return response.data.data;
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