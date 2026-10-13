import api from './client';
import type { Trip, VehicleExpense, BusinessExpense, ExpenseCategory, DashboardSummary } from '../types';

export const tripsApi = {
    list: (params?: Record<string, unknown>) => api.get<Trip[]>('/trips', { params }),
    datatable: (params: Record<string, unknown>) => api.get('/trips', { params: { ...params, datatable: 1 } }),
    create: (data: Partial<Trip>) => api.post<Trip>('/trips', data),
    get: (uuid: string) => api.get<Trip>(`/trips/${uuid}`),
    update: (uuid: string, data: Partial<Trip>) => api.put<Trip>(`/trips/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/trips/${uuid}`),
};

export const vehicleExpensesApi = {
    datatable: (params: Record<string, unknown>) => api.get('/expenses/vehicle', { params: { ...params, datatable: 1 } }),
    create: (data: Partial<VehicleExpense>) => api.post<VehicleExpense>('/expenses/vehicle', data),
    get: (uuid: string) => api.get<VehicleExpense>(`/expenses/vehicle/${uuid}`),
    update: (uuid: string, data: Partial<VehicleExpense>) => api.put<VehicleExpense>(`/expenses/vehicle/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/expenses/vehicle/${uuid}`),
};

export const businessExpensesApi = {
    datatable: (params: Record<string, unknown>) => api.get('/expenses/business', { params: { ...params, datatable: 1 } }),
    create: (data: Partial<BusinessExpense>) => api.post<BusinessExpense>('/expenses/business', data),
    get: (uuid: string) => api.get<BusinessExpense>(`/expenses/business/${uuid}`),
    update: (uuid: string, data: Partial<BusinessExpense>) => api.put<BusinessExpense>(`/expenses/business/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/expenses/business/${uuid}`),
};

export const expenseCategoriesApi = {
    list: (type?: string) => api.get<ExpenseCategory[]>('/expense-categories', { params: type ? { type } : {} }),
    create: (data: Partial<ExpenseCategory>) => api.post<ExpenseCategory>('/expense-categories', data),
    update: (uuid: string, data: Partial<ExpenseCategory>) => api.put<ExpenseCategory>(`/expense-categories/${uuid}`, data),
};

export const dashboardApi = {
    summary: (date?: string) => api.get<DashboardSummary>('/dashboard/summary', { params: date ? { date } : {} }),
    charts: (days?: number) => api.get('/dashboard/charts', { params: days ? { days } : {} }),
    recent: () => api.get('/dashboard/recent'),
};
