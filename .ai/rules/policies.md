---
paths:
  - 'app/Policies/**'
---

# Policies

## Workshop changes only the order it registered
MaintenancePolicy::update/delete usa `Maintenance::isAuthoredByWorkshopId`: com selo é o `verified_workshop_id`; **sem** selo é o `workshop_id` junto com `registered_by_type` workshop — a OS de um período em que o selo dependia de vínculo provado com o veículo (ver "O selo não espera o proprietário" em models.md) continua sendo da oficina, que precisa poder corrigir e excluir o que registrou. A record that an owner or dealer declared citing the workshop (workshop_id, no seal) stays with whoever declared it; the workshop can view it but not change it, in the API (Flutter) and on the web. Other accounts change only declared records of their own tenant; a sealed record is never changed by them. Web controllers call Gate view first and redirect with a friendly error before Gate update, so the declared-but-cited case does not become a bare 403.

## Only the current owner changes a declared record
Besides the tenant, MaintenancePolicy::update/delete require the account to be the vehicle's current owner (VehiclePolicy::addMaintenance, the user_vehicles pivot with is_current_owner in the account's tenant). A seller, or a dealer holding the car on consignment, keeps seeing what it declared but cannot edit or delete it (API and web). That denial carries the code MaintenancePolicy::DENIED_NOT_CURRENT_OWNER; the owner portal reads it with Gate::inspect to explain "O veículo não está mais na sua conta". The policies return Illuminate\Auth\Access\Response, so a policy that delegates calls ->allowed(). MaintenancePhotoPolicy and InvoicePolicy::update/delete delegate to MaintenancePolicy::update (changing a photo or an invoice changes the maintenance); InvoicePolicy::view still follows MaintenancePolicy::view.

## API link claims an unowned vehicle with plate and RENAVAM
VehiclePolicy::link allows the current owner, and any account when no other tenant is the current owner. POST /api/v1/vehicles/{id}/link then requires license_plate and renavam matching the vehicle (LinkVehicleRequest, VehicleOwnershipService::documentMatchesVehicle), throttled by vehicle-link. A vehicle another tenant currently owns stays 403 even with the right document. Ownership claimed this way is not verified; only a CRLV-e import verifies it. POST /api/v1/maintenances for any non-workshop account authorizes VehiclePolicy::addMaintenance, like the web stores; a consignment dealer or an admin gets 403 instead of moving the owner's odometer with a record nobody can undo.

## OS sem proprietário: oculta e anexos pendentes
MaintenancePolicy::view nega a OS oculta (hidden_from_public_at) para quem não é a oficina autora nem quem ocultou. InvoicePolicy::view nega a nota enquanto Maintenance::hidesAttachmentsFrom (OS sem dono sem aceite do proprietário), exceto para a oficina autora e para o dono atual com posse comprovada (prévia, ver models.md); o dono sem comprovação continua de fora. Só o dono atual (VehiclePolicy::update) decide (MaintenanceOwnerDecisionService::decide).

## Fotos: o recurso por conta vem antes de tudo
MaintenancePhotoPolicy::view/create/delete negam primeiro quando a conta não tem as fotos das manutenções (App\Support\Maintenance\MaintenancePhotoAccess, flag Pennant ligada pelo admin; ver features.md) e só depois seguem a MaintenancePolicy. O envio pela API passa por `create` (StoreMaintenancePhotoRequest). Foto negada nunca ganha URL assinada, e as rotas autorizadas (API e web) respondem 403.

## Ver um registro é ver o que a listagem daquele veículo mostra
A regra de visibilidade do histórico mora em `Maintenance::isVisibleInHistoryTo`, par em memória da consulta de `Vehicle::maintenancesVisibleTo` (scope `visibleInHistoryTo` + restrição de tenant de quem não passa em `VehiclePolicy::viewFullHistory`). `MaintenancePolicy::view` chama esse método, e `MaintenancePhotoPolicy::view` e `InvoicePolicy::view` seguem a `MaintenancePolicy::view` — mudar uma das duas formas pede mudar a outra. Nunca autorize registro individual por `VehiclePolicy::view`: o lojista entra nela desde que declara a consignação, e era por ali que ele enumerava ids em `/fotos-manutencao/{id}`, `/notas-fiscais/{invoice}` e nas rotas da API para abrir anexo de dono anterior que a listagem esconde dele. A oficina continua sendo o caso à parte, decidido na policy antes disso: ela vê a OS que registrou (`workshop_id`), em qualquer veículo e mesmo oculta, e nada além dela. O método usa `loadMissing('vehicle')`, não `$this->vehicle`: com `preventLazyLoading` fora de produção, a autorização quebraria em vez de responder. A busca pública não passa por policy e segue com a exceção de `.ai/rules/public-lookup.md` e `api.md`.
