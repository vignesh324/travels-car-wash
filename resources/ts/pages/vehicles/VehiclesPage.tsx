import { useState, useEffect, useCallback } from 'react';
import { vehiclesApi } from '../../api/resources';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Badge } from '../../components/ui/Card';
import type { Vehicle, ApiError } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const FUEL_TYPES = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Other'];
const OWNERSHIP_TYPES = ['Owned', 'Leased', 'Attached'];
const VEHICLE_TYPES = ['Car', 'Van', 'Bus', 'Tempo', 'SUV', 'Other'];

const emptyForm = {
    registration_number: '', name: '', model: '', type: '', manufacturer: '',
    fuel_type: 'Diesel', seating_capacity: '', ownership_type: 'Owned',
    purchase_date: '', default_driver_name: '', default_driver_phone: '',
    insurance_expiry: '', permit_expiry: '', fitness_expiry: '', puc_expiry: '',
    current_odometer: '', is_active: true, notes: '',
};

type FormData = typeof emptyForm;

export default function VehiclesPage() {
    const [vehicles, setVehicles] = useState<Vehicle[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [filterType, setFilterType] = useState('');
    const [filterFuel, setFilterFuel] = useState('');
    const [filterActive, setFilterActive] = useState('');
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 15;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Vehicle | null>(null);
    const [form, setForm] = useState<FormData>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState<Vehicle | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchVehicles = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await vehiclesApi.datatable({
                start: (page - 1) * perPage,
                length: perPage,
                'search[value]': search,
                type: filterType,
                fuel_type: filterFuel,
                is_active: filterActive,
            });
            setVehicles(data.data ?? []);
            setTotal(data.recordsFiltered ?? 0);
        } catch {
            toast.error('Failed to load vehicles');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterType, filterFuel, filterActive]);

    useEffect(() => { fetchVehicles(); }, [fetchVehicles]);

    const openAdd = () => {
        setEditing(null);
        setForm(emptyForm);
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (v: Vehicle) => {
        setEditing(v);
        setForm({
            registration_number: v.registration_number,
            name: v.name,
            model: v.model ?? '',
            type: v.type ?? '',
            manufacturer: v.manufacturer ?? '',
            fuel_type: v.fuel_type,
            seating_capacity: v.seating_capacity?.toString() ?? '',
            ownership_type: v.ownership_type,
            purchase_date: v.purchase_date ?? '',
            default_driver_name: v.default_driver_name ?? '',
            default_driver_phone: v.default_driver_phone ?? '',
            insurance_expiry: v.insurance_expiry ?? '',
            permit_expiry: v.permit_expiry ?? '',
            fitness_expiry: v.fitness_expiry ?? '',
            puc_expiry: v.puc_expiry ?? '',
            current_odometer: v.current_odometer?.toString() ?? '',
            is_active: v.is_active,
            notes: v.notes ?? '',
        });
        setFormErrors({});
        setModalOpen(true);
    };

    const handleSave = async () => {
        setSaving(true);
        setFormErrors({});
        try {
            const payload = {
                ...form,
                seating_capacity: form.seating_capacity ? Number(form.seating_capacity) : null,
                current_odometer: form.current_odometer ? Number(form.current_odometer) : null,
            };
            if (editing) {
                await vehiclesApi.update(editing.uuid, payload);
                toast.success('Vehicle updated');
            } else {
                await vehiclesApi.create(payload);
                toast.success('Vehicle added');
            }
            setModalOpen(false);
            fetchVehicles();
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
            await vehiclesApi.delete(deleteTarget.uuid);
            toast.success('Vehicle deleted');
            setDeleteTarget(null);
            fetchVehicles();
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

    const totalPages = Math.ceil(total / perPage);

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Vehicles</h1>
                    <p className="text-sm text-gray-500 mt-0.5">{total} vehicle{total !== 1 ? 's' : ''}</p>
                </div>
                <Button onClick={openAdd}>+ Add Vehicle</Button>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-3">
                <input
                    type="text"
                    placeholder="Search registration, name..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="flex-1 min-w-48 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <select value={filterType} onChange={e => { setFilterType(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Types</option>
                    {VEHICLE_TYPES.map(t => <option key={t}>{t}</option>)}
                </select>
                <select value={filterFuel} onChange={e => { setFilterFuel(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Fuels</option>
                    {FUEL_TYPES.map(t => <option key={t}>{t}</option>)}
                </select>
                <select value={filterActive} onChange={e => { setFilterActive(e.target.value); setPage(1); }} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Registration</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Name</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Type</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Fuel</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Driver</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {loading ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">Loading...</td></tr>
                            ) : vehicles.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-400">No vehicles found</td></tr>
                            ) : vehicles.map(v => (
                                <tr key={v.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium text-blue-700">{v.registration_number}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900">{v.name}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{v.type ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{v.fuel_type}</td>
                                    <td className="px-4 py-3 text-gray-600 hidden lg:table-cell">{v.default_driver_name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <Badge color={v.is_active ? 'green' : 'gray'}>{v.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openEdit(v)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                            <button onClick={() => setDeleteTarget(v)} className="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
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
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Vehicle' : 'Add Vehicle'} size="lg">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input label="Registration Number" {...f('registration_number')} required placeholder="MH12AB1234" />
                    <Input label="Vehicle Name" {...f('name')} required placeholder="Toyota Innova" />
                    <Input label="Model" {...f('model')} placeholder="Crysta" />
                    <Select label="Type" {...f('type')}>
                        <option value="">Select type</option>
                        {VEHICLE_TYPES.map(t => <option key={t}>{t}</option>)}
                    </Select>
                    <Input label="Manufacturer" {...f('manufacturer')} placeholder="Toyota" />
                    <Select label="Fuel Type" {...f('fuel_type')} required>
                        {FUEL_TYPES.map(t => <option key={t}>{t}</option>)}
                    </Select>
                    <Input label="Seating Capacity" type="number" {...f('seating_capacity')} min="1" max="100" />
                    <Select label="Ownership Type" {...f('ownership_type')} required>
                        {OWNERSHIP_TYPES.map(t => <option key={t}>{t}</option>)}
                    </Select>
                    <Input label="Purchase Date" type="date" {...f('purchase_date')} />
                    <Input label="Current Odometer (km)" type="number" {...f('current_odometer')} min="0" />
                    <Input label="Default Driver Name" {...f('default_driver_name')} />
                    <Input label="Default Driver Phone" {...f('default_driver_phone')} type="tel" />

                    <div className="col-span-full">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Document Expiry Dates</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Input label="Insurance Expiry" type="date" {...f('insurance_expiry')} />
                            <Input label="Permit Expiry" type="date" {...f('permit_expiry')} />
                            <Input label="Fitness Expiry" type="date" {...f('fitness_expiry')} />
                            <Input label="PUC Expiry" type="date" {...f('puc_expiry')} />
                        </div>
                    </div>

                    <div className="col-span-full">
                        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.is_active as unknown as boolean}
                                onChange={e => setForm(prev => ({ ...prev, is_active: e.target.checked }))}
                                className="rounded border-gray-300 text-blue-600"
                            />
                            Active vehicle
                        </label>
                    </div>

                    <div className="col-span-full">
                        <Textarea label="Notes" {...f('notes')} placeholder="Any additional notes..." />
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update Vehicle' : 'Add Vehicle'}</Button>
                </div>
            </Modal>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Vehicle"
                message={`Are you sure you want to delete "${deleteTarget?.name} (${deleteTarget?.registration_number})"? This action can be undone by an administrator.`}
                confirmLabel="Delete"
                danger
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
                loading={deleting}
            />
        </div>
    );
}
