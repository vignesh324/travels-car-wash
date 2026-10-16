import api from './client';
import type { Invoice, Payment } from '../types';

export const invoicesApi = {
    datatable: (params: Record<string, unknown>) =>
        api.get('/invoices', { params: { ...params, datatable: 1 } }),
    create: (data: Record<string, unknown>) => api.post<Invoice>('/invoices', data),
    get: (uuid: string) => api.get<Invoice>(`/invoices/${uuid}`),
    update: (uuid: string, data: Record<string, unknown>) => api.put<Invoice>(`/invoices/${uuid}`, data),
    cancel: (uuid: string, reason: string) =>
        api.post(`/invoices/${uuid}/cancel`, { reason }),
    pdfPath: (uuid: string) => `/invoices/${uuid}/pdf`,
};

export const paymentsApi = {
    datatable: (params: Record<string, unknown>) =>
        api.get('/payments', { params: { ...params, datatable: 1 } }),
    create: (data: Record<string, unknown>) => api.post<Payment>('/payments', data),
    get: (uuid: string) => api.get<Payment>(`/payments/${uuid}`),
    allocate: (uuid: string, allocations: Array<{ invoice_id: number; amount: number; notes?: string }>) =>
        api.post(`/payments/${uuid}/allocate`, { allocations }),
    reverse: (uuid: string, reason: string) =>
        api.post(`/payments/${uuid}/reverse`, { reason }),
    receiptPath: (uuid: string) => `/payments/${uuid}/receipt`,
};

// Customers with outstanding invoices (for payment allocation)
export const customerInvoicesApi = {
    outstanding: (customerId: number) =>
        api.get<Invoice[]>('/invoices', {
            params: { customer_id: customerId, status: 'issued', datatable: 0 },
        }),
};
