import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { invoicesApi } from '../../api/phase3';
import { tripsApi } from '../../api/phase2';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { Badge } from '../../components/ui/Card';
import { useCustomers } from '../../hooks/useCustomers';
import { formatCurrency, formatDate, extractErrors, today } from '../../utils/format';
import type { Invoice, Trip } from '../../types';
import { usePdfDownload } from '../../hooks/usePdfDownload';
import axios from 'axios';
import toast from 'react-hot-toast';

type LineItem = {
    description: string;
    source: 'travels' | 'carwash';
    quantity: string;
    unit_price: string;
    discount: string;
    tax_rate: string;
};

const emptyItem = (): LineItem => ({
    description: '', source: 'travels', quantity: '1',
    unit_price: '', discount: '0', tax_rate: '0',
});

function statusColor(s: string): 'green' | 'yellow' | 'red' | 'blue' | 'gray' {
    if (s === 'paid') return 'green';
    if (s === 'partial') return 'yellow';
    if (s === 'cancelled') return 'red';
    if (s === 'issued') return 'blue';
    return 'gray';
}

function calcLineTotal(item: LineItem) {
    const qty = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.unit_price) || 0;
    const disc = parseFloat(item.discount) || 0;
    const rate = parseFloat(item.tax_rate) || 0;
    const taxable = Math.max(0, qty * price - disc);
    return taxable + Math.round(taxable * rate) / 100;
}

