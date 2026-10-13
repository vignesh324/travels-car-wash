import { useState, useEffect, useCallback } from 'react';
import { activityLogsApi } from '../../api/phase5';
import { formatDate } from '../../utils/format';
import toast from 'react-hot-toast';

type LogEntry = {
    id: number;
    log_name: string;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer: { id: number; name: string } | null;
    properties: Record<string, unknown>;
    created_at: string;
};

export default function ActivityLogsPage() {
    const [logs, setLogs] = useState<LogEntry[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 20;

    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [search, setSearch] = useState('');

    const fetchLogs = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await activityLogsApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                'search[value]': search,
                date_from: dateFrom,
                date_to: dateTo,
            });
            setLogs(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load activity logs');
        } finally {
            setLoading(false);
        }
    }, [page, search, dateFrom, dateTo]);

    useEffect(() => { fetchLogs(); }, [fetchLogs]);

    const totalPages = Math.ceil(total / perPage);

    const subjectLabel = (type: string | null) => {
        if (!type) return '—';
        return type.split('\\').pop() ?? type;
    };

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Activity Logs</h1>
                <p className="text-sm text-gray-500 mt-0.5">{total} log entr{total !== 1 ? 'ies' : 'y'}</p>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-2">
                <input
                    type="text"
                    placeholder="Search description..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="flex-1 min-w-48 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Time</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">User</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Action</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Subject</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Details</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={5} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : logs.length === 0 ? (
                                <tr><td colSpan={5} className="text-center py-12 text-gray-400">No activity logs found</td></tr>
                            ) : logs.map(log => (
                                <tr key={log.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{formatDate(log.created_at)}</td>
                                    <td className="px-4 py-3 text-gray-700 font-medium">{log.causer?.name ?? 'System'}</td>
                                    <td className="px-4 py-3 text-gray-900">{log.description}</td>
                                    <td className="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">
                                        {subjectLabel(log.subject_type)}
                                        {log.subject_id ? ` #${log.subject_id}` : ''}
                                    </td>
                                    <td className="px-4 py-3 hidden lg:table-cell">
                                        {log.properties && Object.keys(log.properties).length > 0 && (
                                            <details className="text-xs text-gray-500">
                                                <summary className="cursor-pointer hover:text-gray-700">View</summary>
                                                <pre className="mt-1 bg-gray-50 rounded p-2 text-xs overflow-x-auto max-w-xs">
                                                    {JSON.stringify(log.properties, null, 2)}
                                                </pre>
                                            </details>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {totalPages > 1 && (
                    <div className="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-600">
                        <span>Page {page} of {totalPages} ({total} total)</span>
                        <div className="flex gap-2">
                            <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1 rounded border disabled:opacity-40">Prev</button>
                            <button disabled={page === totalPages} onClick={() => setPage(p => p + 1)} className="px-3 py-1 rounded border disabled:opacity-40">Next</button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
