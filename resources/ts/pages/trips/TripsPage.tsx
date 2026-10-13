import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { tripsApi } from '../../api/phase2';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea, filterInputClass } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Badge, PageHeader, Table, Th, Td, Pagination, FilterBar, EmptyState, LoadingRow } from '../../components/ui/Card';
import { useVehicles } from '../../hooks/useVehicles';
import { useCustomers } from '../../hooks/useCustomers';
import { formatCurrency, formatDate, paymentStatusColor, tripStatusColor, extractErrors, today } from '../../utils/format';
import type { Trip } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const TRIP_TYPES = ['Local', 'Outstation', 'Airport Transfer', 'Corporate', 'Other'];
const PAYMENT_MODES = ['Cash', 'UPI', 'Bank Transfer', 'Card', 'Other'];

const emptyForm = {
    trip_date: today(), vehicle_id: '', customer_id: '', trip_type: 'Local',
    pickup_location: '', drop_location: '', description: '', start_odometer: '',
    end_odometer: '', driver_name: '', fare_amount: '', additional_charges: '0',
    discount: '0', is_taxable: false, tax_rate: '0', payment_mode: '',
    amount_received: '0', status: 'completed', notes: '',
};
type FormData = typeof emptyForm;

function calcPreview(f: FormData) {
    const fare = Math.max(0, parseFloat(f.fare_amount) || 0);
    const add  = Math.max(0, parseFloat(f.additional_charges) || 0);
    const disc = Math.max(0, parseFloat(f.discount) || 0);
    const rate = f.is_taxable ? Math.max(0, parseFloat(f.tax_rate) || 0) : 0;
    const sub  = fare + add;
    const taxable = sub - Math.min(disc, sub);
    const tax  = f.is_taxable ? Math.round(taxable * rate) / 100 : 0;
    const total = taxable + tax;
    const recv  = Math.max(0, parseFloat(f.amount_received) || 0);
    return { taxable, tax, total, balance: Math.max(0, total - recv) };
}

