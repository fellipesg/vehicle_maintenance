import './bootstrap';
import 'preline';
import { initFormUx } from './form-ux';
import { initUserPortalApi } from './user-portal';
import { initVehicleIdentity } from './vehicle-identity';
import { initProvenanceActions } from './provenance-actions';
import { initVehicleSearchFilters } from './vehicle-search-filters';

document.addEventListener('DOMContentLoaded', () => {
    initFormUx();
    initUserPortalApi();
    initVehicleIdentity();
    initProvenanceActions();
    initVehicleSearchFilters();
});
