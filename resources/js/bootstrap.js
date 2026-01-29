import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add locale header to all requests
window.axios.interceptors.request.use(config => {
    const locale = localStorage.getItem('locale') || 'vi';
    config.headers['Accept-Language'] = locale;
    return config;
});
