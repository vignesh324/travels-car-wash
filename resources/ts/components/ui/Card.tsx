import type { ReactNode } from 'react';

// ── Badge ─────────────────────────────────────────────────────────────────────

interface BadgeProps {
    children: ReactNode;
    color?: 'green' | 'red' | 'yellow' | 'blue' | 'gray' | 'purple' | 'orange';
}

const badgeColors = {
    green:  'bg-green-100 text-green-700 ring-green-200',
    red:    'bg-red-100 text-red-700 ring-red-200',
    yellow: 'bg-yellow-100 text-yellow-700 ring-yellow-200',
    blue:   'bg-blue-100 text-blue-700 ring-blue-200',
    gray:   'bg-gray-100 text-gray-600 ring-gray-200',
    purple: 'bg-purple-100 text-purple-700 ring-purple-200',
    orange: 'bg-orange-100 text-orange-700 ring-orange-200',
};

export function Badge({ children, color = 'gray' }: BadgeProps) {
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${badgeColors[color]}`}>
            {children}
        </span>
    );
}

// ── Card ──────────────────────────────────────────────────────────────────────

interface CardProps { children: ReactNode; className?: string; }

export function Card({ children, className = '' }: CardProps) {
    return (
        <div className={`bg-white rounded-xl border border-gray-200 shadow-sm ${className}`}>
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '' }: CardProps) {
    return (
        <div className={`px-5 py-3.5 border-b border-gray-100 ${className}`}>
            {children}
        </div>
    );
}

export function CardBody({ children, className = '' }: CardProps) {
    return <div className={`p-5 ${className}`}>{children}</div>;
}

// ── PageHeader ────────────────────────────────────────────────────────────────

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}

export function PageHeader({ title, subtitle, action }: PageHeaderProps) {
    return (
        <div className="flex items-start justify-between mb-6 gap-4">
            <div>
                <h1 className="text-xl font-bold text-gray-900">{title}</h1>
                {subtitle && <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

// ── StatsCard ─────────────────────────────────────────────────────────────────

interface StatsCardProps {
    label: string;
    value: string;
    sub?: string;
    icon?: ReactNode;
    color?: 'blue' | 'green' | 'red' | 'yellow' | 'purple' | 'orange';
}

const statsColors = {
    blue:   { bg: 'bg-blue-50',   border: 'border-blue-100',  text: 'text-blue-700',   icon: 'bg-blue-100 text-blue-600' },
    green:  { bg: 'bg-green-50',  border: 'border-green-100', text: 'text-green-700',  icon: 'bg-green-100 text-green-600' },
    red:    { bg: 'bg-red-50',    border: 'border-red-100',   text: 'text-red-700',    icon: 'bg-red-100 text-red-600' },
    yellow: { bg: 'bg-yellow-50', border: 'border-yellow-100',text: 'text-yellow-700', icon: 'bg-yellow-100 text-yellow-600' },
    purple: { bg: 'bg-purple-50', border: 'border-purple-100',text: 'text-purple-700', icon: 'bg-purple-100 text-purple-600' },
    orange: { bg: 'bg-orange-50', border: 'border-orange-100',text: 'text-orange-700', icon: 'bg-orange-100 text-orange-600' },
};

export function StatsCard({ label, value, sub, icon, color = 'blue' }: StatsCardProps) {
    const c = statsColors[color];
    return (
        <div className={`rounded-xl border p-4 ${c.bg} ${c.border}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className={`text-xs font-medium mb-1 ${c.text} opacity-75`}>{label}</p>
                    <p className={`text-xl font-bold ${c.text}`}>{value}</p>
                    {sub && <p className={`text-xs mt-0.5 ${c.text} opacity-60`}>{sub}</p>}
                </div>
                {icon && (
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${c.icon}`}>
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );
}

// ── DataTable wrapper ─────────────────────────────────────────────────────────

interface TableProps {
    children: ReactNode;
    className?: string;
}

export function Table({ children, className = '' }: TableProps) {
    return (
        <div className={`bg-white rounded-xl border border-gray-200 overflow-hidden ${className}`}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">{children}</table>
            </div>
        </div>
    );
}

export function Th({ children, right }: { children: ReactNode; right?: boolean }) {
    return (
        <th className={`px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50 border-b border-gray-100 ${right ? 'text-right' : 'text-left'}`}>
            {children}
        </th>
    );
}

export function Td({ children, right, className = '' }: { children: ReactNode; right?: boolean; className?: string }) {
    return (
        <td className={`px-4 py-3 text-gray-700 ${right ? 'text-right' : ''} ${className}`}>
            {children}
        </td>
    );
}

// ── Pagination ────────────────────────────────────────────────────────────────

interface PaginationProps {
    page: number;
    totalPages: number;
    total: number;
    onPage: (p: number) => void;
}

export function Pagination({ page, totalPages, total, onPage }: PaginationProps) {
    if (totalPages <= 1) return null;
    return (
        <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
            <span>Page {page} of {totalPages} <span className="text-gray-400">({total} total)</span></span>
            <div className="flex gap-1">
                <button
                    disabled={page === 1}
                    onClick={() => onPage(page - 1)}
                    className="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    ← Prev
                </button>
                <button
                    disabled={page === totalPages}
                    onClick={() => onPage(page + 1)}
                    className="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Next →
                </button>
            </div>
        </div>
    );
}

// ── FilterBar ─────────────────────────────────────────────────────────────────

export function FilterBar({ children }: { children: ReactNode }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-4 flex flex-wrap gap-2 items-center">
            {children}
        </div>
    );
}

// ── EmptyState ────────────────────────────────────────────────────────────────

export function EmptyState({ message = 'No records found' }: { message?: string }) {
    return (
        <tr>
            <td colSpan={99} className="text-center py-16 text-gray-400">
                <svg className="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p className="text-sm">{message}</p>
            </td>
        </tr>
    );
}

export function LoadingRow() {
    return (
        <tr>
            <td colSpan={99} className="text-center py-16">
                <div className="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto" />
            </td>
        </tr>
    );
}
