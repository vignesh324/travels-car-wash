import { useState, useEffect } from 'react';
import { carWashServicesApi } from '../../api/phase4';
import { Button } from '../../components/ui/Button';
import { Input, Textarea } from '../../components/ui/FormFields';
import { Modal } from '../../components/ui/Modal';
import { Badge } from '../../components/ui/Card';
import { formatCurrency, extractErrors } from '../../utils/format';
import type { CarWashService } from '../../types';
import axios from 'axios';
import toast from 'react-hot-toast';

const emptyForm = {
    name: '', description: '', default_price: '', tax_rate: '0',
    is_active: true, sort_order: '0',
};
type FormData = typeof emptyForm;

export default function CarWashServicesTab() {
    const [services, setServices] = useState<CarWashService[]>([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CarWashService | null>(null);
    const [form, setForm] = useState<FormData>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const fetchServices = () => {
        setLoading(true);
        carWashServicesApi.list()
            .then(({ data }) => setServices(data))
            .catch(() => toast.error('Failed to load services'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchServices(); }, []);

    const openAdd = () => {
        setEditing(null);
        setForm(emptyForm);
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (s: CarWashService) => {
        setEditing(s);
        setForm({
            name: s.name,
            description: s.description ?? '',
            default_price: s.default_price,
            tax_rate: s.tax_rate,
            is_active: s.is_active,
            sort_order: String(s.sort_order),
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
                default_price: parseFloat(form.default_price) || 0,
                tax_rate: parseFloat(form.tax_rate) || 0,
                sort_order: parseInt(form.sort_order) || 0,
            };
            if (editing) {
                await carWashServicesApi.update(editing.uuid, payload as unknown as Partial<CarWashService>);
                toast.success('Service updated');
            } else {
                await carWashServicesApi.create(payload as unknown as Partial<CarWashService>);
                toast.success('Service added');
            }
            setModalOpen(false);
            fetchServices();
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

    const set = (key: keyof FormData) =>
        (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            setForm(prev => ({ ...prev, [key]: e.target.value }));

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <p className="text-sm text-gray-500">{services.length} service{services.length !== 1 ? 's' : ''}</p>
                <Button onClick={openAdd}>+ Add Service</Button>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {loading ? (
                    <div className="text-center py-12 text-gray-400">Loading...</div>
                ) : services.length === 0 ? (
                    <div className="text-center py-12 text-gray-400">No services found</div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Service</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Price</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600 hidden md:table-cell">GST%</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {services.map(s => (
                                <tr key={s.uuid} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{s.name}</p>
                                        {s.description && <p className="text-xs text-gray-500">{s.description}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCurrency(s.default_price)}</td>
                                    <td className="px-4 py-3 text-right text-gray-600 hidden md:table-cell">{s.tax_rate}%</td>
                                    <td className="px-4 py-3">
                                        <Badge color={s.is_active ? 'green' : 'gray'}>{s.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={() => openEdit(s)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Service' : 'Add Service'} size="md">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="col-span-full">
                        <Input label="Service Name" value={form.name} onChange={set('name')} error={formErrors.name} required />
                    </div>
                    <Input label="Default Price (₹)" type="number" value={form.default_price} onChange={set('default_price')} error={formErrors.default_price} required min="0" step="0.01" />
                    <Input label="GST Rate (%)" type="number" value={form.tax_rate} onChange={set('tax_rate')} min="0" max="100" step="0.01" />
                    <Input label="Sort Order" type="number" value={form.sort_order} onChange={set('sort_order')} min="0" />
                    <div className="flex items-center gap-2 mt-1">
                        <input
                            type="checkbox"
                            checked={form.is_active}
                            onChange={e => setForm(prev => ({ ...prev, is_active: e.target.checked }))}
                            className="rounded border-gray-300 text-blue-600"
                        />
                        <span className="text-sm text-gray-700">Active</span>
                    </div>
                    <div className="col-span-full">
                        <Textarea label="Description" value={form.description} onChange={set('description')} rows={2} />
                    </div>
                </div>
                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                    <Button onClick={handleSave} loading={saving}>{editing ? 'Update' : 'Add Service'}</Button>
                </div>
            </Modal>
        </div>
    );
}
