import axios from 'axios';

/*
Logic:
Creates one HTTP client used by every frontend API module.

Structure:
Centralizing Axios configuration prevents every page from repeating
cookie, CSRF, and JSON settings.

DSA:
No DSA. Each request delegates network transport to Axios.
*/

const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },

    withCredentials: true,
    withXSRFToken: true,
});

export default http;