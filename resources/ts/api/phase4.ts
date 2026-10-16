import api from './client';
import type { CarWashService, CarWashTransaction } from '../types';

export const carWashServicesApi = {
    list: () => api.get<CarWashService[]>('/car-wash/services'),
    create: (data: Partial<CarWashService>) => api.post<CarWashService>('/car-wash/services', data),
    update: (uuid: string, data: Partial<CarWashService>) =>
        api.put<CarWashService>(`/car-wash/services/${uuid}`, data),
};

export const carWashApi = {
    datatable: (params: Record<string, unknown>) =>
        api.get('/car-wash/transactions', { params: { ...params, datatable: 1 } }),
    create: (data: Record<string, unknown>) =>
        api.post<CarWashTransaction>('/car-wash/transactions', data),
    get: (uuid: string) => api.get<CarWashTransaction>(`/car-wash/transactions/${uuid}`),
    update: (uuid: string, data: Record<string, unknown>) =>
        api.put<CarWashTransaction>(`/car-wash/transactions/${uuid}`, data),
    delete: (uuid: string) => api.delete(`/car-wash/transactions/${uuid}`),
    billPath: (uuid: string) => `/car-wash/transactions/${uuid}/pdf`,
};
