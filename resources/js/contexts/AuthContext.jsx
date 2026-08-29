import {
    createContext,
    useContext,
    useEffect,
    useState,
} from 'react';

import {
    fetchCurrentUser,
    loginUser,
    logoutUser,
} from '../api/auth';

/*
Logic:
Stores the current authenticated user and exposes login/logout operations.

Structure:
Authentication state must be shared by login, routing, ticket pages,
and logout controls. React Context avoids passing the user through
every component manually.

DSA:
State access/update is O(1). No search or complex data structure.
*/

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchCurrentUser()
            .then(setUser)
            .catch((error) => {
                if (error.response?.status !== 401) {
                    console.error(error);
                }

                setUser(null);
            })
            .finally(() => {
                setLoading(false);
            });
    }, []);

    async function login(credentials) {
        const authenticatedUser = await loginUser(
            credentials
        );

        setUser(authenticatedUser);

        return authenticatedUser;
    }

    async function logout() {
        await logoutUser();

        setUser(null);
    }

    return (
        <AuthContext.Provider
            value={{
                user,
                loading,
                login,
                logout,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}