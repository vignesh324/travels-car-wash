import api from './client';
import type { Vehicle, Customer } from '../types';

export const vehiclesApi = {
    list: (params?: Record<string, unknown>) => api.get<Vehicle[]>('/vehicles', { params }),
    datatable: (params: Record<string, unknown>) => api.get('/vehicles', { params: { ...params, datatable: 1 } }),
    create: (data: Partial<Vehicle>) => api.post<Vehicle>('/vehicles', data),
    get: (uuid: string) => api.get<Vehicle>(`/vehicles/${uuid}`),
    update: (uuid: string, data: Partial<Vehicle>) => api.put<Vehicle>(`/vehicles/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/vehicles/${uuid}`),
    restore: (uuid: string) => api.post(`/vehicles/${uuid}/restore`),
};

export const customersApi = {
    list: (params?: Record<string, unknown>) => api.get<Customer[]>('/customers', { params }),
    datatable: (params: Record<string, unknown>) => api.get('/customers', { params: { ...params, datatable: 1 } }),
    create: (data: Partial<Customer>) => api.post<Customer>('/customers', data),
    get: (uuid: string) => api.get<Customer>(`/customers/${uuid}`),
    update: (uuid: string, data: Partial<Customer>) => api.put<Customer>(`/customers/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/customers/${uuid}`),
    restore: (uuid: string) => api.post(`/customers/${uuid}/restore`),
    statement: (uuid: string, params?: Record<string, unknown>) => api.get(`/customers/${uuid}/statement`, { params }),
};
