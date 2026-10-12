---
paths:
  - 'app/Policies/**'
---

# Policies

## Workshop changes only the order it sealed
MaintenancePolicy::update/delete: a workshop account may change or delete only a maintenance with verified_at set and verified_workshop_id equal to its workshop. A record that an owner or dealer declared citing the workshop (workshop_id, no seal) stays with whoever declared it; the workshop can view it but not change it, in the API (Flutter) and on the web. Other accounts change only declared records of their own tenant; a sealed record is never changed by them. Web controllers call Gate view first and redirect with a friendly error before Gate update, so the declared-but-cited case does not become a bare 403.

## Only the current owner changes a declared record
Besides the tenant, MaintenancePolicy::update/delete require the account to be the vehicle's current owner (VehiclePolicy::addMaintenance, the user_vehicles pivot with is_current_owner in the account's tenant). A seller, or a dealer holding the car on consignment, keeps seeing what it declared but cannot edit or delete it (API and web). That denial carries the code MaintenancePolicy::DENIED_NOT_CURRENT_OWNER; the owner portal reads it with Gate::inspect to explain "O veículo não está mais na sua conta". The policies return Illuminate\Auth\Access\Response, so a policy that delegates calls ->allowed(). MaintenancePhotoPolicy and InvoicePolicy::update/delete delegate to MaintenancePolicy::update (changing a photo or an invoice changes the maintenance); InvoicePolicy::view still follows MaintenancePolicy::view.

## API link claims an unowned vehicle with plate and RENAVAM
VehiclePolicy::link allows the current owner, and any account when no other tenant is the current owner. POST /api/v1/vehicles/{id}/link then requires license_plate and renavam matching the vehicle (LinkVehicleRequest, VehicleOwnershipService::documentMatchesVehicle), throttled by vehicle-link. A vehicle another tenant currently owns stays 403 even with the right document. Ownership claimed this way is not verified; only a CRLV-e import verifies it. POST /api/v1/maintenances for any non-workshop account authorizes VehiclePolicy::addMaintenance, like the web stores; a consignment dealer or an admin gets 403 instead of moving the owner's odometer with a record nobody can undo.

## OS sem proprietário: oculta e anexos pendentes
MaintenancePolicy::view nega a OS oculta (hidden_from_public_at) para quem não é a oficina autora nem quem ocultou. InvoicePolicy::view nega a nota enquanto Maintenance::hidesAttachmentsFrom (OS sem dono sem aceite do proprietário), mesmo para o dono atual do veículo. Só o dono atual (VehiclePolicy::update) decide (MaintenanceOwnerDecisionService::decide).
