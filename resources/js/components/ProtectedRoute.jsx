import {
    Navigate,
    Outlet,
} from 'react-router-dom';

import { useAuth } from '../contexts/AuthContext';

/*
Logic:
Prevents unauthenticated users from rendering protected React pages.

Structure:
Frontend route protection is centralized instead of duplicated in pages.
Backend authorization remains authoritative.

DSA:
Constant-time check of the current user state: O(1).
*/

export default function ProtectedRoute() {
    const {
        user,
        loading,
    } = useAuth();

    if (loading) {
        return <p>Loading...</p>;
    }

    if (!user) {
        return (
            <Navigate
                to="/login"
                replace
            />
        );
    }

    return <Outlet />;
}