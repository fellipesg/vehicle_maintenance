---
paths:
  - 'resources/views/components/{vehicle,maintenance}/**'
  - 'resources/views/maintenances/**'
  - 'resources/views/components/provenance-*.blade.php'
---

# Domain Components

## One vehicle page, one list, one maintenance detail for every portal
Vehicle page, lists and maintenance detail use x-vehicle.detail, x-vehicle.card, x-maintenance.list and maintenances/_detail in Proprietário, Lojista, Oficina, Admin and the vehicle search. The portal (App\Enums\Portal) picks links and actions; the controller filters with App\Support\Maintenance\MaintenanceListFilters and paginates on the server. No HTML built in JS for these screens: the old renderVehicleTimeline/renderProvenanceStrip/renderMaintenanceHistoryList renderers are gone and PortalTimelineHtmlEscapingTest keeps them out.

## "Manutenções" lists: provenance cards by month; OS and admin tables are the exception
Proprietário (user.maintenances.index) and Lojista (garage.maintenances.index) show the same thing: x-maintenance.list with group-by-month and heading-level="h2" (provenance cards, the month title sticky at top-16 below the h-16 navbar), then x-provenance-legend. Do not switch them back to layout="table". Two work queues keep their own x-ui.table on purpose, because they need columns the card does not have: Oficina "Ordens de serviço" (workshop/maintenances/_table: Itens / Total, Anexos, Código do selo, sortable Data) and Admin "Manutenções" (admin/maintenances/_results: Registrada por, AJAX filters). x-provenance-legend has no props: it always explains OF Selo da oficina, PR Declarada pelo proprietário and LJ Declarada pelo lojista, since any list can hold both kinds of declared record.

## Provenance filter is a GET form filtered in place
The Todas · Selo da oficina · Declaradas filter sits below the timeline: a <form method="get"> with buttons named verified (aria-pressed, data-submit-busy="off"). Without JS it reloads with ?verified; with JS initProvenanceFilters (resources/js/provenance-ui.js) filters the cards in place and updates the URL. x-vehicle.detail sets provenanceStripMaintenances, maintenances_count and verified_maintenances_count on the $vehicle it receives (no second query); listings eager-load the same relation and counts for x-vehicle.card (no query per card).

## "Registrar manutenção" is one form pattern in every portal
Proprietário (user/maintenances/_form), Lojista (garage/maintenances/create) and Oficina (partials/workshop-maintenance-form, step 1 Veículo outside the form) number their sections with <x-ui.form-section :number> (fieldset + h2 in the legend), label maintenance_type "Serviço realizado", pick service_category in x-ui.select (ServiceCategory::options(), no default) and "Revisão obrigatória do fabricante" in x-ui.checkbox. Proprietário and Lojista open with @include('maintenances._declared-notice', ['declaredBy' => 'owner'|'garage']): the .prov-declared block with the PR/LJ marker and "Aparecerá como Declarada pelo proprietário|lojista" (auto_verify_linked_workshop swaps it for the test-environment alert). The workshop field still differs on purpose: owner picks a network workshop in a select plus a free name; the dealer types in one combobox (resources/js/garage-maintenance-form.js).

## Maintenance detail and provenance copy
maintenances/_detail draws seal, service data, warranty, items, invoices and photos (before and after side by side; they open in <x-ui.lightbox> (resources/js/ui/lightbox.js) with arrows, swipe and alt "Foto 2 de 6 — serviço, data. grupo", not in the old maintenance-photos.js). x-provenance-seal shows "Atualizada em" when the order changed after the seal (Maintenance::wasUpdatedAfterSeal), the workshop logo whole (object-contain p-1.5 bg-surface ring-1 ring-border) and, for a declared record that cites a registered workshop, says it has no Selo da oficina. Declared markers use PR/LJ, never the initials of whoever declared. Plate history "Origem" comes from VehiclePlate::sourceLabel (CRLV-e, Informada manualmente, Cadastro), never the raw source.

## Detalhe da OS usa a forma mínima
maintenances/_detail passa a maintenance por MaintenanceRedactor::redact antes de desenhar, e x-vehicle.detail passa a coleção por redactAll: não leia description, valores, invoices ou photos de um Maintenance de histórico sem passar por eles.
