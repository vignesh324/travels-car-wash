import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { paymentsApi } from '../../api/phase3';
import { invoicesApi } from '../../api/phase3';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { Badge } from '../../components/ui/Card';
import { useCustomers } from '../../hooks/useCustomers';
import { formatCurrency, formatDate, extractErrors, today } from '../../utils/format';
import type { Invoice, Payment } from '../../types';
import { usePdfDownload } from '../../hooks/usePdfDownload';
import axios from 'axios';
import toast from 'react-hot-toast';

const PAYMENT_METHODS = ['Cash', 'UPI', 'Bank Transfer', 'Card', 'Other'];

function statusColor(s: string): 'green' | 'red' | 'gray' {
    if (s === 'active') return 'green';
    if (s === 'reversed') return 'red';
    return 'gray';
}

export default function PaymentsPage() {
    const { customers } = useCustomers();
    const { download, downloading } = usePdfDownload();
    const [payments, setPayments] = useState<Payment[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [filterCustomer, setFilterCustomer] = useState('');
    const [filterMethod, setFilterMethod] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    // Record payment modal
    const [modalOpen, setModalOpen] = useState(false);
    const [customerId, setCustomerId] = useState('');
    const [paymentDate, setPaymentDate] = useState(today());
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState('Cash');
    const [txRef, setTxRef] = useState('');
    const [notes, setNotes] = useState('');
    const [outstandingInvoices, setOutstandingInvoices] = useState<Invoice[]>([]);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    // Reverse modal
    const [reverseTarget, setReverseTarget] = useState<Payment | null>(null);
    const [reverseReason, setReverseReason] = useState('');
    const [reversing, setReversing] = useState(false);
    const [viewTarget, setViewTarget] = useState<Payment | null>(null);

    const fetchPayments = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await paymentsApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                customer_id: filterCustomer,
                payment_method: filterMethod,
                status: filterStatus,
                date_from: dateFrom,
                date_to: dateTo,
            });
            setPayments(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load payments');
        } finally {
            setLoading(false);
        }
    }, [page, filterCustomer, filterMethod, filterStatus, dateFrom, dateTo]);

    const [searchParams, setSearchParams] = useSearchParams();

    useEffect(() => { fetchPayments(); }, [fetchPayments]);

    useEffect(() => {
        if (searchParams.get('action') === 'new') {
            openRecord();
            setSearchParams({}, { replace: true });
        }
    }, [searchParams]);

    const openRecord = () => {
        setCustomerId(''); setPaymentDate(today()); setAmount('');
        setMethod('Cash'); setTxRef(''); setNotes('');
        setOutstandingInvoices([]); setAllocations({}); setFormErrors({});
        setModalOpen(true);
    };

    const onCustomerChange = async (id: string) => {
        setCustomerId(id);
        setAllocations({});
        if (id) {
            try {
                const { data } = await invoicesApi.datatable({
                    customer_id: id, status: 'issued', start: 0, length: 50,
                });
                const issued = (data.data ?? []) as Invoice[];
                // also partial
                const { data: data2 } = await invoicesApi.datatable({
                    customer_id: id, status: 'partial', start: 0, length: 50,
                });
                setOutstandingInvoices([...issued, ...(data2.data ?? []) as Invoice[]]);
            } catch {
                setOutstandingInvoices([]);
            }
        } else {
            setOutstandingInvoices([]);
        }
    };

    const totalAllocated = Object.values(allocations).reduce((s, v) => s + (parseFloat(v) || 0), 0);
    const paymentAmount = parseFloat(amount) || 0;
    const unallocated = Math.max(0, paymentAmount - totalAllocated);

    const handleSave = async () => {
        setSaving(true);
        setFormErrors({});
        try {
            const allocs = Object.entries(allocations)
                .filter(([, v]) => parseFloat(v) > 0)
                .map(([invoiceId, amt]) => ({ invoice_id: Number(invoiceId), amount: parseFloat(amt) }));

            await paymentsApi.create({
                customer_id: Number(customerId),
                payment_date: paymentDate,
                amount: parseFloat(amount),
                payment_method: method,
                transaction_reference: txRef || null,
                notes: notes || null,
                allocations: allocs,
            });
            toast.success('Payment recorded');
            setModalOpen(false);
            fetchPayments();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const errs = extractErrors(err);
                if (errs._) toast.error(errs._);
                else setFormErrors(errs);
            }
        } finally {
            setSaving(false);
        }
    };

    const handleReverse = async () => {
        if (!reverseTarget || !reverseReason.trim()) return;
        setReversing(true);
        try {
            await paymentsApi.reverse(reverseTarget.uuid, reverseReason);
            toast.success('Payment reversed');
            setReverseTarget(null);
            setReverseReason('');
            fetchPayments();
        } catch (err) {
            if (axios.isAxiosError(err)) toast.error(extractErrors(err)._);
        } finally {
            setReversing(false);
        }
    };

    const totalPages = Math.ceil(total / perPage);

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payments</h1>
                    <p className="text-sm text-gray-500 mt-0.5">{total} payment{total !== 1 ? 's' : ''}</p>
                </div>
                <Button onClick={openRecord}>+ Record Payment</Button>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-2">
                <select value={filterCustomer} onChange={e => { setFilterCustomer(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Customers</option>
                    {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                </select>
                <select value={filterMethod} onChange={e => { setFilterMethod(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Methods</option>
                    {PAYMENT_METHODS.map(m => <option key={m}>{m}</option>)}
                </select>
                <select value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="reversed">Reversed</option>
                </select>
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Receipt #</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Customer</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Method</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Unallocated</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={8} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : payments.length === 0 ? (
                                <tr><td colSpan={8} className="text-center py-12 text-gray-400">No payments found</td></tr>
                            ) : payments.map(p => (
                                <tr key={p.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs font-medium text-green-700">{p.payment_number}</td>
                                    <td className="px-4 py-3 text-gray-700">{formatDate(p.payment_date)}</td>
                                    <td className="px-4 py-3 text-gray-700 hidden md:table-cell">
                                        {p.customer?.company_name ?? p.customer?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{p.payment_method}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCurrency(p.amount)}</td>
                                    <td className="px-4 py-3 text-right text-amber-600 hidden md:table-cell">{formatCurrency(p.unallocated_amount)}</td>
                                    <td className="px-4 py-3"><Badge color={statusColor(p.status)}>{p.status}</Badge></td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => setViewTarget(p)} className="text-gray-600 hover:text-gray-800 text-xs font-medium">View</button>
                                            <button onClick={() => download(paymentsApi.receiptPath(p.uuid), `Receipt-${p.payment_number}.pdf`)} disabled={downloading} className="text-blue-600 hover:text-blue-800 text-xs font-medium disabled:opacity-40">Receipt</button>
                                            {p.status === 'active' && (
                                                <button onClick={() => { setReverseTarget(p); setReverseReason(''); }} className="text-red-600 hover:text-red-800 text-xs font-medium">Reverse</button>
                                            )}
                                        </div>
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

            {/* Record Payment Modal */}
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Record Payment" size="lg">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Select label="Customer" value={customerId} onChange={e => onCustomerChange(e.target.value)} error={formErrors.customer_id} required>
                        <option value="">Select customer</option>
                        {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                    </Select>
                    <Input label="Payment Date" type="date" value={paymentDate} onChange={e => setPaymentDate(e.target.value)} required />
                    <Input label="Amount (₹)" type="number" value={amount} onChange={e => setAmount(e.target.value)} error={formErrors.amount} required min="0.01" step="0.01" />
                    <Select label="Payment Method" value={method} onChange={e => setMethod(e.target.value)} required>
                        {PAYMENT_METHODS.map(m => <option key={m}>{m}</option>)}
                    </Select>
                    <Input label="Transaction Reference" value={txRef} onChange={e => setTxRef(e.target.value)} placeholder="UPI ref, cheque no..." />
                    <div className="col-span-full">
                        <Textarea label="Notes" value={notes} onChange={e => setNotes(e.target.value)} rows={2} />
                    </div>
                </div>

                {outstandingInvoices.length > 0 && (
                    <div className="mt-4">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Allocate to Invoices (optional)</p>
                        <div className="border border-gray-200 rounded-lg divide-y divide-gray-100">
                            {outstandingInvoices.map(inv => (
                                <div key={inv.id} className="flex items-center gap-3 px-3 py-2">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-mono font-medium text-blue-700">{inv.invoice_number}</p>
                                        <p className="text-xs text-gray-500">{formatDate(inv.invoice_date)} · Balance: {formatCurrency(inv.balance_due)}</p>
                                    </div>
                                    <input
                                        type="number"
                                        placeholder="0.00"
                                        value={allocations[inv.id] ?? ''}
                                        onChange={e => setAllocations(prev => ({ ...prev, [inv.id]: e.target.value }))}
                                        className="w-28 rounded-lg border border-gray-300 px-2 py-1 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        min="0"
                                        step="0.01"
                                        max={inv.balance_due}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-between text-xs mt-2 px-1">
                            <span className="text-gray-500">Allocated: {formatCurrency(totalAllocated)}</span>
                            <span className={unallocated > 0 ? 'text-amber-600 font-medium' : 'text-green-600 font-medium'}>
                                Advance/Unallocated: {formatCurrency(unallocated)}
                            </span>
                        </div>
                    </div>
                )}

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>Record Payment</Button>
                </div>
            </Modal>

            {/* Reverse Payment Modal */}
            <Modal open={!!reverseTarget} onClose={() => setReverseTarget(null)} title="Reverse Payment" size="sm">
                <p className="text-sm text-gray-600 mb-3">
                    Reverse payment <span className="font-mono font-medium">{reverseTarget?.payment_number}</span> of{' '}
                    <span className="font-semibold">{formatCurrency(reverseTarget?.amount)}</span>?
                    All allocations will be removed and invoice balances restored.
                </p>
                <Textarea label="Reason" value={reverseReason} onChange={e => setReverseReason(e.target.value)} required rows={2} />
                <div className="flex justify-end gap-3 mt-4">
                    <Button variant="secondary" onClick={() => setReverseTarget(null)}>Back</Button>
                    <Button variant="danger" onClick={handleReverse} loading={reversing} disabled={!reverseReason.trim()}>Reverse Payment</Button>
                </div>
            </Modal>

            {/* View Payment Modal */}
            <Modal open={!!viewTarget} onClose={() => setViewTarget(null)} title={`Payment: ${viewTarget?.payment_number}`} size="md">
                {viewTarget && (
                    <div className="space-y-3 text-sm">
                        <div className="grid grid-cols-2 gap-3">
                            <div><span className="text-gray-500">Date:</span> <span className="font-medium ml-1">{formatDate(viewTarget.payment_date)}</span></div>
                            <div><span className="text-gray-500">Method:</span> <span className="font-medium ml-1">{viewTarget.payment_method}</span></div>
                            <div><span className="text-gray-500">Customer:</span> <span className="font-medium ml-1">{viewTarget.customer?.company_name ?? viewTarget.customer?.name ?? '—'}</span></div>
                            <div><span className="text-gray-500">Status:</span> <span className="ml-1"><Badge color={statusColor(viewTarget.status)}>{viewTarget.status}</Badge></span></div>
                            {viewTarget.transaction_reference && <div className="col-span-2"><span className="text-gray-500">Reference:</span> <span className="font-medium ml-1">{viewTarget.transaction_reference}</span></div>}
                        </div>
                        <div className="bg-gray-50 rounded-lg p-3 grid grid-cols-3 gap-2">
                            <div><span className="text-gray-500">Amount:</span> <span className="font-bold text-blue-700 ml-1">{formatCurrency(viewTarget.amount)}</span></div>
                            <div><span className="text-gray-500">Allocated:</span> <span className="font-medium text-green-700 ml-1">{formatCurrency(viewTarget.allocated_amount)}</span></div>
                            <div><span className="text-gray-500">Advance:</span> <span className="font-medium text-amber-600 ml-1">{formatCurrency(viewTarget.unallocated_amount)}</span></div>
                        </div>
                        {viewTarget.allocations && viewTarget.allocations.length > 0 && (
                            <div>
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Applied Against</p>
                                <div className="border border-gray-200 rounded-lg divide-y divide-gray-100">
                                    {viewTarget.allocations.map(a => (
                                        <div key={a.id} className="flex justify-between px-3 py-2 text-xs">
                                            <span className="font-mono text-blue-700">{a.invoice?.invoice_number ?? 'Invoice'}</span>
                                            <span className="font-medium">{formatCurrency(a.amount)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        {viewTarget.notes && <p className="text-gray-500 text-xs">Notes: {viewTarget.notes}</p>}
                        <div className="flex justify-end gap-3 pt-2">
                            <Button variant="secondary" onClick={() => download(paymentsApi.receiptPath(viewTarget.uuid), `Receipt-${viewTarget.payment_number}.pdf`)} loading={downloading}>Download Receipt</Button>
                            <Button onClick={() => setViewTarget(null)}>Close</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
