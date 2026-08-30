import {
    LogOut,
    MessageSquareText,
    Ticket,
} from 'lucide-react';

import {
    NavLink,
    useNavigate,
} from 'react-router-dom';

import { useAuth } from '../contexts/AuthContext';

import NotificationBell
    from './NotificationBell';

/*
Logic:
Provides the shared application frame: brand, navigation, current user,
organization identity, and logout action.

Structure:
A reusable shell prevents each page from rebuilding the same navigation
and account controls. The actual ticket pages remain responsible only
for their own content.

DSA:
No significant algorithm. Initial generation is O(n) where n is the
small number of words in the user's name.
*/

function getInitials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

export default function AppShell({ children }) {
    const {
        user,
        logout,
    } = useAuth();

    const navigate = useNavigate();

    async function handleLogout() {
        await logout();
        navigate('/login');
    }

    const roleLabel = user.role === 'support_agent'
        ? 'Support Agent'
        : 'Client User';

    return (
        <div className="app-shell">
            <aside className="sidebar">
                <div>
                    <div className="brand">
                        <div className="brand-mark">
                            <MessageSquareText size={21} />
                        </div>

                        <span>TICKITA</span>
                    </div>

                    <p className="sidebar-label">
                        WORKSPACE
                    </p>

                    <nav className="sidebar-nav">
                        <NavLink
                            to="/tickets"
                            className={({ isActive }) =>
                                `sidebar-link ${
                                    isActive ? 'active' : ''
                                }`
                            }
                        >
                            <Ticket size={18} />
                            <span>Tickets</span>
                        </NavLink>
                    </nav>
                </div>

                <div className="sidebar-footer">
                    <div className="sidebar-profile">
                        <div className="avatar">
                            {getInitials(user.name)}
                        </div>

                        <div className="sidebar-profile-text">
                            <strong>{user.name}</strong>

                            <span>
                                {roleLabel}
                            </span>
                        </div>
                    </div>

                    <button
                        className="sidebar-logout"
                        type="button"
                        onClick={handleLogout}
                        title="Sign out"
                    >
                        <LogOut size={17} />
                    </button>
                </div>
            </aside>

            <section className="workspace">
                <header className="workspace-topbar">
                    <div />

                    <div className="topbar-actions">
                        <NotificationBell />

                        <div className="topbar-user">
                            <div className="avatar">
                                {getInitials(user.name)}
                            </div>
                        </div>
                    </div>
                </header>

                <header className="mobile-topbar">
                    <div className="brand mobile-brand">
                        <div className="brand-mark">
                            <MessageSquareText size={19} />
                        </div>

                        <span>TICKITA</span>
                    </div>

                    <div className="mobile-topbar-actions">
                        <NotificationBell />

                        <button
                            className="icon-button"
                            type="button"
                            onClick={handleLogout}
                            aria-label="Sign out"
                            title="Sign out"
                        >
                            <LogOut size={18} />
                        </button>
                    </div>
                </header>

                <div className="workspace-content">
                    {children}
                </div>
            </section>
        </div>
    );
}