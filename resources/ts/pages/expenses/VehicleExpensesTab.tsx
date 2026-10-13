import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { vehicleExpensesApi } from '../../api/phase2';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Badge } from '../../components/ui/Card';
import { useVehicles } from '../../hooks/useVehicles';
import { useExpenseCategories } from '../../hooks/useExpenseCategories';
import { formatCurrency, formatDate, extractErrors, today } from '../../utils/format';
import type { VehicleExpense } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const PAYMENT_MODES = ['Cash', 'UPI', 'Bank Transfer', 'Card', 'Other'];

const emptyForm = {
    expense_date: today(),
    vehicle_id: '',
    category_id: '',
    description: '',
    vendor_name: '',
    reference_number: '',
    amount_before_tax: '',
    gst_amount: '0',
    payment_mode: 'Cash',
    payment_status: 'paid',
    payment_date: '',
    notes: '',
};

type FormData = typeof emptyForm;

export default function VehicleExpensesTab() {
    const { vehicles } = useVehicles();
    const { categories } = useExpenseCategories('vehicle');

    const [expenses, setExpenses] = useState<VehicleExpense[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [filterVehicle, setFilterVehicle] = useState('');
    const [filterCategory, setFilterCategory] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<VehicleExpense | null>(null);
    const [form, setForm] = useState<FormData>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<VehicleExpense | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchExpenses = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await vehicleExpensesApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                vehicle_id: filterVehicle,
                category_id: filterCategory,
                payment_status: filterStatus,
                date_from: dateFrom,
                date_to: dateTo,
            });
            setExpenses(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load expenses');
        } finally {
            setLoading(false);
        }
    }, [page, filterVehicle, filterCategory, filterStatus, dateFrom, dateTo]);

    const [searchParams, setSearchParams] = useSearchParams();

    useEffect(() => { fetchExpenses(); }, [fetchExpenses]);

    useEffect(() => {
        if (searchParams.get('action') === 'new') {
            openAdd();
            setSearchParams({}, { replace: true });
        }
    }, [searchParams]);

    const openAdd = () => {
        setEditing(null);
        setForm(emptyForm);
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (e: VehicleExpense) => {
        setEditing(e);
        setForm({
            expense_date: e.expense_date,
            vehicle_id: String(e.vehicle_id),
            category_id: String(e.category_id),
            description: e.description ?? '',
            vendor_name: e.vendor_name ?? '',
            reference_number: e.reference_number ?? '',
            amount_before_tax: e.amount_before_tax,
            gst_amount: e.gst_amount,
            payment_mode: e.payment_mode,
            payment_status: e.payment_status,
            payment_date: e.payment_date ?? '',
            notes: e.notes ?? '',
        });
        setFormErrors({});
        setModalOpen(true);
    };

    const handleSave = async () => {
        setSaving(true);
        setFormErrors({});
        try {
            const amtBefore = parseFloat(form.amount_before_tax) || 0;
            const gst = parseFloat(form.gst_amount) || 0;
            const payload = {
                ...form,
                vehicle_id: Number(form.vehicle_id),
                category_id: Number(form.category_id),
                amount_before_tax: amtBefore,
                gst_amount: gst,
                total_amount: amtBefore + gst,
            };
            if (editing) {
                await vehicleExpensesApi.update(editing.uuid, payload as unknown as Partial<VehicleExpense>);
                toast.success('Expense updated');
            } else {
                await vehicleExpensesApi.create(payload as unknown as Partial<VehicleExpense>);
                toast.success('Expense recorded');
            }
            setModalOpen(false);
            fetchExpenses();
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
            await vehicleExpensesApi.delete(deleteTarget.uuid);
            toast.success('Expense deleted');
            setDeleteTarget(null);
            fetchExpenses();
        } catch {
            toast.error('Delete failed');
        } finally {
            setDeleting(false);
        }
    };

    const set = (key: keyof FormData) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [key]: e.target.value }));

    const totalPages = Math.ceil(total / perPage);
    const previewTotal = (parseFloat(form.amount_before_tax) || 0) + (parseFloat(form.gst_amount) || 0);

    const paymentColor = (s: string) => s === 'paid' ? 'green' : s === 'partial' ? 'yellow' : 'red';

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <p className="text-sm text-gray-500">{total} expense{total !== 1 ? 's' : ''}</p>
                <Button onClick={openAdd}>+ Add Expense</Button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-2">
                <select value={filterVehicle} onChange={e => { setFilterVehicle(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Vehicles</option>
                    {vehicles.map(v => <option key={v.id} value={v.id}>{v.name} ({v.registration_number})</option>)}
                </select>
                <select value={filterCategory} onChange={e => { setFilterCategory(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Categories</option>
                    {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                </select>
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm" />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Vehicle</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Category</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Vendor</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : expenses.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">No expenses found</td></tr>
                            ) : expenses.map(e => (
                                <tr key={e.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-700">{formatDate(e.expense_date)}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900">{e.vehicle?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{e.category?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden lg:table-cell">{e.vendor_name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCurrency(e.total_amount)}</td>
                                    <td className="px-4 py-3"><Badge color={paymentColor(e.payment_status)}>{e.payment_status}</Badge></td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openEdit(e)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                            <button onClick={() => setDeleteTarget(e)} className="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
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

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Vehicle Expense' : 'Add Vehicle Expense'} size="lg">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Expense Date" type="date" value={form.expense_date} onChange={set('expense_date')} error={formErrors.expense_date} required />
                    <Select label="Vehicle" value={form.vehicle_id} onChange={set('vehicle_id')} error={formErrors.vehicle_id} required>
                        <option value="">Select vehicle</option>
                        {vehicles.map(v => <option key={v.id} value={v.id}>{v.name} ({v.registration_number})</option>)}
                    </Select>
                    <Select label="Category" value={form.category_id} onChange={set('category_id')} error={formErrors.category_id} required>
                        <option value="">Select category</option>
                        {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </Select>
                    <Input label="Vendor / Shop" value={form.vendor_name} onChange={set('vendor_name')} error={formErrors.vendor_name} />
                    <Input label="Reference Number" value={form.reference_number} onChange={set('reference_number')} />
                    <Input label="Amount Before Tax (₹)" type="number" value={form.amount_before_tax} onChange={set('amount_before_tax')} error={formErrors.amount_before_tax} required min="0" step="0.01" />
                    <Input label="GST Amount (₹)" type="number" value={form.gst_amount} onChange={set('gst_amount')} min="0" step="0.01" />
                    <div className="col-span-full bg-blue-50 rounded-lg px-3 py-2 text-sm">
                        Total: <span className="font-bold text-blue-700">{formatCurrency(previewTotal)}</span>
                    </div>
                    <Select label="Payment Mode" value={form.payment_mode} onChange={set('payment_mode')} error={formErrors.payment_mode} required>
                        {PAYMENT_MODES.map(m => <option key={m}>{m}</option>)}
                    </Select>
                    <Select label="Payment Status" value={form.payment_status} onChange={set('payment_status')} error={formErrors.payment_status} required>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partial</option>
                    </Select>
                    <Input label="Payment Date" type="date" value={form.payment_date} onChange={set('payment_date')} />
                    <Input label="Description" value={form.description} onChange={set('description')} />
                    <div className="col-span-full">
                        <Textarea label="Notes" value={form.notes} onChange={set('notes')} rows={2} />
                    </div>
                </div>
                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update' : 'Save'}</Button>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Expense"
                message={`Delete this expense of ${formatCurrency(deleteTarget?.total_amount)}? This cannot be undone.`}
                confirmLabel="Delete"
                danger
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
                loading={deleting}
            />
        </div>
    );
}
