import { useState, useEffect, type FormEvent } from 'react';
import api from '../../api/client';
import { useAuth } from '../../hooks/useAuth';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/FormFields';
import type { BusinessProfile, ApiError } from '../../types';
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

export default function SettingsPage() {
    const { refreshUser } = useAuth();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        business_name: '', address: '', phone: '', email: '', gstin: '',
        state: '', state_code: '', upi_id: '', invoice_prefix: 'INV',
        receipt_prefix: 'RCP', financial_year_start: '04-01',
        default_payment_terms: '', invoice_footer: '',
        bank_name: '', bank_account: '', bank_ifsc: '', bank_branch: '',
    });

    useEffect(() => {
        api.get<BusinessProfile>('/settings/business').then(({ data }) => {
            const bd = data.bank_details as Record<string, string> | null;
            setForm({
                business_name: data.business_name ?? '',
                address: data.address ?? '',
                phone: data.phone ?? '',
                email: data.email ?? '',
                gstin: data.gstin ?? '',
                state: data.state ?? '',
                state_code: data.state_code ?? '',
                upi_id: data.upi_id ?? '',
                invoice_prefix: data.invoice_prefix ?? 'INV',
                receipt_prefix: data.receipt_prefix ?? 'RCP',
                financial_year_start: data.financial_year_start ?? '04-01',
                default_payment_terms: data.default_payment_terms ?? '',
                invoice_footer: data.invoice_footer ?? '',
                bank_name: bd?.bank_name ?? '',
                bank_account: bd?.account_number ?? '',
                bank_ifsc: bd?.ifsc ?? '',
                bank_branch: bd?.branch ?? '',
            });
        }).finally(() => setLoading(false));
    }, []);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = {
                ...form,
                bank_details: {
                    bank_name: form.bank_name,
                    account_number: form.bank_account,
                    ifsc: form.bank_ifsc,
                    branch: form.bank_branch,
                },
            };
            await api.post('/settings/business', payload);
            await refreshUser();
            toast.success('Settings saved');
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as ApiError;
                if (data?.errors) {
                    const flat: Record<string, string> = {};
                    Object.entries(data.errors).forEach(([k, v]) => { flat[k] = v[0]; });
                    setErrors(flat);
                } else {
                    toast.error(data?.message ?? 'Save failed');
                }
            }
        } finally {
            setSaving(false);
        }
    };

    const f = (key: keyof typeof form) => ({
        value: form[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
            setForm(prev => ({ ...prev, [key]: e.target.value })),
        error: errors[key],
    });

    if (loading) return <div className="text-center py-12 text-gray-400">Loading settings...</div>;

    return (
        <div className="max-w-3xl">
            <h1 className="text-2xl font-bold text-gray-900 mb-6">Business Settings</h1>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Business Info */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Business Information</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="col-span-full">
                            <Input label="Business Name" {...f('business_name')} required />
                        </div>
                        <Input label="Phone" type="tel" {...f('phone')} />
                        <Input label="Email" type="email" {...f('email')} />
                        <Input label="GSTIN" {...f('gstin')} placeholder="22AAAAA0000A1Z5" />
                        <Select label="State" {...f('state')}>
                            <option value="">Select state</option>
                            {INDIAN_STATES.map(s => <option key={s}>{s}</option>)}
                        </Select>
                        <Input label="State Code" {...f('state_code')} placeholder="27" maxLength={3} />
                        <div className="col-span-full">
                            <Textarea label="Address" {...f('address')} />
                        </div>
                    </div>
                </div>

                {/* Invoice Settings */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Invoice Settings</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input label="Invoice Prefix" {...f('invoice_prefix')} placeholder="INV" maxLength={20} />
                        <Input label="Receipt Prefix" {...f('receipt_prefix')} placeholder="RCP" maxLength={20} />
                        <Input label="Financial Year Start (MM-DD)" {...f('financial_year_start')} placeholder="04-01" />
                        <Input label="Default Payment Terms" {...f('default_payment_terms')} placeholder="Net 30" />
                        <div className="col-span-full">
                            <Textarea label="Invoice Footer / Terms" {...f('invoice_footer')} rows={3} />
                        </div>
                    </div>
                </div>

                {/* Bank Details */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Bank & Payment Details</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input label="Bank Name" {...f('bank_name')} />
                        <Input label="Account Number" {...f('bank_account')} />
                        <Input label="IFSC Code" {...f('bank_ifsc')} className="uppercase" />
                        <Input label="Branch" {...f('bank_branch')} />
                        <div className="col-span-full">
                            <Input label="UPI ID" {...f('upi_id')} placeholder="business@upi" />
                        </div>
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" loading={saving} size="lg">Save Settings</Button>
                </div>
            </form>
        </div>
    );
}
