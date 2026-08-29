import './bootstrap';
import 'preline';
import { initFormUx } from './form-ux';
import { initUserPortalApi } from './user-portal';

document.addEventListener('DOMContentLoaded', () => {
    initFormUx();
    initUserPortalApi();
});
