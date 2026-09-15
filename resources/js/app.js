import './bootstrap';
import 'preline';
import { initFormUx } from './form-ux';
import { initUserPortalApi } from './user-portal';
import { initVehicleIdentity } from './vehicle-identity';

document.addEventListener('DOMContentLoaded', () => {
    initFormUx();
    initUserPortalApi();
    initVehicleIdentity();
});
