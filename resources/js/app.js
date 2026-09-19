import './bootstrap';
import 'preline';
import { initFormUx } from './form-ux';
import { initUserPortalApi } from './user-portal';
import { initVehicleIdentity } from './vehicle-identity';
import { initProvenanceActions } from './provenance-actions';
import { initVehicleSearchFilters } from './vehicle-search-filters';
import { initAdminMaintenancesFilters } from './admin-maintenances-filters';
import { initLanding } from './landing';

document.addEventListener('DOMContentLoaded', () => {
    initFormUx();
    initUserPortalApi();
    initVehicleIdentity();
    initProvenanceActions();
    initVehicleSearchFilters();
    initAdminMaintenancesFilters();
    initLanding();
});
