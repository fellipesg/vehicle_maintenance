import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
});

let csrfCookieInitialized = false;
let csrfCookiePromise = null;

function hasCsrfCookie() {
    return document.cookie.split(';').some((cookie) => cookie.trim().startsWith('XSRF-TOKEN='));
}

async function ensureCsrfCookie() {
    if (csrfCookieInitialized || hasCsrfCookie()) {
        csrfCookieInitialized = true;

        return;
    }

    if (!csrfCookiePromise) {
        csrfCookiePromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true })
            .then(() => {
                csrfCookieInitialized = true;
            })
            .finally(() => {
                csrfCookiePromise = null;
            });
    }

    await csrfCookiePromise;
}

api.interceptors.request.use(async (config) => {
    await ensureCsrfCookie();

    return config;
});

/**
 * Chamadas da API usadas pela web. As telas são renderizadas no servidor; só a exportação do PDF do
 * histórico (resources/js/user-portal.js) passa pela API /api/v1, que é a mesma do app Flutter.
 */
export default {
    requestVehiclePdfExport: (id) => api.post(`/vehicles/${id}/export-pdf`),
    getVehiclePdfExportStatus: (exportId) => api.get(`/vehicle-pdf-exports/${exportId}`),
};

export { api };
