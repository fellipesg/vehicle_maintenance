import { initProvenanceStripFilters } from './provenance-ui';

export function initVehicleSearchFilters() {
    const root = document.querySelector('[data-vehicle-search-results]');
    if (!root) {
        return;
    }

    const dataEl = document.getElementById('vehicle-search-maintenances-json');
    if (!dataEl) {
        return;
    }

    let maintenances = [];
    try {
        maintenances = JSON.parse(dataEl.textContent);
    } catch (error) {
        console.error('Invalid vehicle search maintenances JSON', error);

        return;
    }

    initProvenanceStripFilters(root, maintenances, {
        basePath: '/usuario',
        linkMaintenances: false,
    });
}
