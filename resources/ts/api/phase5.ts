import api from './client';
import { appPath } from '../utils/appBasePath';

export const reportsApi = {
    revenue:  (params: Record<string, unknown>) => api.get('/reports/revenue', { params }),
    expenses: (params: Record<string, unknown>) => api.get('/reports/expenses', { params }),
    trips:    (params: Record<string, unknown>) => api.get('/reports/trips', { params }),
    invoices: (params: Record<string, unknown>) => api.get('/reports/invoices', { params }),
    payments: (params: Record<string, unknown>) => api.get('/reports/payments', { params }),
    carwash:  (params: Record<string, unknown>) => api.get('/reports/car-wash', { params }),
    exportTripsUrl:    (params: Record<string, unknown>) => appPath('/api/reports/export/trips?') + new URLSearchParams(params as Record<string, string>).toString(),
    exportExpensesUrl: (params: Record<string, unknown>) => appPath('/api/reports/export/expenses?') + new URLSearchParams(params as Record<string, string>).toString(),
    exportInvoicesUrl: (params: Record<string, unknown>) => appPath('/api/reports/export/invoices?') + new URLSearchParams(params as Record<string, string>).toString(),
};

export const activityLogsApi = {
    datatable: (params: Record<string, unknown>) =>
        api.get('/activity-logs', { params: { ...params, datatable: 1 } }),
};
