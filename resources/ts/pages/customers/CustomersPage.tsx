import { useState, useEffect, useCallback } from 'react';
import { customersApi } from '../../api/resources';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Badge } from '../../components/ui/Card';
import type { Customer, ApiError } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const INDIAN_STATES = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
    'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
    'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
    'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Delhi', 'Jammu & Kashmir', 'Ladakh', 'Puducherry',
];

const emptyForm = {
    type: 'individual' as 'individual' | 'corporate' | 'walkin',
    name: '', company_name: '', contact_person: '', phone: '', email: '',
    address: '', billing_address: '', gstin: '', state: '', state_code: '',
    payment_terms: '', credit_limit: '', notes: '', is_active: true,
};

type FormData = typeof emptyForm;

export default function CustomersPage() {
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [filterType, setFilterType] = useState('');
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);
    const [form, setForm] = useState<FormData>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [statementOpen, setStatementOpen] = useState(false);
    const [statementLoading, setStatementLoading] = useState(false);
    const [statementCustomer, setStatementCustomer] = useState<Customer | null>(null);
    const [statementData, setStatementData] = useState<any>(null);

    const openStatement = async (c: Customer) => {
        setStatementCustomer(c);
        setStatementOpen(true);
        setStatementLoading(true);
        try {
            const { data } = await customersApi.statement(c.uuid);
            setStatementData(data);
        } catch {
            toast.error('Failed to load customer statement');
        } finally {
            setStatementLoading(false);
        }
    };

    const fetchCustomers = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await customersApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                'search[value]': search,
                type: filterType,
            });
            setCustomers(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load customers');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterType]);

    useEffect(() => { fetchCustomers(); }, [fetchCustomers]);

    const openAdd = () => {
        setEditing(null);
        setForm(emptyForm);
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (c: Customer) => {
        setEditing(c);
        setForm({
            type: c.type,
            name: c.name,
            company_name: c.company_name ?? '',
            contact_person: c.contact_person ?? '',
            phone: c.phone ?? '',
            email: c.email ?? '',
            address: c.address ?? '',
            billing_address: c.billing_address ?? '',
            gstin: c.gstin ?? '',
            state: c.state ?? '',
            state_code: c.state_code ?? '',
            payment_terms: c.payment_terms ?? '',
            credit_limit: c.credit_limit ?? '',
            notes: c.notes ?? '',
            is_active: c.is_active,
        });
        setFormErrors({});
        setModalOpen(true);
    };

    const handleSave = async () => {
        setSaving(true);
        setFormErrors({});
        try {
            if (editing) {
                await customersApi.update(editing.uuid, form);
                toast.success('Customer updated');
            } else {
                await customersApi.create(form);
                toast.success('Customer added');
            }
            setModalOpen(false);
            fetchCustomers();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as ApiError;
                if (data?.errors) {
                    const flat: Record<string, string> = {};
                    Object.entries(data.errors).forEach(([k, v]) => { flat[k] = v[0]; });
                    setFormErrors(flat);
                } else {
                    toast.error(data?.message ?? 'Save failed');
                }
            }
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await customersApi.delete(deleteTarget.uuid);
            toast.success('Customer deleted');
            setDeleteTarget(null);
            fetchCustomers();
        } catch {
            toast.error('Delete failed');
        } finally {
            setDeleting(false);
        }
    };

    const f = (key: keyof FormData) => ({
        value: form[key] as string,
        onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
            setForm(prev => ({ ...prev, [key]: e.target.value })),
        error: formErrors[key],
    });

    const isCorporate = form.type === 'corporate';
    const totalPages = Math.ceil(total / perPage);

    const displayName = (c: Customer) =>
        c.type === 'corporate' && c.company_name ? c.company_name : c.name;

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Customers</h1>
                    <p className="text-sm text-gray-500 mt-0.5">{total} customer{total !== 1 ? 's' : ''}</p>
                </div>
                <Button onClick={openAdd}>+ Add Customer</Button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-3">
                <input
                    type="text"
                    placeholder="Search name, phone, company..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="flex-1 min-w-48 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <select value={filterType} onChange={e => { setFilterType(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Types</option>
                    <option value="individual">Individual</option>
                    <option value="corporate">Corporate</option>
                    <option value="walkin">Walk-in</option>
                </select>
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Name</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Phone</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">GSTIN</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">State</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : customers.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">No customers found</td></tr>
                            ) : customers.map(c => (
                                <tr key={c.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{displayName(c)}</p>
                                        {c.type === 'corporate' && c.contact_person && (
                                            <p className="text-xs text-gray-500">{c.contact_person}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge color={c.type === 'corporate' ? 'blue' : c.type === 'walkin' ? 'yellow' : 'gray'}>
                                            {c.type}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{c.phone ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 font-mono text-xs hidden lg:table-cell">{c.gstin ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden lg:table-cell">{c.state ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <Badge color={c.is_active ? 'green' : 'gray'}>{c.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openStatement(c)} className="text-emerald-600 hover:text-emerald-800 text-xs font-semibold">Statement</button>
                                            <button onClick={() => openEdit(c)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                            <button onClick={() => setDeleteTarget(c)} className="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
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

            {/* Add/Edit Modal */}
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Customer' : 'Add Customer'} size="lg">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="col-span-full">
                        <Select label="Customer Type" {...f('type')} required>
                            <option value="individual">Individual</option>
                            <option value="corporate">Corporate</option>
                            <option value="walkin">Walk-in</option>
                        </Select>
                    </div>

                    {isCorporate && <Input label="Company Name" {...f('company_name')} required={isCorporate} />}
                    <Input label={isCorporate ? 'Contact Person' : 'Full Name'} {...f('name')} required />
                    {isCorporate && <Input label="Contact Person Name" {...f('contact_person')} />}

                    <Input label="Phone" type="tel" {...f('phone')} placeholder="+91 98765 43210" />
                    <Input label="Email" type="email" {...f('email')} />
                    <Input label="GSTIN" {...f('gstin')} placeholder="22AAAAA0000A1Z5" className="uppercase" />
                    <Select label="State" {...f('state')}>
                        <option value="">Select state</option>
                        {INDIAN_STATES.map(s => <option key={s}>{s}</option>)}
                    </Select>
                    <Input label="State Code" {...f('state_code')} placeholder="27" maxLength={3} />
                    {isCorporate && <Input label="Payment Terms" {...f('payment_terms')} placeholder="Net 30" />}
                    {isCorporate && <Input label="Credit Limit (₹)" type="number" {...f('credit_limit')} min="0" />}

                    <div className="col-span-full">
                        <Textarea label="Address" {...f('address')} />
                    </div>
                    {isCorporate && (
                        <div className="col-span-full">
                            <Textarea label="Billing Address" {...f('billing_address')} />
                        </div>
                    )}
                    <div className="col-span-full">
                        <Textarea label="Notes" {...f('notes')} />
                    </div>

                    <div className="col-span-full">
                        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.is_active as unknown as boolean}
                                onChange={e => setForm(prev => ({ ...prev, is_active: e.target.checked }))}
                                className="rounded border-gray-300 text-blue-600"
                            />
                            Active customer
                        </label>
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update Customer' : 'Add Customer'}</Button>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Customer"
                message={`Are you sure you want to delete "${deleteTarget ? displayName(deleteTarget) : ''}"?`}
                confirmLabel="Delete"
                danger
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
                loading={deleting}
            />

            {/* Customer Statement Modal */}
            <Modal
                open={statementOpen}
                onClose={() => setStatementOpen(false)}
                title={`Customer Statement: ${statementCustomer ? displayName(statementCustomer) : ''}`}
                size="xl"
            >
                {statementLoading ? (
                    <div className="py-12 text-center text-gray-500">Loading statement...</div>
                ) : statementData ? (
                    <div className="space-y-6">
                        {/* Summary Cards */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div className="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <p className="text-xs text-gray-500 font-medium">Opening Balance</p>
                                <p className="text-base font-bold text-gray-900 mt-0.5">₹{Number(statementData.opening_balance || 0).toFixed(2)}</p>
                            </div>
                            <div className="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <p className="text-xs text-blue-600 font-medium">Total Invoiced</p>
                                <p className="text-base font-bold text-blue-900 mt-0.5">₹{Number(statementData.total_invoiced || 0).toFixed(2)}</p>
                            </div>
                            <div className="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
                                <p className="text-xs text-emerald-600 font-medium">Total Paid</p>
                                <p className="text-base font-bold text-emerald-900 mt-0.5">₹{Number(statementData.total_paid || 0).toFixed(2)}</p>
                            </div>
                            <div className="p-3 bg-rose-50 rounded-lg border border-rose-100">
                                <p className="text-xs text-rose-600 font-medium">Closing Due</p>
                                <p className="text-base font-bold text-rose-900 mt-0.5">₹{Number(statementData.closing_balance || 0).toFixed(2)}</p>
                            </div>
                        </div>

                        {Number(statementData.advance_balance || 0) > 0 && (
                            <div className="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-800">
                                <strong>Advance Credit Available:</strong> ₹{Number(statementData.advance_balance).toFixed(2)}
                            </div>
                        )}

                        {/* Invoices */}
                        <div>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Invoices</h4>
                            <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
                                <table className="w-full text-xs">
                                    <thead className="bg-gray-50 text-gray-600 border-b">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Invoice #</th>
                                            <th className="px-3 py-2 text-left font-semibold">Date</th>
                                            <th className="px-3 py-2 text-right font-semibold">Total</th>
                                            <th className="px-3 py-2 text-right font-semibold">Balance</th>
                                            <th className="px-3 py-2 text-center font-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {(statementData.invoices || []).length === 0 ? (
                                            <tr><td colSpan={5} className="text-center py-4 text-gray-400">No invoices</td></tr>
                                        ) : (
                                            statementData.invoices.map((inv: any) => (
                                                <tr key={inv.id}>
                                                    <td className="px-3 py-2 font-mono">{inv.invoice_number}</td>
                                                    <td className="px-3 py-2">{inv.invoice_date?.slice(0, 10)}</td>
                                                    <td className="px-3 py-2 text-right font-medium">₹{Number(inv.total_amount).toFixed(2)}</td>
                                                    <td className="px-3 py-2 text-right font-bold text-rose-600">₹{Number(inv.balance_due).toFixed(2)}</td>
                                                    <td className="px-3 py-2 text-center"><Badge color={inv.status === 'paid' ? 'green' : inv.status === 'cancelled' ? 'red' : 'yellow'}>{inv.status}</Badge></td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Payments */}
                        <div>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Payments Received</h4>
                            <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
                                <table className="w-full text-xs">
                                    <thead className="bg-gray-50 text-gray-600 border-b">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">Receipt #</th>
                                            <th className="px-3 py-2 text-left font-semibold">Date</th>
                                            <th className="px-3 py-2 text-left font-semibold">Mode</th>
                                            <th className="px-3 py-2 text-right font-semibold">Amount</th>
                                            <th className="px-3 py-2 text-right font-semibold">Allocated</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {(statementData.payments || []).length === 0 ? (
                                            <tr><td colSpan={5} className="text-center py-4 text-gray-400">No payments</td></tr>
                                        ) : (
                                            statementData.payments.map((p: any) => (
                                                <tr key={p.id}>
                                                    <td className="px-3 py-2 font-mono">{p.payment_number}</td>
                                                    <td className="px-3 py-2">{p.payment_date?.slice(0, 10)}</td>
                                                    <td className="px-3 py-2">{p.payment_method}</td>
                                                    <td className="px-3 py-2 text-right font-semibold text-emerald-600">₹{Number(p.amount).toFixed(2)}</td>
                                                    <td className="px-3 py-2 text-right text-gray-700">₹{Number(p.allocated_amount).toFixed(2)}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex justify-end pt-3 border-t">
                            <Button variant="secondary" onClick={() => setStatementOpen(false)}>Close</Button>
                        </div>
                    </div>
                ) : null}
            </Modal>
        </div>
    );
}