export default function InvoicesPage() {
    const { customers } = useCustomers();
    const { download, downloading } = usePdfDownload();
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [filterStatus, setFilterStatus] = useState('');
    const [filterCustomer, setFilterCustomer] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    // form state
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Invoice | null>(null);
    const [customerId, setCustomerId] = useState('');
    const [invoiceDate, setInvoiceDate] = useState(today());
    const [dueDate, setDueDate] = useState('');
    const [source, setSource] = useState('travels');
    const [placeOfSupply, setPlaceOfSupply] = useState('');
    const [paymentTerms, setPaymentTerms] = useState('');
    const [notes, setNotes] = useState('');
    const [items, setItems] = useState<LineItem[]>([emptyItem()]);
    const [tripIds, setTripIds] = useState<number[]>([]);
    const [availableTrips, setAvailableTrips] = useState<Trip[]>([]);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const [cancelTarget, setCancelTarget] = useState<Invoice | null>(null);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelling, setCancelling] = useState(false);
    const [viewTarget, setViewTarget] = useState<Invoice | null>(null);
    const [viewLoading, setViewLoading] = useState(false);

    const [searchParams, setSearchParams] = useSearchParams();

    const fetchInvoices = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await invoicesApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                customer_id: filterCustomer,
                status: filterStatus,
                date_from: dateFrom,
                date_to: dateTo,
            });
            setInvoices(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load invoices');
        } finally {
            setLoading(false);
        }
    }, [page, filterCustomer, filterStatus, dateFrom, dateTo]);

    useEffect(() => { fetchInvoices(); }, [fetchInvoices]);

    useEffect(() => {
        if (searchParams.get('action') === 'new') {
            openCreate();
            setSearchParams({}, { replace: true });
        }
    }, [searchParams]);

    const openCreate = () => {
        setEditing(null);
        setCustomerId(''); setInvoiceDate(today()); setDueDate('');
        setSource('travels'); setPlaceOfSupply(''); setPaymentTerms('');
        setNotes(''); setItems([emptyItem()]); setTripIds([]);
        setAvailableTrips([]); setFormErrors({});
        setModalOpen(true);
    };

    const toDateInput = (val: string | null | undefined) =>
        val ? val.slice(0, 10) : '';

    const openEdit = async (inv: Invoice) => {
        setEditing(inv);
        setCustomerId(String(inv.customer_id));
        setInvoiceDate(toDateInput(inv.invoice_date));
        setDueDate(toDateInput(inv.due_date));
        setSource(inv.source);
        setPlaceOfSupply(inv.place_of_supply ?? '');
        setPaymentTerms(inv.payment_terms ?? '');
        setNotes(inv.notes ?? '');
        setTripIds([]);
        setAvailableTrips([]);
        setFormErrors({});

        // load full invoice to get items
        try {
            const { data } = await invoicesApi.get(inv.uuid);
            const full = data as Invoice;
            setItems((full.items ?? []).map(it => ({
                description: it.description,
                source: it.source,
                quantity: String(it.quantity),
                unit_price: String(it.unit_price),
                discount: String(it.discount),
                tax_rate: String(it.tax_rate),
            })));
        } catch {
            setItems([emptyItem()]);
        }

        setModalOpen(true);
    };

    const onCustomerChange = async (id: string) => {
        setCustomerId(id);
        setTripIds([]);
        if (id) {
            try {
                const { data } = await tripsApi.list({ customer_id: id });
                setAvailableTrips(Array.isArray(data) ? data as Trip[] : []);
            } catch {
                setAvailableTrips([]);
            }
        } else {
            setAvailableTrips([]);
        }
    };

    const updateItem = (i: number, key: keyof LineItem, val: string) =>
        setItems(prev => prev.map((item, idx) => idx === i ? { ...item, [key]: val } : item));

    const grandTotal = items.reduce((sum, item) => sum + calcLineTotal(item), 0);

    const buildPayload = () => ({
        invoice_date: invoiceDate,
        due_date: dueDate || null,
        source,
        place_of_supply: placeOfSupply || null,
        payment_terms: paymentTerms || null,
        notes: notes || null,
        items: items.map(it => ({
            ...it,
            quantity: parseFloat(it.quantity) || 1,
            unit_price: parseFloat(it.unit_price) || 0,
            discount: parseFloat(it.discount) || 0,
            tax_rate: parseFloat(it.tax_rate) || 0,
        })),
    });

    const handleSave = async () => {
        setSaving(true);
        setFormErrors({});
        try {
            if (editing) {
                await invoicesApi.update(editing.uuid, buildPayload());
                toast.success('Invoice updated');
            } else {
                await invoicesApi.create({
                    customer_id: Number(customerId),
                    trip_ids: tripIds,
                    ...buildPayload(),
                });
                toast.success('Invoice created');
            }
            setModalOpen(false);
            fetchInvoices();
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

    const handleCancel = async () => {
        if (!cancelTarget || !cancelReason.trim()) return;
        setCancelling(true);
        try {
            await invoicesApi.cancel(cancelTarget.uuid, cancelReason);
            toast.success('Invoice cancelled');
            setCancelTarget(null);
            setCancelReason('');
            fetchInvoices();
        } catch (err) {
            if (axios.isAxiosError(err)) toast.error(extractErrors(err)._);
        } finally {
            setCancelling(false);
        }
    };

    const openView = async (inv: Invoice) => {
        setViewTarget({ ...inv, invoice_date: toDateInput(inv.invoice_date), due_date: toDateInput(inv.due_date) });
        setViewLoading(true);
        try {
            const { data } = await invoicesApi.get(inv.uuid);
            const full = data as Invoice;
            setViewTarget({ ...full, invoice_date: toDateInput(full.invoice_date), due_date: toDateInput(full.due_date) });
        } catch {
            // keep the list-level data
        } finally {
            setViewLoading(false);
        }
    };

    const canEdit = (inv: Invoice) => inv.status !== 'paid' && inv.status !== 'cancelled';

    const totalPages = Math.ceil(total / perPage);

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Invoices</h1>
                    <p className="text-sm text-gray-500 mt-0.5">{total} invoice{total !== 1 ? 's' : ''}</p>
                </div>
                <Button onClick={openCreate}>+ Create Invoice</Button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-2">
                <select value={filterCustomer} onChange={e => { setFilterCustomer(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Customers</option>
                    {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                </select>
                <select value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="issued">Issued</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Invoice #</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Customer</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Total</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Paid</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Balance</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={8} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : invoices.length === 0 ? (
                                <tr><td colSpan={8} className="text-center py-12 text-gray-400">No invoices found</td></tr>
                            ) : invoices.map(inv => (
                                <tr key={inv.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs font-medium text-blue-700">{inv.invoice_number}</td>
                                    <td className="px-4 py-3 text-gray-700">{formatDate(inv.invoice_date)}</td>
                                    <td className="px-4 py-3 text-gray-700 hidden md:table-cell">{inv.customer_name}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCurrency(inv.total_amount)}</td>
                                    <td className="px-4 py-3 text-right text-green-700 hidden md:table-cell">{formatCurrency(inv.amount_paid)}</td>
                                    <td className="px-4 py-3 text-right text-red-600 hidden md:table-cell">{formatCurrency(inv.balance_due)}</td>
                                    <td className="px-4 py-3"><Badge color={statusColor(inv.status)}>{inv.status}</Badge></td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openView(inv)} className="text-gray-600 hover:text-gray-800 text-xs font-medium">View</button>
                                            <button onClick={() => download(invoicesApi.pdfPath(inv.uuid), `Invoice-${inv.invoice_number}.pdf`)} disabled={downloading} className="text-blue-600 hover:text-blue-800 text-xs font-medium disabled:opacity-40">PDF</button>
                                            {canEdit(inv) && (
                                                <button onClick={() => openEdit(inv)} className="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>
                                            )}
                                            {inv.status !== 'cancelled' && inv.status !== 'paid' && (
                                                <button onClick={() => { setCancelTarget(inv); setCancelReason(''); }} className="text-red-600 hover:text-red-800 text-xs font-medium">Cancel</button>
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

            {/* Create / Edit Modal */}
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? `Edit Invoice: ${editing.invoice_number}` : 'Create Invoice'} size="xl">
                <div className="space-y-4">
                    {editing ? (
                        <div className="bg-gray-50 rounded-lg px-4 py-3 space-y-2">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-gray-500 mb-0.5">Customer</p>
                                    <p className="text-sm font-medium text-gray-900">{editing.customer_name}</p>
                                </div>
                                <Badge color="blue">{editing.source}</Badge>
                            </div>
                            <div className="grid grid-cols-3 gap-2 pt-1 border-t border-gray-200 text-xs">
                                <div>
                                    <p className="text-gray-500">Invoice Total</p>
                                    <p className="font-semibold text-gray-900 mt-0.5">{formatCurrency(editing.total_amount)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Amount Paid</p>
                                    <p className="font-semibold text-green-700 mt-0.5">{formatCurrency(editing.amount_paid)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Balance Due</p>
                                    <p className={`font-semibold mt-0.5 ${parseFloat(editing.balance_due) > 0 ? 'text-red-600' : 'text-green-600'}`}>{formatCurrency(editing.balance_due)}</p>
                                </div>
                            </div>
                            {parseFloat(editing.amount_paid) > 0 && (
                                <p className="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">
                                    ⚠ This invoice has recorded payments. Editing line items will recalculate the balance due.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Select label="Customer" value={customerId} onChange={e => onCustomerChange(e.target.value)} error={formErrors.customer_id} required>
                                <option value="">Select customer</option>
                                {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                            </Select>
                            <Select label="Source" value={source} onChange={e => setSource(e.target.value)}>
                                <option value="travels">Travels</option>
                                <option value="carwash">Car Wash</option>
                                <option value="mixed">Mixed</option>
                            </Select>
                        </div>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input label="Invoice Date" type="date" value={invoiceDate} onChange={e => setInvoiceDate(e.target.value)} required />
                        <Input label="Due Date" type="date" value={dueDate} onChange={e => setDueDate(e.target.value)} />
                        <Input label="Place of Supply" value={placeOfSupply} onChange={e => setPlaceOfSupply(e.target.value)} placeholder="State name" />
                        <Input label="Payment Terms" value={paymentTerms} onChange={e => setPaymentTerms(e.target.value)} placeholder="Net 30" />
                    </div>

                    {!editing && availableTrips.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Link Trips (optional)</p>
                            <div className="max-h-32 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                                {availableTrips.map(t => (
                                    <label key={t.id} className="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
                                        <input type="checkbox" checked={tripIds.includes(t.id)} onChange={e => setTripIds(prev => e.target.checked ? [...prev, t.id] : prev.filter(id => id !== t.id))} className="rounded border-gray-300 text-blue-600" />
                                        <span className="font-mono text-xs text-blue-700">{t.trip_number}</span>
                                        <span className="text-gray-600">{formatDate(t.trip_date)}</span>
                                        <span className="ml-auto font-semibold">{formatCurrency(t.total_amount)}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Line Items</p>
                            <button onClick={() => setItems(prev => [...prev, emptyItem()])} className="text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add Line</button>
                        </div>
                        {formErrors.items && <p className="text-xs text-red-600 mb-2">{formErrors.items}</p>}
                        <div className="space-y-2">
                            {items.map((item, i) => (
                                <div key={i} className="grid grid-cols-12 gap-2 items-end bg-gray-50 rounded-lg p-2">
                                    <div className="col-span-12 sm:col-span-4">
                                        <Input label={i === 0 ? 'Description' : undefined} value={item.description} onChange={e => updateItem(i, 'description', e.target.value)} placeholder="Service description" error={formErrors[`items.${i}.description`]} />
                                    </div>
                                    <div className="col-span-6 sm:col-span-2">
                                        <Select label={i === 0 ? 'Source' : undefined} value={item.source} onChange={e => updateItem(i, 'source', e.target.value as 'travels' | 'carwash')}>
                                            <option value="travels">Travels</option>
                                            <option value="carwash">Car Wash</option>
                                        </Select>
                                    </div>
                                    <div className="col-span-6 sm:col-span-1">
                                        <Input label={i === 0 ? 'Qty' : undefined} type="number" value={item.quantity} onChange={e => updateItem(i, 'quantity', e.target.value)} min="0.01" step="0.01" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-2">
                                        <Input label={i === 0 ? 'Rate (₹)' : undefined} type="number" value={item.unit_price} onChange={e => updateItem(i, 'unit_price', e.target.value)} min="0" step="0.01" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-1">
                                        <Input label={i === 0 ? 'Disc' : undefined} type="number" value={item.discount} onChange={e => updateItem(i, 'discount', e.target.value)} min="0" step="0.01" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-1">
                                        <Input label={i === 0 ? 'GST%' : undefined} type="number" value={item.tax_rate} onChange={e => updateItem(i, 'tax_rate', e.target.value)} min="0" max="100" step="0.01" />
                                    </div>
                                    <div className="col-span-6 sm:col-span-1 text-right">
                                        {i === 0 && <p className="text-xs text-gray-500 mb-1">Total</p>}
                                        <p className="text-sm font-semibold text-gray-900 py-2">{formatCurrency(calcLineTotal(item))}</p>
                                    </div>
                                    <div className="col-span-6 sm:col-span-1 flex justify-end items-end pb-1">
                                        {items.length > 1 && (
                                            <button onClick={() => setItems(prev => prev.filter((_, idx) => idx !== i))} className="text-red-500 hover:text-red-700 text-xl leading-none">×</button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="text-right mt-2 text-sm font-bold text-blue-700">
                            Grand Total (preview): {formatCurrency(grandTotal)}
                        </div>
                    </div>

                    <Textarea label="Notes" value={notes} onChange={e => setNotes(e.target.value)} rows={2} />
                </div>
                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update Invoice' : 'Create Invoice'}</Button>
                </div>
            </Modal>

            {/* Cancel Modal */}
            <Modal open={!!cancelTarget} onClose={() => setCancelTarget(null)} title="Cancel Invoice" size="sm">
                <p className="text-sm text-gray-600 mb-3">
                    Cancel invoice <span className="font-mono font-medium">{cancelTarget?.invoice_number}</span>? Linked trips will be unlinked.
                </p>
                <Textarea label="Cancellation Reason" value={cancelReason} onChange={e => setCancelReason(e.target.value)} required rows={2} />
                <div className="flex justify-end gap-3 mt-4">
                    <Button variant="secondary" onClick={() => setCancelTarget(null)}>Back</Button>
                    <Button variant="danger" onClick={handleCancel} loading={cancelling} disabled={!cancelReason.trim()}>Cancel Invoice</Button>
                </div>
            </Modal>

            {/* View Modal */}
            <Modal open={!!viewTarget} onClose={() => setViewTarget(null)} title={`Invoice: ${viewTarget?.invoice_number}`} size="lg">
                {viewTarget && (
                    <div className="space-y-4 text-sm">
                        {viewLoading && <p className="text-xs text-gray-400 text-center py-1">Loading details…</p>}
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div><p className="text-xs text-gray-500">Customer</p><p className="font-medium mt-0.5">{viewTarget.customer_name}</p></div>
                            <div><p className="text-xs text-gray-500">Invoice Date</p><p className="font-medium mt-0.5">{formatDate(viewTarget.invoice_date)}</p></div>
                            <div><p className="text-xs text-gray-500">Due Date</p><p className="font-medium mt-0.5">{viewTarget.due_date ? formatDate(viewTarget.due_date) : '—'}</p></div>
                            <div><p className="text-xs text-gray-500">Status</p><div className="mt-0.5"><Badge color={statusColor(viewTarget.status)}>{viewTarget.status}</Badge></div></div>
                            <div><p className="text-xs text-gray-500">Source</p><p className="font-medium mt-0.5 capitalize">{viewTarget.source}</p></div>
                            {viewTarget.place_of_supply && <div><p className="text-xs text-gray-500">Place of Supply</p><p className="font-medium mt-0.5">{viewTarget.place_of_supply}</p></div>}
                            {viewTarget.payment_terms && <div><p className="text-xs text-gray-500">Payment Terms</p><p className="font-medium mt-0.5">{viewTarget.payment_terms}</p></div>}
                            {viewTarget.customer_gstin && <div><p className="text-xs text-gray-500">Customer GSTIN</p><p className="font-mono text-xs font-medium mt-0.5">{viewTarget.customer_gstin}</p></div>}
                        </div>

                        {viewTarget.items && viewTarget.items.length > 0 && (
                            <table className="w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="text-left px-3 py-2">Description</th>
                                        <th className="text-right px-3 py-2">Qty</th>
                                        <th className="text-right px-3 py-2">Rate</th>
                                        <th className="text-right px-3 py-2">Disc</th>
                                        <th className="text-right px-3 py-2">GST%</th>
                                        <th className="text-right px-3 py-2">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {viewTarget.items.map((item, i) => (
                                        <tr key={i} className="hover:bg-gray-50">
                                            <td className="px-3 py-2">{item.description}</td>
                                            <td className="px-3 py-2 text-right">{item.quantity}</td>
                                            <td className="px-3 py-2 text-right">{formatCurrency(item.unit_price)}</td>
                                            <td className="px-3 py-2 text-right">{formatCurrency(item.discount)}</td>
                                            <td className="px-3 py-2 text-right">{item.tax_rate}%</td>
                                            <td className="px-3 py-2 text-right font-medium">{formatCurrency(item.total_amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        <div className="bg-gray-50 rounded-lg p-3 space-y-1.5 text-xs">
                            <div className="flex justify-between"><span className="text-gray-500">Subtotal</span><span className="font-medium">{formatCurrency(viewTarget.subtotal)}</span></div>
                            <div className="flex justify-between"><span className="text-gray-500">Discount</span><span className="font-medium">- {formatCurrency(viewTarget.discount_total)}</span></div>
                            <div className="flex justify-between"><span className="text-gray-500">Taxable Amount</span><span className="font-medium">{formatCurrency(viewTarget.taxable_amount)}</span></div>
                            {parseFloat(viewTarget.cgst_amount) > 0 && <div className="flex justify-between"><span className="text-gray-500">CGST</span><span className="font-medium">{formatCurrency(viewTarget.cgst_amount)}</span></div>}
                            {parseFloat(viewTarget.sgst_amount) > 0 && <div className="flex justify-between"><span className="text-gray-500">SGST</span><span className="font-medium">{formatCurrency(viewTarget.sgst_amount)}</span></div>}
                            {parseFloat(viewTarget.igst_amount) > 0 && <div className="flex justify-between"><span className="text-gray-500">IGST</span><span className="font-medium">{formatCurrency(viewTarget.igst_amount)}</span></div>}
                            <div className="flex justify-between border-t border-gray-200 pt-1.5 mt-1">
                                <span className="font-semibold text-gray-700">Total</span>
                                <span className="font-bold text-blue-700 text-sm">{formatCurrency(viewTarget.total_amount)}</span>
                            </div>
                            <div className="flex justify-between"><span className="text-gray-500">Amount Paid</span><span className="font-medium text-green-700">{formatCurrency(viewTarget.amount_paid)}</span></div>
                            <div className="flex justify-between"><span className="text-gray-500">Balance Due</span><span className="font-bold text-red-600">{formatCurrency(viewTarget.balance_due)}</span></div>
                        </div>

                        {viewTarget.notes && <p className="text-xs text-gray-600 bg-yellow-50 rounded px-3 py-2">Notes: {viewTarget.notes}</p>}
                        {viewTarget.cancellation_reason && <p className="text-xs text-red-600 bg-red-50 rounded px-3 py-2">Cancellation reason: {viewTarget.cancellation_reason}</p>}

                        <div className="flex justify-between items-center pt-2 border-t gap-3">
                            <div className="flex gap-2">
                                {canEdit(viewTarget) && (
                                    <Button size="sm" variant="secondary" onClick={() => { setViewTarget(null); openEdit(viewTarget); }}>Edit</Button>
                                )}
                                {viewTarget.status !== 'cancelled' && viewTarget.status !== 'paid' && (
                                    <Button size="sm" variant="danger" onClick={() => { setViewTarget(null); setCancelTarget(viewTarget); setCancelReason(''); }}>Cancel</Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button size="sm" variant="secondary" onClick={() => download(invoicesApi.pdfPath(viewTarget.uuid), `Invoice-${viewTarget.invoice_number}.pdf`)} loading={downloading}>View PDF</Button>
                                <Button size="sm" onClick={() => setViewTarget(null)}>Close</Button>
                            </div>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
