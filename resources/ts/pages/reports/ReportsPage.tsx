import { useState, useCallback } from 'react';
import { reportsApi } from '../../api/phase5';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Card';
import { formatCurrency, formatDate, paymentStatusColor, tripStatusColor } from '../../utils/format';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import toast from 'react-hot-toast';

type Tab = 'revenue' | 'expenses' | 'trips' | 'invoices' | 'payments' | 'carwash';

function DateFilters({ from, to, onFrom, onTo, onApply, loading }: {
    from: string; to: string;
    onFrom: (v: string) => void; onTo: (v: string) => void;
    onApply: () => void; loading: boolean;
}) {
    return (
        <div className="flex flex-wrap gap-2 items-end mb-4">
            <div>
                <label className="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" value={from} onChange={e => onFrom(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>
            <div>
                <label className="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" value={to} onChange={e => onTo(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>
            <Button onClick={onApply} loading={loading} size="sm">Apply</Button>
        </div>
    );
}

function SummaryGrid({ items }: { items: Array<{ label: string; value: string; color?: string }> }) {
    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            {items.map((item, i) => (
                <div key={i} className="bg-white rounded-xl border border-gray-200 p-4">
                    <p className="text-xs text-gray-500 mb-1">{item.label}</p>
                    <p className={`text-lg font-bold ${item.color ?? 'text-gray-900'}`}>{item.value}</p>
                </div>
            ))}
        </div>
    );
}

function DataTable({ columns, rows, loading }: { columns: string[]; rows: unknown[][]; loading: boolean }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-200">
                        <tr>{columns.map(c => <th key={c} className="text-left px-4 py-3 font-medium text-gray-600">{c}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {loading ? (
                            <tr><td colSpan={columns.length} className="text-center py-10 text-gray-400">Loading...</td></tr>
                        ) : rows.length === 0 ? (
                            <tr><td colSpan={columns.length} className="text-center py-10 text-gray-400">No data found</td></tr>
                        ) : rows.map((row, i) => (
                            <tr key={i} className="hover:bg-gray-50">
                                {row.map((cell, j) => <td key={j} className="px-4 py-3 text-gray-700">{cell as React.ReactNode}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function startOfMonth() { return new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]; }
function todayStr() { return new Date().toISOString().split('T')[0]; }

export default function ReportsPage() {
    const [tab, setTab] = useState<Tab>('revenue');
    const [from, setFrom] = useState(startOfMonth());
    const [to, setTo] = useState(todayStr());
    const [data, setData] = useState<Record<string, unknown> | null>(null);
    const [loading, setLoading] = useState(false);

    const tabs: Array<{ key: Tab; label: string }> = [
        { key: 'revenue', label: 'Revenue' },
        { key: 'expenses', label: 'Expenses' },
        { key: 'trips', label: 'Trips' },
        { key: 'invoices', label: 'Invoices' },
        { key: 'payments', label: 'Payments' },
        { key: 'carwash', label: 'Car Wash' },
    ];

    const fetchData = useCallback(async (t: Tab = tab, f: string = from, to2: string = to) => {
        setLoading(true);
        setData(null);
        try {
            const params = { date_from: f, date_to: to2 };
            const res = await ({
                revenue:  () => reportsApi.revenue(params),
                expenses: () => reportsApi.expenses(params),
                trips:    () => reportsApi.trips(params),
                invoices: () => reportsApi.invoices(params),
                payments: () => reportsApi.payments(params),
                carwash:  () => reportsApi.carwash(params),
            }[t])();
            setData(res.data as Record<string, unknown>);
        } catch {
            toast.error('Failed to load report');
        } finally {
            setLoading(false);
        }
    }, [tab, from, to]);

    const switchTab = (t: Tab) => { setTab(t); setData(null); fetchData(t, from, to); };
    const apply = () => fetchData(tab, from, to);

    const params = { date_from: from, date_to: to };

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Reports</h1>
                <p className="text-sm text-gray-500 mt-0.5">Business analytics and exports</p>
            </div>

            {/* Tab bar */}
            <div className="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 overflow-x-auto">
                {tabs.map(t => (
                    <button key={t.key} onClick={() => switchTab(t.key)}
                        className={`px-4 py-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors ${tab === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}>
                        {t.label}
                    </button>
                ))}
            </div>

            <DateFilters from={from} to={to} onFrom={setFrom} onTo={setTo} onApply={apply} loading={loading} />

            {/* Revenue */}
            {tab === 'revenue' && (() => {
                const s = (data?.summary ?? {}) as Record<string, number>;
                const daily = (data?.trips ?? []) as Array<Record<string, unknown>>;
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Travels Revenue', value: formatCurrency(s.travels_revenue) },
                            { label: 'Car Wash Revenue', value: formatCurrency(s.carwash_revenue) },
                            { label: 'Total Revenue', value: formatCurrency(s.total_revenue), color: 'text-blue-700' },
                            { label: 'Total Collected', value: formatCurrency(s.total_collected), color: 'text-green-700' },
                        ]} />
                        {daily.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                                <p className="text-sm font-semibold text-gray-700 mb-3">Daily Trip Revenue</p>
                                <ResponsiveContainer width="100%" height={200}>
                                    <BarChart data={daily}>
                                        <XAxis dataKey="date" tick={{ fontSize: 10 }} />
                                        <YAxis tick={{ fontSize: 10 }} tickFormatter={v => `₹${(v/1000).toFixed(0)}k`} />
                                        <Tooltip formatter={(v: number) => formatCurrency(v)} />
                                        <Bar dataKey="revenue" fill="#3b82f6" name="Revenue" radius={[3,3,0,0]} />
                                        <Bar dataKey="collected" fill="#10b981" name="Collected" radius={[3,3,0,0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        )}
                    </div>
                );
            })()}

            {/* Expenses */}
            {tab === 'expenses' && (() => {
                const s = (data?.summary ?? {}) as Record<string, number>;
                const rows = ((data?.vehicle_expenses ?? []) as Array<Record<string, unknown>>).map(e => [
                    formatDate(e.expense_date as string),
                    (e.vehicle as Record<string,unknown>)?.name ?? '—',
                    (e.category as Record<string,unknown>)?.name ?? '—',
                    e.description ?? '—',
                    formatCurrency(e.total_amount as number),
                    <Badge key="s" color={e.payment_status === 'paid' ? 'green' : e.payment_status === 'partial' ? 'yellow' : 'red'}>{e.payment_status as string}</Badge>,
                ]);
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Vehicle Expenses', value: formatCurrency(s.vehicle_expenses), color: 'text-red-600' },
                            { label: 'Business Expenses', value: formatCurrency(s.business_expenses), color: 'text-orange-600' },
                            { label: 'Total Expenses', value: formatCurrency(s.total_expenses), color: 'text-red-700' },
                        ]} />
                        <div className="flex justify-end mb-3">
                            <a href={reportsApi.exportExpensesUrl(params)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">⬇ Export Excel</a>
                        </div>
                        <DataTable loading={loading} columns={['Date','Vehicle','Category','Description','Amount','Status']} rows={rows} />
                    </div>
                );
            })()}

            {/* Trips */}
            {tab === 'trips' && (() => {
                const s = (data?.summary ?? {}) as Record<string, unknown>;
                const trips = ((data?.trips as Record<string,unknown>)?.data ?? []) as Array<Record<string, unknown>>;
                const rows = trips.map(t => [
                    t.trip_number,
                    formatDate(t.trip_date as string),
                    (t.vehicle as Record<string,unknown>)?.name ?? '—',
                    (t.customer as Record<string,unknown>)?.name ?? (t.customer as Record<string,unknown>)?.company_name ?? 'Walk-in',
                    t.trip_type,
                    formatCurrency(t.total_amount as number),
                    formatCurrency(t.amount_received as number),
                    <Badge key="s" color={tripStatusColor(t.status as string)}>{t.status as string}</Badge>,
                    <Badge key="p" color={paymentStatusColor(t.payment_status as string)}>{t.payment_status as string}</Badge>,
                ]);
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Total Trips', value: String(s.count ?? 0) },
                            { label: 'Revenue', value: formatCurrency(s.revenue as number), color: 'text-blue-700' },
                            { label: 'Collected', value: formatCurrency(s.collected as number), color: 'text-green-700' },
                            { label: 'Outstanding', value: formatCurrency(s.outstanding as number), color: 'text-red-600' },
                        ]} />
                        <div className="flex justify-end mb-3">
                            <a href={reportsApi.exportTripsUrl(params)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">⬇ Export Excel</a>
                        </div>
                        <DataTable loading={loading} columns={['Trip #','Date','Vehicle','Customer','Type','Total','Received','Status','Payment']} rows={rows} />
                    </div>
                );
            })()}

            {/* Invoices */}
            {tab === 'invoices' && (() => {
                const s = (data?.summary ?? {}) as Record<string, unknown>;
                const invs = ((data?.invoices as Record<string,unknown>)?.data ?? []) as Array<Record<string, unknown>>;
                const rows = invs.map(inv => [
                    <span key="n" className="font-mono text-xs text-blue-700">{inv.invoice_number as string}</span>,
                    formatDate(inv.invoice_date as string),
                    inv.customer_name,
                    formatCurrency(inv.total_amount as number),
                    formatCurrency(inv.amount_paid as number),
                    formatCurrency(inv.balance_due as number),
                    <Badge key="s" color={inv.status === 'paid' ? 'green' : inv.status === 'partial' ? 'yellow' : inv.status === 'cancelled' ? 'red' : 'blue'}>{inv.status as string}</Badge>,
                ]);
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Total Invoices', value: String(s.count ?? 0) },
                            { label: 'Invoiced', value: formatCurrency(s.invoiced as number), color: 'text-blue-700' },
                            { label: 'Collected', value: formatCurrency(s.collected as number), color: 'text-green-700' },
                            { label: 'Outstanding', value: formatCurrency(s.outstanding as number), color: 'text-red-600' },
                        ]} />
                        <div className="flex justify-end mb-3">
                            <a href={reportsApi.exportInvoicesUrl(params)} className="text-sm text-blue-600 hover:text-blue-800 font-medium">⬇ Export Excel</a>
                        </div>
                        <DataTable loading={loading} columns={['Invoice #','Date','Customer','Total','Paid','Balance','Status']} rows={rows} />
                    </div>
                );
            })()}

            {/* Payments */}
            {tab === 'payments' && (() => {
                const s = (data?.summary ?? {}) as Record<string, unknown>;
                const byMethod = (data?.by_method ?? []) as Array<Record<string, unknown>>;
                const pmts = ((data?.payments as Record<string,unknown>)?.data ?? []) as Array<Record<string, unknown>>;
                const rows = pmts.map(p => [
                    <span key="n" className="font-mono text-xs text-green-700">{p.payment_number as string}</span>,
                    formatDate(p.payment_date as string),
                    (p.customer as Record<string,unknown>)?.company_name ?? (p.customer as Record<string,unknown>)?.name ?? '—',
                    p.payment_method,
                    formatCurrency(p.amount as number),
                    formatCurrency(p.unallocated_amount as number),
                ]);
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Total Collected', value: formatCurrency(s.total_collected as number), color: 'text-green-700' },
                            ...byMethod.map(m => ({ label: m.payment_method as string, value: formatCurrency(m.total as number) })),
                        ]} />
                        <DataTable loading={loading} columns={['Receipt #','Date','Customer','Method','Amount','Unallocated']} rows={rows} />
                    </div>
                );
            })()}

            {/* Car Wash */}
            {tab === 'carwash' && (() => {
                const s = (data?.summary ?? {}) as Record<string, unknown>;
                const txs = ((data?.transactions as Record<string,unknown>)?.data ?? []) as Array<Record<string, unknown>>;
                const rows = txs.map(tx => [
                    <span key="n" className="font-mono text-xs text-blue-700">{tx.transaction_number as string}</span>,
                    formatDate(tx.service_datetime as string),
                    (tx.customer as Record<string,unknown>)?.name ?? 'Walk-in',
                    tx.vehicle_registration ?? '—',
                    formatCurrency(tx.total_amount as number),
                    formatCurrency(tx.amount_paid as number),
                    <Badge key="s" color={paymentStatusColor(tx.payment_status as string)}>{tx.payment_status as string}</Badge>,
                ]);
                return (
                    <div>
                        <SummaryGrid items={[
                            { label: 'Transactions', value: String(s.count ?? 0) },
                            { label: 'Revenue', value: formatCurrency(s.revenue as number), color: 'text-blue-700' },
                            { label: 'Collected', value: formatCurrency(s.collected as number), color: 'text-green-700' },
                            { label: 'Outstanding', value: formatCurrency(s.outstanding as number), color: 'text-red-600' },
                        ]} />
                        <DataTable loading={loading} columns={['Tx #','Date','Customer','Vehicle','Total','Paid','Status']} rows={rows} />
                    </div>
                );
            })()}

            {!data && !loading && (
                <div className="text-center py-16 text-gray-400">
                    <p className="text-lg mb-2">Select a date range and click Apply</p>
                    <Button onClick={apply}>Load Report</Button>
                </div>
            )}
        </div>
    );
}