export default function TripsPage() {
    const { vehicles } = useVehicles();
    const { customers } = useCustomers();
    const [trips, setTrips] = useState<Trip[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [search, setSearch] = useState('');
    const [filterVehicle, setFilterVehicle] = useState('');
    const [filterType, setFilterType] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [filterPayment, setFilterPayment] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Trip | null>(null);
    const [form, setForm] = useState<FormData>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Trip | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchTrips = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await tripsApi.datatable({
                start: (page - 1) * perPage, length: perPage,
                'search[value]': search, vehicle_id: filterVehicle,
                trip_type: filterType, status: filterStatus,
                payment_status: filterPayment, date_from: dateFrom, date_to: dateTo,
            });
            setTrips(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch { toast.error('Failed to load trips'); }
        finally { setLoading(false); }
    }, [page, search, filterVehicle, filterType, filterStatus, filterPayment, dateFrom, dateTo]);

    const [searchParams, setSearchParams] = useSearchParams();

    useEffect(() => { fetchTrips(); }, [fetchTrips]);

    useEffect(() => {
        if (searchParams.get('action') === 'new') {
            openAdd();
            setSearchParams({}, { replace: true });
        }
    }, [searchParams]);

    const openAdd = () => { setEditing(null); setForm(emptyForm); setFormErrors({}); setModalOpen(true); };
    const openEdit = (t: Trip) => {
        setEditing(t);
        setForm({
            trip_date: t.trip_date, vehicle_id: String(t.vehicle_id),
            customer_id: t.customer_id ? String(t.customer_id) : '',
            trip_type: t.trip_type, pickup_location: t.pickup_location ?? '',
            drop_location: t.drop_location ?? '', description: t.description ?? '',
            start_odometer: t.start_odometer?.toString() ?? '',
            end_odometer: t.end_odometer?.toString() ?? '',
            driver_name: t.driver_name ?? '', fare_amount: t.fare_amount,
            additional_charges: t.additional_charges, discount: t.discount,
            is_taxable: t.is_taxable, tax_rate: t.tax_rate,
            payment_mode: t.payment_mode ?? '', amount_received: t.amount_received,
            status: t.status, notes: t.notes ?? '',
        });
        setFormErrors({}); setModalOpen(true);
    };

    const handleSave = async () => {
        setSaving(true); setFormErrors({});
        try {
            const payload = {
                ...form,
                vehicle_id: Number(form.vehicle_id),
                customer_id: form.customer_id ? Number(form.customer_id) : null,
                start_odometer: form.start_odometer ? Number(form.start_odometer) : null,
                end_odometer: form.end_odometer ? Number(form.end_odometer) : null,
                fare_amount: parseFloat(form.fare_amount) || 0,
                additional_charges: parseFloat(form.additional_charges) || 0,
                discount: parseFloat(form.discount) || 0,
                tax_rate: parseFloat(form.tax_rate) || 0,
                amount_received: parseFloat(form.amount_received) || 0,
            };
            if (editing) {
                await tripsApi.update(editing.uuid, payload as unknown as Partial<Trip>);
                toast.success('Trip updated');
            } else {
                await tripsApi.create(payload as unknown as Partial<Trip>);
                toast.success('Trip recorded');
            }
            setModalOpen(false); fetchTrips();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const errs = extractErrors(err);
                if (errs._) toast.error(errs._); else setFormErrors(errs);
            }
        } finally { setSaving(false); }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await tripsApi.delete(deleteTarget.uuid);
            toast.success('Trip deleted'); setDeleteTarget(null); fetchTrips();
        } catch { toast.error('Delete failed'); }
        finally { setDeleting(false); }
    };

    const set = (key: keyof FormData) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [key]: e.target.value }));

    const preview = calcPreview(form);
    const totalPages = Math.ceil(total / perPage);

    return (
        <div>
            <PageHeader
                title="Trips"
                subtitle={`${total} trip${total !== 1 ? 's' : ''}`}
                action={<Button onClick={openAdd}>+ Add Trip</Button>}
            />

            <FilterBar>
                <input
                    type="text" placeholder="Search trip #, location..."
                    value={search} onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className={`${filterInputClass} flex-1 min-w-40`}
                />
                <select value={filterVehicle} onChange={e => { setFilterVehicle(e.target.value); setPage(1); }} className={filterInputClass}>
                    <option value="">All Vehicles</option>
                    {vehicles.map(v => <option key={v.id} value={v.id}>{v.name} ({v.registration_number})</option>)}
                </select>
                <select value={filterType} onChange={e => { setFilterType(e.target.value); setPage(1); }} className={filterInputClass}>
                    <option value="">All Types</option>
                    {TRIP_TYPES.map(t => <option key={t}>{t}</option>)}
                </select>
                <select value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1); }} className={filterInputClass}>
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select value={filterPayment} onChange={e => { setFilterPayment(e.target.value); setPage(1); }} className={filterInputClass}>
                    <option value="">All Payments</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
                <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className={filterInputClass} />
                <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className={filterInputClass} />
            </FilterBar>

            <Table>
                <thead>
                    <tr>
                        <Th>Trip #</Th>
                        <Th>Date</Th>
                        <Th>Vehicle</Th>
                        <Th>Customer</Th>
                        <Th>Type</Th>
                        <Th right>Amount</Th>
                        <Th>Status</Th>
                        <Th right>Actions</Th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {loading ? <LoadingRow /> : trips.length === 0 ? <EmptyState message="No trips found" /> : trips.map(t => (
                        <tr key={t.uuid} className="hover:bg-gray-50/60 transition-colors">
                            <Td><span className="font-mono text-xs font-semibold text-blue-600">{t.trip_number}</span></Td>
                            <Td>{formatDate(t.trip_date)}</Td>
                            <Td className="hidden md:table-cell">{t.vehicle?.name ?? '—'}</Td>
                            <Td className="hidden lg:table-cell text-gray-500">
                                {t.customer?.name ?? t.customer?.company_name ?? <span className="italic text-gray-400">Walk-in</span>}
                            </Td>
                            <Td className="hidden md:table-cell">
                                <span className="text-xs text-gray-500">{t.trip_type}</span>
                            </Td>
                            <Td right><span className="font-semibold text-gray-900">{formatCurrency(t.total_amount)}</span></Td>
                            <Td>
                                <div className="flex flex-col gap-1">
                                    <Badge color={tripStatusColor(t.status)}>{t.status}</Badge>
                                    <Badge color={paymentStatusColor(t.payment_status)}>{t.payment_status}</Badge>
                                </div>
                            </Td>
                            <Td right>
                                <div className="flex items-center justify-end gap-3">
                                    <button onClick={() => openEdit(t)} disabled={t.is_invoiced}
                                        className="text-xs font-medium text-blue-600 hover:text-blue-800 disabled:opacity-30 disabled:cursor-not-allowed">
                                        Edit
                                    </button>
                                    <button onClick={() => setDeleteTarget(t)} disabled={t.is_invoiced}
                                        className="text-xs font-medium text-red-500 hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed">
                                        Delete
                                    </button>
                                </div>
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </Table>
            <Pagination page={page} totalPages={totalPages} total={total} onPage={setPage} />

            {/* Add/Edit Modal */}
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Trip' : 'Add Trip'} size="xl">
                <div className="space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input label="Trip Date" type="date" value={form.trip_date} onChange={set('trip_date')} error={formErrors.trip_date} required />
                        <Select label="Trip Type" value={form.trip_type} onChange={set('trip_type')} error={formErrors.trip_type} required>
                            {TRIP_TYPES.map(t => <option key={t}>{t}</option>)}
                        </Select>
                        <Select label="Vehicle" value={form.vehicle_id} onChange={set('vehicle_id')} error={formErrors.vehicle_id} required>
                            <option value="">Select vehicle</option>
                            {vehicles.map(v => <option key={v.id} value={v.id}>{v.name} ({v.registration_number})</option>)}
                        </Select>
                        <Select label="Customer" value={form.customer_id} onChange={set('customer_id')}>
                            <option value="">Walk-in / No customer</option>
                            {customers.map(c => <option key={c.id} value={c.id}>{c.name ?? c.company_name}</option>)}
                        </Select>
                        <Input label="Pickup Location" value={form.pickup_location} onChange={set('pickup_location')} />
                        <Input label="Drop Location" value={form.drop_location} onChange={set('drop_location')} />
                        <Input label="Driver Name" value={form.driver_name} onChange={set('driver_name')} />
                        <Select label="Status" value={form.status} onChange={set('status')} required>
                            <option value="draft">Draft</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </Select>
                        <Input label="Start Odometer (km)" type="number" value={form.start_odometer} onChange={set('start_odometer')} min="0" />
                        <Input label="End Odometer (km)" type="number" value={form.end_odometer} onChange={set('end_odometer')} min="0" />
                    </div>

                    {/* Fare section */}
                    <div className="rounded-xl bg-gray-50 border border-gray-100 p-4">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Fare & Charges</p>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <Input label="Fare (₹)" type="number" value={form.fare_amount} onChange={set('fare_amount')} error={formErrors.fare_amount} required min="0" step="0.01" />
                            <Input label="Extra Charges (₹)" type="number" value={form.additional_charges} onChange={set('additional_charges')} min="0" step="0.01" />
                            <Input label="Discount (₹)" type="number" value={form.discount} onChange={set('discount')} min="0" step="0.01" />
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">GST</label>
                                <label className="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" checked={form.is_taxable}
                                        onChange={e => setForm(p => ({ ...p, is_taxable: e.target.checked }))}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    <span className="text-sm text-gray-700">Apply GST</span>
                                </label>
                            </div>
                            {form.is_taxable && (
                                <Input label="Tax Rate (%)" type="number" value={form.tax_rate} onChange={set('tax_rate')} min="0" max="100" step="0.01" />
                            )}
                        </div>

                        {/* Live preview */}
                        <div className="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 bg-white rounded-lg border border-gray-100 p-3 text-sm">
                            <div><p className="text-xs text-gray-400 mb-0.5">Taxable</p><p className="font-medium text-gray-700">{formatCurrency(preview.taxable)}</p></div>
                            <div><p className="text-xs text-gray-400 mb-0.5">Tax</p><p className="font-medium text-gray-700">{formatCurrency(preview.tax)}</p></div>
                            <div><p className="text-xs text-gray-400 mb-0.5">Total</p><p className="font-bold text-blue-700">{formatCurrency(preview.total)}</p></div>
                            <div><p className="text-xs text-gray-400 mb-0.5">Balance</p><p className={`font-bold ${preview.balance > 0 ? 'text-red-600' : 'text-green-600'}`}>{formatCurrency(preview.balance)}</p></div>
                        </div>
                    </div>

                    {/* Payment */}
                    <div className="grid grid-cols-2 gap-4">
                        <Select label="Payment Mode" value={form.payment_mode} onChange={set('payment_mode')}>
                            <option value="">Select mode</option>
                            {PAYMENT_MODES.map(m => <option key={m}>{m}</option>)}
                        </Select>
                        <Input label="Amount Received (₹)" type="number" value={form.amount_received} onChange={set('amount_received')} min="0" step="0.01" />
                    </div>

                    <Textarea label="Notes" value={form.notes} onChange={set('notes')} rows={2} />
                </div>

                <div className="flex justify-end gap-3 mt-5 pt-4 border-t border-gray-100">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update Trip' : 'Save Trip'}</Button>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!deleteTarget} title="Delete Trip"
                message={`Delete trip ${deleteTarget?.trip_number}? This cannot be undone.`}
                confirmLabel="Delete" danger
                onConfirm={handleDelete} onCancel={() => setDeleteTarget(null)} loading={deleting}
            />
        </div>
    );
}
