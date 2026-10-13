import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { AppLayout } from './layouts/AppLayout';
import LoginPage from './pages/auth/LoginPage';
import DashboardPage from './pages/DashboardPage';
import VehiclesPage from './pages/vehicles/VehiclesPage';
import CustomersPage from './pages/customers/CustomersPage';
import SettingsPage from './pages/settings/SettingsPage';
import { PlaceholderPage } from './components/ui/PlaceholderPage';
import TripsPage from './pages/trips/TripsPage';
import ExpensesPage from './pages/expenses/ExpensesPage';
import InvoicesPage from './pages/invoices/InvoicesPage';
import PaymentsPage from './pages/payments/PaymentsPage';
import CarWashPage from './pages/car-wash/CarWashPage';
import ReportsPage from './pages/reports/ReportsPage';
import ActivityLogsPage from './pages/activity-logs/ActivityLogsPage';
import type { ReactNode } from 'react';
import { appBasePath } from './utils/appBasePath';

function RequireAuth({ children }: { children: ReactNode }) {
    const { user, loading } = useAuth();
    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <div className="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                    <p className="text-sm text-gray-500">Loading...</p>
                </div>
            </div>
        );
    }
    return user ? <>{children}</> : <Navigate to="/login" replace />;
}

function AppRoutes() {
    const { user } = useAuth();

    return (
        <Routes>
            <Route path="/login" element={user ? <Navigate to="/dashboard" replace /> : <LoginPage />} />

            <Route path="/" element={
                user ? <Navigate to="/dashboard" replace /> : <Navigate to="/login" replace />
            } />

            <Route path="/dashboard" element={
                <RequireAuth><AppLayout><DashboardPage /></AppLayout></RequireAuth>
            } />
            <Route path="/vehicles" element={
                <RequireAuth><AppLayout><VehiclesPage /></AppLayout></RequireAuth>
            } />
            <Route path="/customers" element={
                <RequireAuth><AppLayout><CustomersPage /></AppLayout></RequireAuth>
            } />
            <Route path="/trips" element={
                <RequireAuth><AppLayout><TripsPage /></AppLayout></RequireAuth>
            } />
            <Route path="/expenses" element={
                <RequireAuth><AppLayout><ExpensesPage /></AppLayout></RequireAuth>
            } />
            <Route path="/invoices" element={
                <RequireAuth><AppLayout><InvoicesPage /></AppLayout></RequireAuth>
            } />
            <Route path="/payments" element={
                <RequireAuth><AppLayout><PaymentsPage /></AppLayout></RequireAuth>
            } />
            <Route path="/car-wash" element={
                <RequireAuth><AppLayout><CarWashPage /></AppLayout></RequireAuth>
            } />
            <Route path="/reports" element={
                <RequireAuth><AppLayout><ReportsPage /></AppLayout></RequireAuth>
            } />
            <Route path="/activity-logs" element={
                <RequireAuth><AppLayout><ActivityLogsPage /></AppLayout></RequireAuth>
            } />
            <Route path="/settings" element={
                <RequireAuth><AppLayout><SettingsPage /></AppLayout></RequireAuth>
            } />

            <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
    );
}

export default function App() {
    return (
        <BrowserRouter basename={appBasePath || '/'}>
            <AuthProvider>
                <AppRoutes />
                <Toaster position="top-right" toastOptions={{ duration: 3000 }} />
            </AuthProvider>
        </BrowserRouter>
    );
}
