import http from './http';

/*
Logic:
Contains frontend functions for authentication API calls.

Structure:
Pages do not need to know endpoint URLs or Axios response shapes.
That knowledge stays in the API layer.

DSA:
No DSA. All operations are network requests.
*/

export async function fetchCurrentUser() {
    const response = await http.get('/api/me');

    return response.data.data;
}

export async function loginUser(credentials) {
    await http.get('/sanctum/csrf-cookie');

    const response = await http.post(
        '/api/login',
        credentials
    );

    return response.data.data;
}

export async function logoutUser() {
    await http.post('/api/logout');
}