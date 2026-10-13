import api from './client';
import type { User, BusinessProfile } from '../types';

export const authApi = {
    login: (email: string, password: string) =>
        api.post<{ user: User }>('/auth/login', { email, password }),

    logout: () => api.post('/auth/logout'),

    me: () => api.get<{ user: User; business: BusinessProfile | null }>('/auth/me'),

    changePassword: (current_password: string, password: string, password_confirmation: string) =>
        api.put('/auth/change-password', { current_password, password, password_confirmation }),
};
