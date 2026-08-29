import http from './api/http';

/*
Logic:
Makes the configured Axios instance available globally for development
console testing while application modules import it directly.

Structure:
bootstrap.js remains the application's initialization bridge.

DSA:
No algorithm.
*/

window.axios = http;