import './bootstrap';
import { initPreline } from './ui/preline';
import { initDialogs } from './ui/dialog';
import { initConfirm } from './ui/confirm';
import { initToasts } from './ui/toast';
import { initTabs } from './ui/tabs';
import { initTooltips } from './ui/tooltip';
import { initFlash } from './ui/flash';
import { initFileInputs } from './ui/file-input';
import { initImageCroppers } from './ui/image-cropper';
import { initPasswordToggles } from './ui/password-toggle';
import { initFormErrors } from './ui/form-errors';
import { initSwitches } from './ui/switch';
import { initSubmitBusy } from './ui/submit-busy';
import { initCommandPalettes } from './ui/command';
import { initCopyButtons } from './ui/copy';
import { initLightboxes } from './ui/lightbox';
import { initTextareaCounters } from './ui/textarea-counter';
import { initSidebarToggles } from './ui/sidebar';
import { initFormUx } from './form-ux';
import { initUserPortal } from './user-portal';
import { initProvenanceActions } from './provenance-actions';
import { initProvenanceFilters } from './provenance-ui';
import { initVehicleDetails, initVehicleTimelines } from './vehicle-timeline-portal';
import { initAdminMaintenancesFilters } from './admin-maintenances-filters';
import { initAdmin } from './admin';
import { initLanding } from './landing';
import { initAnalytics } from './analytics';
import { initMaintenanceItems } from './maintenance-items';
import { initGarageMaintenanceForms } from './garage-maintenance-form';
import { initWorkshopAddressCep } from './workshop-address-cep';

/**
 * Componentes <x-ui.*> primeiro (Preline: overlay, dropdown e remove-element; diálogos, confirmação,
 * toasts, abas, dicas, avisos, campos, recorte de imagem, estado de envio dos formulários, paleta
 * de comandos Ctrl K / ⌘K, copiar, fotos em tela cheia, contador de caracteres e a sidebar
 * recolhível do admin), depois os scripts de cada tela. Cada script de tela só age onde acha o
 * próprio data-*, então todos rodam em todos os portais:
 * - ficha do veículo (x-vehicle.detail, em qualquer portal e na busca): filtro de procedência no
 *   lugar, linha do tempo e a aba certa para #manutencao-{id};
 * - Selo da oficina: o "Compartilhar" nativo (copiar é do x-ui.copy-button);
 * - proprietário (PDF e formulário de manutenção), lojista (formulário de manutenção), oficina
 *   (itens da OS e o CEP preenchendo o endereço do perfil), admin (mapas, editor do blog, filtro de manutenções) e landing.
 * Os init são idempotentes: para HTML inserido depois (innerHTML), chame de novo o do componente com
 * o trecho como raiz, ex.: initPreline(container), initTabs(container) ou initDialogs(container).
 */
function initApp() {
    initPreline();
    initAnalytics();
    initDialogs();
    initConfirm();
    initToasts();
    initTabs();
    initTooltips();
    initFlash();
    initFileInputs();
    initImageCroppers();
    initPasswordToggles();
    initFormErrors();
    initSwitches();
    initSubmitBusy();
    initCommandPalettes();
    initCopyButtons();
    initLightboxes();
    initTextareaCounters();
    initSidebarToggles();

    initFormUx();
    initProvenanceFilters();
    initVehicleTimelines();
    initVehicleDetails();
    initUserPortal();
    initProvenanceActions();
    initAdminMaintenancesFilters();
    initAdmin();
    initLanding();
    initMaintenanceItems();
    initGarageMaintenanceForms();
    initWorkshopAddressCep();
}

// Módulo roda antes do DOMContentLoaded quando vem do @vite; o else cobre carregamento tardio.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp, { once: true });
} else {
    initApp();
}
