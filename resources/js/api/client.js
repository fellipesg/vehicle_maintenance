import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
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
    downloadVehiclePdfExport: (exportId) => api.get(`/vehicle-pdf-exports/${exportId}/download`, {
        responseType: 'blob',
    }),
    getMaintenances: (params = {}) => api.get('/maintenances', { params }),
    getMaintenance: (id) => api.get(`/maintenances/${id}`),
    getWorkshops: (params = {}) => api.get('/workshops', { params }),
};

export { api };
