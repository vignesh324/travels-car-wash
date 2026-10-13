import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/FormFields';
import type { ApiError } from '../../types';
import axios from 'axios';

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);
        try {
            await login(email, password);
            navigate('/dashboard');
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as ApiError;
                if (data?.errors) {
                    const flat: Record<string, string> = {};
                    Object.entries(data.errors).forEach(([k, v]) => { flat[k] = v[0]; });
                    setErrors(flat);
                } else {
                    setErrors({ email: data?.message ?? 'Login failed' });
                }
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8">
                <div className="text-center mb-8">
                    <div className="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl mx-auto mb-3">TC</div>
                    <h1 className="text-xl font-bold text-gray-900">Travels & Car Wash</h1>
                    <p className="text-sm text-gray-500 mt-1">Sign in to your account</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Email"
                        type="email"
                        value={email}
                        onChange={e => setEmail(e.target.value)}
                        error={errors.email}
                        required
                        autoComplete="email"
                        placeholder="admin@example.com"
                    />
                    <Input
                        label="Password"
                        type="password"
                        value={password}
                        onChange={e => setPassword(e.target.value)}
                        error={errors.password}
                        required
                        autoComplete="current-password"
                        placeholder="••••••••"
                    />
                    <Button type="submit" loading={loading} className="w-full" size="lg">
                        Sign In
                    </Button>
                </form>
            </div>
        </div>
    );
}
