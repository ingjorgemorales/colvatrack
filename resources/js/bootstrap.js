import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

function setCsrfToken(value) {
    if (!value) return;

    let token = document.head.querySelector('meta[name="csrf-token"]');
    if (!token) {
        token = document.createElement('meta');
        token.setAttribute('name', 'csrf-token');
        document.head.appendChild(token);
    }

    token.setAttribute('content', value);
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = value;
}

setCsrfToken(document.head.querySelector('meta[name="csrf-token"]')?.content);

window.axios.interceptors.request.use((config) => {
    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }

    return config;
});

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (error.response?.status !== 419 || originalRequest?._csrfRetried || originalRequest?.url === '/csrf-token') {
            return Promise.reject(error);
        }

        originalRequest._csrfRetried = true;

        try {
            const { data } = await window.axios.get('/csrf-token');
            setCsrfToken(data.token);
            originalRequest.headers = originalRequest.headers ?? {};
            originalRequest.headers['X-CSRF-TOKEN'] = data.token;

            return window.axios(originalRequest);
        } catch (refreshError) {
            return Promise.reject(refreshError);
        }
    }
);
