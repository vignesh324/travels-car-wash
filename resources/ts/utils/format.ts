export function formatCurrency(amount: number | string | null | undefined): string {
    const num = parseFloat(String(amount ?? 0));
    if (isNaN(num)) return '₹0.00';
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function today(): string {
    return new Date().toISOString().split('T')[0];
}

export function extractErrors(err: unknown): Record<string, string> {
    if (err && typeof err === 'object' && 'response' in err) {
        const res = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }).response;
        if (res?.data?.errors) {
            const flat: Record<string, string> = {};
            Object.entries(res.data.errors).forEach(([k, v]) => { flat[k] = v[0]; });
            return flat;
        }
        if (res?.data?.message) return { _: res.data.message };
    }
    return { _: 'An unexpected error occurred' };
}

export function paymentStatusColor(status: string): 'green' | 'yellow' | 'red' | 'gray' {
    if (status === 'paid') return 'green';
    if (status === 'partial') return 'yellow';
    if (status === 'unpaid') return 'red';
    return 'gray';
}

export function tripStatusColor(status: string): 'green' | 'yellow' | 'red' | 'gray' {
    if (status === 'completed') return 'green';
    if (status === 'draft') return 'yellow';
    if (status === 'cancelled') return 'red';
    return 'gray';
}
