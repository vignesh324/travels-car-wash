import axios from 'axios';
import { appPath } from '../utils/appBasePath';

const api = axios.create({
    baseURL: appPath('/api'),
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && window.location.pathname !== appPath('/login')) {
            window.location.href = appPath('/login');
        }
        return Promise.reject(error);
    }
);

export default api;
