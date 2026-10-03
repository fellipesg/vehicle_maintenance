---
paths:
  - 'resources/views/components/ui/**'
  - 'resources/views/**'
---

# UI Components

## Build screens with x-ui.*, not loose markup
Anonymous Blade components in resources/views/components/ui (used as <x-ui.name>) are the design system: button, link, icon-button, copy-button, badge, card, stat, avatar, empty-state, skeleton, spinner, alert, page-header, breadcrumb, container, table, section, field, label, input, input-group, select, textarea (counter + maxlength: "120/4.000", announced every 10%), checkbox, radio, radio-cards, switch, fieldset, form-section, form-errors, file-input, image-cropper, dialog, confirm-dialog, sheet, dropdown, tabs, tooltip, toaster, flash, nav, sidebar-toggle, stepper, segmented, command, lightbox, icon. New or touched screens use them instead of hand-written markup or new utility soups. The .btn-*, .badge-* and .stat-card classes no longer exist; a button created in JS takes buttonClass()/setButtonVariant() from resources/js/ui/button.js (mirror of x-ui.button, checked by ButtonScriptTest). ObsidianUI (obsidianui.dev) is only a visual reference: no React, motion, gsap or lenis, and no new dependency.

## Copy, photos and choices have their own component
Copying a value (chassis, seal code and link, invite) is <x-ui.copy-button :value label copied-label>: check icon for 1.5s and the copied label announced, manual-copy field when the clipboard is blocked; never a hand-written data-copy-* button. Service-order photos open in <x-ui.lightbox> with contextual alt "Foto N de T — serviço, data. grupo". A single choice with descriptions (blog "Situação") is <x-ui.radio-cards> inside <x-ui.fieldset>. Wizard steps are <x-ui.stepper> (the x-ui.steps alias is gone).

## Covers and fixed-ratio images use x-ui.image-cropper
Capas e imagens com proporção fixa usam <x-ui.image-cropper aspect="16:9|9:16">; nunca input de arquivo cru. O recorte sai em JPEG de no máximo 1920 px (qualidade 0,85) e Cancelar mantém a foto anterior daquela orientação.

## One H1 per page, from x-ui.page-header
Every page has exactly one <h1>, drawn by <x-ui.page-header title="..."> (primary action in the actions slot, trail in the breadcrumb slot). Portal pages (account, admin, garage, notifications, public, user, vehicles, workshop) follow the page template: page-header, sections in x-ui.card/x-ui.section, complete empty/error states (x-ui.empty-state, x-ui.alert), forms with x-ui.field + x-ui.form-errors and a form-actions bar. The owner portal is server-rendered: no "Carregando…" placeholders. DesignSystemGuardrailsTest checks both. The topbar never carries a heading. CTA labels follow the glossary and Portal::primaryAction(): "Adicionar veículo", "Adicionar ao estoque", "Registrar manutenção", "Nova OS" — verb + object, no "+" in the text (use icon="plus").

## Destructive actions confirm with data-confirm
Never onsubmit="return confirm(...)", window.confirm or window.alert. Put data-confirm="consequence. Não é possível desfazer.", data-confirm-title, data-confirm-action-label ("Excluir marca") and data-confirm-variant="danger" on the form (Blade escapes them). In JS use confirmAction() from resources/js/ui/confirm.js and toast({ variant: 'error' }) from resources/js/ui/toast.js. "Excluir" deletes for good and the flash says "excluído(a)"; "Remover" only takes something out of a list.

## Icons only through x-ui.icon / icons.js; no emoji
Icons are local Heroicons (MIT) in resources/js/ui/icons.json, rendered by <x-ui.icon name="..."> (App\Support\IconLibrary) in Blade and icon() from resources/js/ui/icons.js in JS templates. A new icon goes into both the outline and the solid variant of icons.json. Emoji are never icons or copy decoration (✓ and ○ stay only as aria-hidden text glyphs in criteria lists).

## Component props are validated by UiProps
x-ui.* components validate props with App\Support\UiProps (oneOf, required): it throws in local/testing and, in other environments, reports the error and falls back to the default. Keep that contract in new components and add a tests/Feature/Ui/<Name>Test.php using $this->blade(...)/InspectsUiMarkup.

## Inline components end with a @php block
A component meant to sit inside running text (button, badge, icon, spinner...) ends the file with @php ... @endphp right after its closing tag, so the file's final newline does not add a space after it. The comment inside that block must not contain '?>' nor '@endphp'.

## Busy state and dismissible notices are automatic
resources/js/ui/submit-busy.js marks the submitter of any form as busy after submit (disabled, aria-busy, data-loading-label text, spinner) and blocks a second submit. Give submit buttons loading-label="Salvando…" on x-ui.button. A form whose response does not leave the page (file download) needs data-submit-busy="off". <x-ui.alert dismissible> is removed by Preline's HSRemoveElement and resources/js/ui/flash.js moves focus to #conteudo; x-ui.flash reads session('status'), so views never print session('status') again.

## Brand spelling and guardrails
Visible brand text is "RevisaLog" (titles, copy, alt, e-mail, og). Domain and addresses stay revisalog.com.br. tests/Feature/DesignSystem/DesignSystemGuardrailsTest.php is a ratchet (raw palette and '!' per file, confirm/alert, emoji, object-cover, transition-all, tiny text, admin H1, motion): ceilings only go down, new files start at zero. After Phase 3 everything is at zero except the raw amber of the dark landing phone-timeline mock (3).

## Domain components for vehicles and maintenances
Vehicle page, lists and maintenance detail are the same in every portal: <x-vehicle.detail> (one H1, cover, identity, timeline, history, documents; portal via App\Enums\Portal), <x-vehicle.card> (listings), <x-maintenance.list> (+ App\Support\Maintenance\MaintenanceListFilters in the controller) and @include('maintenances._detail', ['maintenance' => ..., 'portal' => Portal::X]). Provenance pieces are x-provenance-seal, x-provenance-card, x-provenance-marker (aria-hidden) and x-provenance-legend. Rules in .ai/rules/domain-components.md.
