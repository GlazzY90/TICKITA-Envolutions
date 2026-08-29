import {
    useState,
} from 'react';

import {
    Navigate,
    useNavigate,
} from 'react-router-dom';

import { useAuth } from '../contexts/AuthContext';

/*
Logic:
Collects email/password and delegates authentication to AuthContext.

Structure:
The page handles presentation and user interaction only.
Sanctum/CSRF details remain in the API layer.

DSA:
No DSA. Form operations are O(1).
*/

export default function LoginPage() {
    const {
        user,
        login,
    } = useAuth();

    const navigate = useNavigate();

    const [form, setForm] = useState({
        email: '',
        password: '',
    });

    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

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

            navigate('/tickets');
        } catch (requestError) {
            setError(
                requestError.response?.data?.message
                ?? 'Login failed.'
            );
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <main className="auth-page">
            <form
                className="card form"
                onSubmit={handleSubmit}
            >
                <h1>Support Ticket Portal</h1>

                {error && (
                    <p className="error">
                        {error}
                    </p>
                )}

                <label>
                    Email
                    <input
                        type="email"
                        value={form.email}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                email: event.target.value,
                            })
                        }
                        required
                    />
                </label>

                <label>
                    Password
                    <input
                        type="password"
                        value={form.password}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                password: event.target.value,
                            })
                        }
                        required
                    />
                </label>

                <button
                    type="submit"
                    disabled={submitting}
                >
                    {submitting
                        ? 'Logging in...'
                        : 'Login'}
                </button>
            </form>
        </main>
    );
}