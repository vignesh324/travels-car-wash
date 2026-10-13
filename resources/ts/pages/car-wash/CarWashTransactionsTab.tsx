import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { carWashApi, carWashServicesApi } from '../../api/phase4';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Badge } from '../../components/ui/Card';
import { useCustomers } from '../../hooks/useCustomers';
import { formatCurrency, formatDate, paymentStatusColor, extractErrors } from '../../utils/format';
import type { CarWashTransaction, CarWashService } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const PAYMENT_MODES = ['Cash', 'UPI', 'Bank Transfer', 'Card', 'Other'];

type TxItem = {
    service_id: string;
    service_name: string;
    quantity: string;
    unit_price: string;
    discount: string;
    tax_rate: string;
};

const emptyItem = (): TxItem => ({
    service_id: '', service_name: '', quantity: '1',
    unit_price: '', discount: '0', tax_rate: '0',
});

function calcItem(item: TxItem) {
    const qty = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.unit_price) || 0;
    const disc = parseFloat(item.discount) || 0;
    const rate = parseFloat(item.tax_rate) || 0;
    const taxable = Math.max(0, qty * price - disc);
    return taxable + Math.round(taxable * rate) / 100;
}

function nowLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

export default function CarWashTransactionsTab() {
    const { customers } = useCustomers();
    const [services, setServices] = useState<CarWashService[]>([]);
    const [transactions, setTransactions] = useState<CarWashTransaction[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [filterCustomer, setFilterCustomer] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CarWashTransaction | null>(null);
    const [serviceDatetime, setServiceDatetime] = useState(nowLocal());
    const [customerId, setCustomerId] = useState('');
    const [vehicleReg, setVehicleReg] = useState('');
    const [txDiscount, setTxDiscount] = useState('0');
    const [paymentMode, setPaymentMode] = useState('Cash');
    const [amountPaid, setAmountPaid] = useState('');
    const [notes, setNotes] = useState('');
    const [items, setItems] = useState<TxItem[]>([emptyItem()]);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<CarWashTransaction | null>(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        carWashServicesApi.list().then(({ data }) => setServices(data)).catch(() => {});
    }, []);

    const fetchTransactions = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await carWashApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                customer_id: filterCustomer,
                payment_status: filterStatus,
                date_from: dateFrom,
                date_to: dateTo,
            });
            setTransactions(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load transactions');
        } finally {
            setLoading(false);
        }
    }, [page, filterCustomer, filterStatus, dateFrom, dateTo]);

    const [searchParams, setSearchParams] = useSearchParams();

    useEffect(() => { fetchTransactions(); }, [fetchTransactions]);

    useEffect(() => {
        if (searchParams.get('action') === 'new') {
            openAdd();
            setSearchParams({}, { replace: true });
        }
    }, [searchParams]);

    const openAdd = () => {
        setEditing(null);
        setServiceDatetime(nowLocal()); setCustomerId(''); setVehicleReg('');
        setTxDiscount('0'); setPaymentMode('Cash'); setAmountPaid('');
        setNotes(''); setItems([emptyItem()]); setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (tx: CarWashTransaction) => {
        setEditing(tx);
        setServiceDatetime(tx.service_datetime.slice(0, 16));
        setCustomerId(tx.customer_id ? String(tx.customer_id) : '');
        setVehicleReg(tx.vehicle_registration ?? '');
        setTxDiscount(tx.discount);
        setPaymentMode(tx.payment_mode ?? 'Cash');
        setAmountPaid(tx.amount_paid);
        setNotes(tx.notes ?? '');
        setItems((tx.items ?? []).map(i => ({
            service_id: i.service_id ? String(i.service_id) : '',
            service_name: i.service_name,
            quantity: i.quantity,
            unit_price: i.unit_price,
            discount: i.discount,
            tax_rate: i.tax_rate,
        })));
        setFormErrors({});
        setModalOpen(true);
    };

    const onServiceSelect = (i: number, serviceId: string) => {
        const svc = services.find(s => String(s.id) === serviceId);
        setItems(prev => prev.map((item, idx) => idx === i ? {
            ...item,
            service_id: serviceId,
            service_name: svc?.name ?? item.service_name,
            unit_price: svc ? String(svc.default_price) : item.unit_price,
            tax_rate: svc ? String(svc.tax_rate) : item.tax_rate,
        } : item));
    };

    const updateItem = (i: number, key: keyof TxItem, val: string) =>
        setItems(prev => prev.map((item, idx) => idx === i ? { ...item, [key]: val } : item));

    const grandTotal = items.reduce((s, item) => s + calcItem(item), 0);
    const discountVal = parseFloat(txDiscount) || 0;
    const netTotal = Math.max(0, grandTotal - discountVal);
    const balance = Math.max(0, netTotal - (parseFloat(amountPaid) || 0));

    const buildPayload = () => ({
        service_datetime: serviceDatetime,
        customer_id: customerId ? Number(customerId) : null,
        vehicle_registration: vehicleReg || null,
        discount: discountVal,
        payment_mode: paymentMode || null,
        amount_paid: parseFloat(amountPaid) || 0,
        notes: notes || null,
        items: items.map(it => ({
            service_id: it.service_id ? Number(it.service_id) : null,
            service_name: it.service_name,
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
                await carWashApi.update(editing.uuid, buildPayload());
                toast.success('Transaction updated');
            } else {
                await carWashApi.create(buildPayload());
                toast.success('Transaction recorded');
            }
            setModalOpen(false);
            fetchTransactions();
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

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await carWashApi.delete(deleteTarget.uuid);
            toast.success('Transaction deleted');
            setDeleteTarget(null);
            fetchTransactions();
        } catch {
            toast.error('Delete failed');
        } finally {
            setDeleting(false);
        }
    };

    const totalPages = Math.ceil(total / perPage);

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <p className="text-sm text-gray-500">{total} transaction{total !== 1 ? 's' : ''}</p>
                <Button onClick={openAdd}>+ New Bill</Button>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-2">
                <select value={filterCustomer} onChange={e => { setFilterCustomer(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Customers</option>
                    {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                </select>
                <select value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Tx #</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Customer</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Vehicle</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Total</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : transactions.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">No transactions found</td></tr>
                            ) : transactions.map(tx => (
                                <tr key={tx.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs font-medium text-blue-700">{tx.transaction_number}</td>
                                    <td className="px-4 py-3 text-gray-700">{formatDate(tx.service_datetime)}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">
                                        {tx.customer?.name ?? tx.customer?.company_name ?? <span className="text-gray-400">Walk-in</span>}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 font-mono text-xs hidden md:table-cell">{tx.vehicle_registration ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCurrency(tx.total_amount)}</td>
                                    <td className="px-4 py-3"><Badge color={paymentStatusColor(tx.payment_status)}>{tx.payment_status}</Badge></td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openEdit(tx)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                            <button onClick={() => setDeleteTarget(tx)} className="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
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

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Transaction' : 'New Car Wash Bill'} size="xl">
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input label="Date & Time" type="datetime-local" value={serviceDatetime} onChange={e => setServiceDatetime(e.target.value)} required />
                        <Input label="Vehicle Registration" value={vehicleReg} onChange={e => setVehicleReg(e.target.value)} placeholder="MH12AB1234" />
                        <Select label="Customer (optional)" value={customerId} onChange={e => setCustomerId(e.target.value)}>
                            <option value="">Walk-in</option>
                            {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                        </Select>
                        <Input label="Transaction Discount (₹)" type="number" value={txDiscount} onChange={e => setTxDiscount(e.target.value)} min="0" step="0.01" />
                    </div>

                    {/* Services */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Services</p>
                            <button onClick={() => setItems(prev => [...prev, emptyItem()])} className="text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add Service</button>
                        </div>
                        {formErrors.items && <p className="text-xs text-red-600 mb-2">{formErrors.items}</p>}
                        <div className="space-y-2">
                            {items.map((item, i) => (
                                <div key={i} className="grid grid-cols-12 gap-2 items-end bg-gray-50 rounded-lg p-2">
                                    <div className="col-span-12 sm:col-span-3">
                                        <Select label={i === 0 ? 'Service' : undefined} value={item.service_id} onChange={e => onServiceSelect(i, e.target.value)}>
                                            <option value="">Custom</option>
                                            {services.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </Select>
                                    </div>
                                    <div className="col-span-12 sm:col-span-3">
                                        <Input label={i === 0 ? 'Name' : undefined} value={item.service_name} onChange={e => updateItem(i, 'service_name', e.target.value)} placeholder="Service name" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-1">
                                        <Input label={i === 0 ? 'Qty' : undefined} type="number" value={item.quantity} onChange={e => updateItem(i, 'quantity', e.target.value)} min="0.01" step="0.01" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-2">
                                        <Input label={i === 0 ? 'Rate (₹)' : undefined} type="number" value={item.unit_price} onChange={e => updateItem(i, 'unit_price', e.target.value)} min="0" step="0.01" />
                                    </div>
                                    <div className="col-span-4 sm:col-span-1">
                                        <Input label={i === 0 ? 'GST%' : undefined} type="number" value={item.tax_rate} onChange={e => updateItem(i, 'tax_rate', e.target.value)} min="0" max="100" step="0.01" />
                                    </div>
                                    <div className="col-span-6 sm:col-span-1 text-right">
                                        {i === 0 && <p className="text-xs text-gray-500 mb-1">Total</p>}
                                        <p className="text-sm font-semibold py-2">{formatCurrency(calcItem(item))}</p>
                                    </div>
                                    <div className="col-span-6 sm:col-span-1 flex justify-end items-end pb-1">
                                        {items.length > 1 && (
                                            <button onClick={() => setItems(prev => prev.filter((_, idx) => idx !== i))} className="text-red-500 hover:text-red-700 text-xl">×</button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Live totals */}
                        <div className="bg-blue-50 rounded-lg p-3 mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                            <div><span className="text-gray-500">Subtotal:</span> <span className="font-medium">{formatCurrency(grandTotal)}</span></div>
                            <div><span className="text-gray-500">Discount:</span> <span className="font-medium">{formatCurrency(discountVal)}</span></div>
                            <div><span className="text-gray-500">Net Total:</span> <span className="font-bold text-blue-700">{formatCurrency(netTotal)}</span></div>
                            <div><span className="text-gray-500">Balance:</span> <span className={`font-bold ${balance > 0 ? 'text-red-600' : 'text-green-600'}`}>{formatCurrency(balance)}</span></div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Select label="Payment Mode" value={paymentMode} onChange={e => setPaymentMode(e.target.value)}>
                            {PAYMENT_MODES.map(m => <option key={m}>{m}</option>)}
                        </Select>
                        <Input label="Amount Paid (₹)" type="number" value={amountPaid} onChange={e => setAmountPaid(e.target.value)} min="0" step="0.01" />
                    </div>
                    <Textarea label="Notes" value={notes} onChange={e => setNotes(e.target.value)} rows={2} />
                </div>

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update' : 'Save Bill'}</Button>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Transaction"
                message={`Delete transaction ${deleteTarget?.transaction_number}?`}
                confirmLabel="Delete"
                danger
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
                loading={deleting}
            />
        </div>
    );
}
