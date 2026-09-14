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

export default {
    getMe: () => api.get('/me'),
    getMyVehicles: (params = {}) => api.get('/my-vehicles', { params }),
    getVehicles: (params = {}) => api.get('/vehicles', { params }),
    getVehicle: (id) => api.get(`/vehicles/${id}`),
    getVehicleMaintenances: (id, params = {}) => api.get(`/vehicles/${id}/maintenances`, { params }),
    getVehicleTimeline: (id) => api.get(`/vehicles/${id}/timeline`),
    requestVehiclePdfExport: (id) => api.post(`/vehicles/${id}/export-pdf`),
    getVehiclePdfExportStatus: (exportId) => api.get(`/vehicle-pdf-exports/${exportId}`),
    getMaintenances: (params = {}) => api.get('/maintenances', { params }),
    getMaintenance: (id) => api.get(`/maintenances/${id}`),
    getWorkshops: (params = {}) => api.get('/workshops', { params }),
};

export { api };
