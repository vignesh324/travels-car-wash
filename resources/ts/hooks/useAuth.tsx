import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { authApi } from '../api/auth';
import type { User, BusinessProfile } from '../types';

interface AuthContextType {
    user: User | null;
    business: BusinessProfile | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [business, setBusiness] = useState<BusinessProfile | null>(null);
    const [loading, setLoading] = useState(true);

    const refreshUser = async () => {
        try {
            const { data } = await authApi.me();
            setUser(data.user);
            setBusiness(data.business);
        } catch {
            setUser(null);
            setBusiness(null);
        }
    };

    useEffect(() => {
        refreshUser().finally(() => setLoading(false));
    }, []);

    const login = async (email: string, password: string) => {
        await authApi.login(email, password);
        await refreshUser();
    };

    const logout = async () => {
        await authApi.logout();
        setUser(null);
        setBusiness(null);
    };

    return (
        <AuthContext.Provider value={{ user, business, loading, login, logout, refreshUser }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
}
