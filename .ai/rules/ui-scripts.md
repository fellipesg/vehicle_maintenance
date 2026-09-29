---
paths:
  - 'resources/js/**'
---

# UI Scripts

## Preline plugins come from the non-auto entries
Import Preline plugins as `import HSOverlay from 'preline/plugins/overlay-non-auto'` and start them in initPreline() (resources/js/ui/preline.js). A bare `import 'preline/plugins/<name>'` (or `import 'preline'`) is dropped by the build, because Preline's package.json sideEffects lists only index and helper-clipboard. Only the plugins in use are loaded: overlay, dropdown, remove-element. Tabs and tooltips use resources/js/ui/tabs.js and tooltip.js (Preline's are not accessible). preline.js also restores native Enter/Space activation inside overlays and dropdowns and wraps Shift+Tab.

## Every ui/* module is idempotent and started by app.js
resources/js/app.js imports and calls each init (initPreline, initDialogs, initConfirm, initToasts, initTabs, initTooltips, initFlash, initFileInputs, initImageCroppers, initPasswordToggles, initFormErrors, initSwitches, initSubmitBusy, initCommandPalettes, initCopyButtons, initLightboxes, initTextareaCounters, initSidebarToggles) inside initApp; OverlayComponentsContractTest checks the list. For HTML inserted later, call the component's init again with the fragment as root.

Screen scripts also start in initApp for every portal and act only where they find their own data-*: initFormUx, initProvenanceFilters (provenance-ui.js), initVehicleTimelines and initVehicleDetails (vehicle-timeline-portal.js), initUserPortal (PDF export), initProvenanceActions (only the seal's native "Compartilhar"; copying is x-ui.copy-button), initAdminMaintenancesFilters, initAdmin (admin.js: maps and blog editor), initLanding, initMaintenanceItems (OS items and workshop-maintenance-form) and initGarageMaintenanceForms. vehicle-identity.js and maintenance-photos.js are gone (x-ui.copy-button and x-ui.lightbox). A new screen script gets its init here, never an inline <script> in the view. initDialogs reopens <dialog data-dialog-open-on-load> (form that came back with errors) in every portal.

## Dialogs: listen to ui:dialog-close
On dialogs opened with openDialog() (resources/js/ui/dialog.js) listen to the ui:dialog-close event, not the native close event: Chrome 154 stops firing close after an Esc.

## No native boxes; escape HTML
No window.confirm/alert: confirmAction() and toast() from resources/js/ui. Template strings escape data with escapeHtml from resources/js/utils/html.js and draw icons with icon() from resources/js/ui/icons.js. Live validation (form-ux.js) marks fields with data-state and aria-invalid and only rewrites legacy [data-field-hint] hints, never the x-ui.field {id}-hint.
