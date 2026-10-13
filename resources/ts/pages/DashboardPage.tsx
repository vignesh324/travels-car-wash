import { useState, useEffect } from 'react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { dashboardApi } from '../api/phase2';
import { useAuth } from '../hooks/useAuth';
import { formatCurrency, formatDate, paymentStatusColor } from '../utils/format';
import { Badge } from '../components/ui/Card';
import type { DashboardSummary } from '../types';
import toast from 'react-hot-toast';

const PIE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

function SummaryCard({ label, value, sub, color = 'blue' }: { label: string; value: string; sub?: string; color?: string }) {
    const colors: Record<string, string> = {
        blue: 'bg-blue-50 border-blue-200 text-blue-700',
        green: 'bg-green-50 border-green-200 text-green-700',
        red: 'bg-red-50 border-red-200 text-red-700',
        yellow: 'bg-yellow-50 border-yellow-200 text-yellow-700',
        purple: 'bg-purple-50 border-purple-200 text-purple-700',
    };
    return (
        <div className={`rounded-xl border p-4 ${colors[color] ?? colors.blue}`}>
            <p className="text-xs font-medium opacity-75 mb-1">{label}</p>
            <p className="text-xl font-bold">{value}</p>
            {sub && <p className="text-xs opacity-60 mt-0.5">{sub}</p>}
        </div>
    );
}

