import './bootstrap';
import '../css/app.css';

import React from 'react';
import {
    createRoot,
} from 'react-dom/client';

import {
    BrowserRouter,
    Navigate,
    Route,
    Routes,
} from 'react-router-dom';

import ProtectedRoute from './components/ProtectedRoute';
import {
    AuthProvider,
} from './contexts/AuthContext';

import LoginPage from './pages/LoginPage';
import TicketDetailPage from './pages/TicketDetailPage';
import TicketsPage from './pages/TicketsPage';

/*
Logic:
Bootstraps React and defines the three required application routes.

Structure:
Routing stays at the top-level application entry point.
AuthProvider wraps the application so every route can access user state.

DSA:
React Router performs route matching. No application-specific DSA.
*/

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route
                        path="/"
                        element={
                            <Navigate
                                to="/tickets"
                                replace
                            />
                        }
                    />

                    <Route
                        path="/login"
                        element={<LoginPage />}
                    />

                    <Route
                        element={
                            <ProtectedRoute />
                        }
                    >
                        <Route
                            path="/tickets"
                            element={<TicketsPage />}
                        />

                        <Route
                            path="/tickets/:id"
                            element={
                                <TicketDetailPage />
                            }
                        />
                    </Route>

                    <Route
                        path="*"
                        element={
                            <Navigate
                                to="/tickets"
                                replace
                            />
                        }
                    />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

createRoot(
    document.getElementById('app')
).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);