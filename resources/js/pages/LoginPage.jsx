import {
    ArrowRight,
    CheckCircle2,
    MessageSquareText,
} from 'lucide-react';

import {
    useState,
} from 'react';

import {
    Navigate,
    useNavigate,
} from 'react-router-dom';

import {
    useAuth,
} from '../contexts/AuthContext';

/*
Logic:
Authenticates users and sends successful sessions into the ticket workspace.

Structure:
The visual page remains separate from authentication API implementation.
AuthContext owns authentication state while this page only handles form UX.

DSA:
No meaningful algorithm. Form updates and state transitions are O(1).
*/

export default function LoginPage() {
    const {
        user,
        loading,
        login,
    } = useAuth();

    const navigate =
        useNavigate();

    const [form, setForm] =
        useState({
            email: '',
            password: '',
        });

    const [submitting, setSubmitting] =
        useState(false);

    const [error, setError] =
        useState('');

    if (loading) {
        return (
            <div className="login-loading">
                Loading...
            </div>
        );
    }

    if (user) {
        return (
            <Navigate
                to="/tickets"
                replace
            />
        );
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setSubmitting(true);
        setError('');

        try {
            await login(form);

            navigate(
                '/tickets',
                {
                    replace: true,
                }
            );
        } catch (requestError) {
            setError(
                requestError.response?.data
                    ?.errors?.email?.[0]
                ?? requestError.response
                    ?.data?.message
                ?? 'Unable to log in.'
            );
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <main className="login-page">
            <section className="login-brand-panel">
                <div className="login-brand-content">
                    <div className="brand large">
                        <div className="brand-mark">
                            <MessageSquareText
                                size={24}
                            />
                        </div>

                        <span>TICKITA</span>
                    </div>

                    <div className="login-copy">
                        <p className="eyebrow light">
                            SUPPORT PORTAL
                        </p>

                        <h1>
                            Support that stays
                            organized.
                        </h1>

                        <p>
                            Create, track, and resolve
                            support requests with clear
                            ownership, conversations,
                            and SLA visibility.
                        </p>
                    </div>

                    <div className="login-features">
                        <div>
                            <CheckCircle2
                                size={18}
                            />
                            Clear ticket lifecycle
                        </div>

                        <div>
                            <CheckCircle2
                                size={18}
                            />
                            SLA tracking
                        </div>

                        <div>
                            <CheckCircle2
                                size={18}
                            />
                            Secure client conversations
                        </div>
                    </div>
                </div>
            </section>

            <section className="login-form-panel">
                <form
                    className="login-card"
                    onSubmit={handleSubmit}
                >
                    <div className="login-card-heading">
                        <h2>Welcome back</h2>

                        <p>
                            Sign in to access your
                            support workspace.
                        </p>
                    </div>

                    {error && (
                        <div className="alert alert-error">
                            {error}
                        </div>
                    )}

                    <label className="field">
                        <span>Email address</span>

                        <input
                            type="email"
                            autoComplete="email"
                            placeholder="you@example.com"
                            value={form.email}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    email:
                                        event.target.value,
                                })
                            }
                            required
                        />
                    </label>

                    <label className="field">
                        <span>Password</span>

                        <input
                            type="password"
                            autoComplete="current-password"
                            placeholder="Enter your password"
                            value={form.password}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    password:
                                        event.target.value,
                                })
                            }
                            required
                        />
                    </label>

                    <button
                        type="submit"
                        className="btn btn-primary login-button"
                        disabled={submitting}
                    >
                        {submitting
                            ? 'Signing in...'
                            : 'Sign In'}

                        {!submitting && (
                            <ArrowRight
                                size={17}
                            />
                        )}
                    </button>
                </form>
            </section>
        </main>
    );
}