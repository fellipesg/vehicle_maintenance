---
paths:
  - 'resources/views/components/{vehicle,maintenance,inspection}/**'
  - 'resources/views/maintenances/**'
  - 'resources/views/components/provenance-*.blade.php'
---

# Domain Components

## One vehicle page, one list, one maintenance detail for every portal
Vehicle page, lists and maintenance detail use x-vehicle.detail, x-vehicle.card, x-maintenance.list and maintenances/_detail in Proprietário, Lojista, Oficina, Admin and the vehicle search. The portal (App\Enums\Portal) picks links and actions; the controller filters with App\Support\Maintenance\MaintenanceListFilters and paginates on the server. No HTML built in JS for these screens: the old renderVehicleTimeline/renderProvenanceStrip/renderMaintenanceHistoryList renderers are gone and PortalTimelineHtmlEscapingTest keeps them out.

## A aba Vistorias da ficha é do laudo, e fica fora das contas de manutenção
x-vehicle.detail tem quatro abas: Linha do tempo, Histórico, **Vistorias** (#vistorias, só quando o veículo tem laudo) e Documentos. A aba lista x-inspection.card, da mais recente à mais antiga, e os laudos não entram em nenhuma conta de manutenção — nem no badge do Histórico, nem no x-ui.stat "Procedência", nem na faixa de pontos, nem no filtro Todas/Selo/Declaradas. O card não usa .prov-* nem x-provenance-marker: o trilho e o anel teal afirmam "eu fiz este serviço", e o laudo afirma "eu examinei e atesto o que encontrei" (.ai/rules/inspections.md e theme.md). A coleção passa por App\Support\Inspection\InspectionRedactor::redactAll, que rejeita o laudo suprimido antes de redigir o resto.

## A contestação do laudo mora no rodapé do card, e só para o dono atual
x-inspection.card recebe `dispute-url` e desenha a ação: `<details>` "Não concordo com este laudo"
com motivo opcional, ou, já contestado, o x-ui.alert "Você contestou este laudo" com "Retirar
contestação". Quem passa a URL é x-vehicle.detail, só no portal do Proprietário e só quando
`InspectionDisputeService::canDispute` responde que sim. A **marca** "Contestado pelo proprietário"
continua em todos os portais — ela é informação de quem está comprando —, mas o texto da contestação
aparece só para quem o escreveu.

O bloco fica no slot `footer`, não no corpo: o card com `href` desenha um link esticado
(`after:inset-0`) por cima de tudo, e só os slots `action` e `footer` ganham `relative z-10`.
Formulário no corpo fica debaixo do link e o clique navega em vez de enviar. E o rótulo do campo vem
de `<x-ui.field label>`: `<x-ui.textarea>` **não tem** prop `label` — passá-la vira atributo HTML e
o campo fica sem rótulo visível (é o que acontece hoje no formulário de contestação de OS).

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
