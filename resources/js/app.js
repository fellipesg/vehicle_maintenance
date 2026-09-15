import './bootstrap';
import 'preline';
import { initFormUx } from './form-ux';
import { initUserPortalApi } from './user-portal';
import { initVehicleIdentity } from './vehicle-identity';
import { initProvenanceActions } from './provenance-actions';

document.addEventListener('DOMContentLoaded', () => {
    initFormUx();
    initUserPortalApi();
    initVehicleIdentity();
    initProvenanceActions();
});
