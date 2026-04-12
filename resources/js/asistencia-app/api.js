import axios from 'axios';

const api = axios.create({
    baseURL: '/asistencia-app',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Inject CSRF token
api.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

export default api;
