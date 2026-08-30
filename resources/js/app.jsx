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

import ProtectedRoute
    from './components/ProtectedRoute';

import {
    AuthProvider,
} from './contexts/AuthContext';

import LoginPage
    from './pages/LoginPage';

import TicketDetailPage
    from './pages/TicketDetailPage';

import TicketsPage
    from './pages/TicketsPage';

/*
Logic:
Bootstraps React and exposes only the routes required by the application.

Structure:
Authentication state surrounds routing, while ProtectedRoute controls
authenticated browser navigation.

DSA:
Route matching is delegated to React Router. No custom DSA is used.
*/

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route
                        path="/login"
                        element={
                            <LoginPage />
                        }
                    />

                    <Route
                        element={
                            <ProtectedRoute />
                        }
                    >
                        <Route
                            path="/tickets"
                            element={
                                <TicketsPage />
                            }
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