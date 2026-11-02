import axios from 'axios';

const TOKEN_KEY = 'uchebnik_token';
const NOTICE_KEY = 'uchebnik_notice';

export const tokenStorage = {
    get: () => localStorage.getItem(TOKEN_KEY),
    set: (token) => localStorage.setItem(TOKEN_KEY, token),
    clear: () => localStorage.removeItem(TOKEN_KEY),
};

export const authNotice = {
    set: (text) => sessionStorage.setItem(NOTICE_KEY, text),
    take() {
        const text = sessionStorage.getItem(NOTICE_KEY);
        sessionStorage.removeItem(NOTICE_KEY);
        return text;
    },
};

const http = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

http.interceptors.request.use((config) => {
    const token = tokenStorage.get();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;
        const accessLost = status === 403 && data?.code === 'user_disabled';

        if ((status === 401 || accessLost) && tokenStorage.get()) {
            tokenStorage.clear();
            if (accessLost && data?.message) authNotice.set(data.message);
            window.location.assign('/login');
        }
        return Promise.reject(error);
    },
);

export function parseError(error) {
    const data = error.response?.data;
    return {
        status: error.response?.status,
        message: data?.message || 'Қате. Қайталап көріңіз.',
        errors: data?.errors || {},
    };
}

export default http;
