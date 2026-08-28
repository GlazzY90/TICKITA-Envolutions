import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';

function App() {
    return (
        <main>
            <h1>Support Ticket Portal</h1>
            <p>Laravel API + React frontend is working.</p>
        </main>
    );
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);