export default function DashboardPage() {
    const { user } = useAuth();
    const [summary, setSummary] = useState<DashboardSummary | null>(null);
    const [charts, setCharts] = useState<{ daily_revenue: unknown[]; vehicle_revenue: unknown[]; expense_breakdown: unknown[] } | null>(null);
    const [recent, setRecent] = useState<{ recent_trips: unknown[]; recent_expenses: unknown[]; expiring_documents: unknown[] } | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            dashboardApi.summary(),
            dashboardApi.charts(30),
            dashboardApi.recent(),
        ]).then(([s, c, r]) => {
            setSummary(s.data);
            setCharts(c.data as typeof charts);
            setRecent(r.data as typeof recent);
        }).catch(() => {
            toast.error('Failed to load dashboard');
        }).finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    const recentTrips = (recent?.recent_trips ?? []) as Array<Record<string, unknown>>;
    const expiringDocs = (recent?.expiring_documents ?? []) as Array<Record<string, unknown>>;
    const dailyRevenue = (charts?.daily_revenue ?? []) as Array<Record<string, unknown>>;
    const vehicleRevenue = (charts?.vehicle_revenue ?? []) as Array<Record<string, unknown>>;
    const expenseBreakdown = (charts?.expense_breakdown ?? []) as Array<Record<string, unknown>>;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p className="text-sm text-gray-500 mt-0.5">Welcome back, {user?.name}</p>
            </div>

            <div>
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Today</p>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <SummaryCard label="Trips Today" value={String(summary?.today.trip_count ?? 0)} color="blue" />
                    <SummaryCard label="Trip Revenue" value={formatCurrency(summary?.today.trip_revenue)} color="green" />
                    <SummaryCard label="Collected" value={formatCurrency(summary?.today.collected)} color="green" />
                    <SummaryCard label="Expenses" value={formatCurrency(summary?.today.expenses)} color="red" />
                </div>
            </div>

            <div>
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">This Month</p>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <SummaryCard label="Revenue" value={formatCurrency(summary?.month.revenue)} color="blue" />
                    <SummaryCard label="Collections" value={formatCurrency(summary?.month.collected)} color="green" />
                    <SummaryCard label="Expenses" value={formatCurrency(summary?.month.expenses)} color="red" />
                    <SummaryCard
                        label="Est. Profit"
                        value={formatCurrency(summary?.month.estimated_profit)}
                        sub="Operating expenses only"
                        color={(summary?.month.estimated_profit ?? 0) >= 0 ? 'green' : 'red'}
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="bg-orange-50 border border-orange-200 rounded-xl p-4">
                    <p className="text-xs font-medium text-orange-700 mb-1">Outstanding Balance</p>
                    <p className="text-xl font-bold text-orange-800">{formatCurrency(summary?.outstanding_balance)}</p>
                    <p className="text-xs text-orange-600 mt-0.5">Unpaid / partial trips</p>
                </div>
                <div className={`rounded-xl border p-4 ${(summary?.expiry_alerts ?? 0) > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'}`}>
                    <p className="text-xs font-medium text-gray-600 mb-1">Document Expiry Alerts</p>
                    <p className={`text-xl font-bold ${(summary?.expiry_alerts ?? 0) > 0 ? 'text-red-700' : 'text-gray-700'}`}>
                        {summary?.expiry_alerts ?? 0} vehicle{(summary?.expiry_alerts ?? 0) !== 1 ? 's' : ''}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">Expiring within 30 days</p>
                </div>
            </div>

            {dailyRevenue.length > 0 && (
                <div className="bg-white rounded-xl border border-gray-200 p-4">
                    <p className="text-sm font-semibold text-gray-700 mb-4">Daily Revenue — Last 30 Days</p>
                    <ResponsiveContainer width="100%" height={200}>
                        <BarChart data={dailyRevenue} margin={{ top: 0, right: 0, left: 0, bottom: 0 }}>
                            <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                            <YAxis tick={{ fontSize: 11 }} tickFormatter={v => `₹${(v / 1000).toFixed(0)}k`} />
                            <Tooltip formatter={(v: number) => formatCurrency(v)} />
                            <Bar dataKey="revenue" fill="#3b82f6" radius={[3, 3, 0, 0]} name="Revenue" />
                            <Bar dataKey="collected" fill="#10b981" radius={[3, 3, 0, 0]} name="Collected" />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {vehicleRevenue.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-sm font-semibold text-gray-700 mb-4">Vehicle Revenue — This Month</p>
                        <ResponsiveContainer width="100%" height={200}>
                            <BarChart data={vehicleRevenue} layout="vertical" margin={{ left: 10 }}>
                                <XAxis type="number" tick={{ fontSize: 11 }} tickFormatter={v => `₹${(v / 1000).toFixed(0)}k`} />
                                <YAxis type="category" dataKey="vehicle" tick={{ fontSize: 11 }} width={80} />
                                <Tooltip formatter={(v: number) => formatCurrency(v)} />
                                <Bar dataKey="revenue" fill="#6366f1" radius={[0, 3, 3, 0]} name="Revenue" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {expenseBreakdown.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-sm font-semibold text-gray-700 mb-4">Expense Breakdown — This Month</p>
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={expenseBreakdown}
                                    dataKey="total"
                                    nameKey="category"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={70}
                                    label={({ category }: { category: string }) => category}
                                >
                                    {expenseBreakdown.map((_, i) => (
                                        <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={(v: number) => formatCurrency(v)} />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </div>

            {/* Recent Trips */}
            {recentTrips.length > 0 && (
                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-4 py-3 border-b border-gray-200">
                        <p className="text-sm font-semibold text-gray-700">Recent Trips</p>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {recentTrips.map((t, i) => {
                            const customer = t.customer as Record<string, unknown> | null;
                            const vehicle = t.vehicle as Record<string, unknown> | null;
                            return (
                                <div key={i} className="px-4 py-3 flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">
                                            {String(customer?.name ?? customer?.company_name ?? 'Walk-in')}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {String(vehicle?.name ?? '')} · {formatDate(t.trip_date as string)}
                                        </p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <p className="text-sm font-semibold text-gray-900">{formatCurrency(t.total_amount as number)}</p>
                                        <Badge color={paymentStatusColor(t.payment_status as string)}>
                                            {t.payment_status as string}
                                        </Badge>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Expiring Documents */}
            {expiringDocs.length > 0 && (
                <div className="bg-white rounded-xl border border-red-200">
                    <div className="px-4 py-3 border-b border-red-200 bg-red-50 rounded-t-xl">
                        <p className="text-sm font-semibold text-red-700">⚠️ Document Expiry Alerts</p>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {expiringDocs.map((d, i) => (
                            <div key={i} className="px-4 py-3 text-sm">
                                <p className="font-medium text-gray-900">{d.name as string} <span className="text-gray-500 font-mono text-xs">({d.registration_number as string})</span></p>
                                <div className="flex flex-wrap gap-3 mt-1 text-xs text-gray-600">
                                    {d.insurance_expiry ? <span>Insurance: {formatDate(String(d.insurance_expiry))}</span> : null}
                                    {d.permit_expiry ? <span>Permit: {formatDate(String(d.permit_expiry))}</span> : null}
                                    {d.fitness_expiry ? <span>Fitness: {formatDate(String(d.fitness_expiry))}</span> : null}
                                    {d.puc_expiry ? <span>PUC: {formatDate(String(d.puc_expiry))}</span> : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
