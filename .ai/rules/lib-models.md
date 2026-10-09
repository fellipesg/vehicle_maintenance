---
paths:
  - frontend/lib/models/maintenance_item.dart
---

# Lib Models

> Flutter app (`vehicle_maintenance_frontend`), not this repository. Paths are relative to a sibling `frontend/` checkout.

## Expired warranty chip wording
Expired item warranties use expiredWarrantyChipLabel(): "Garantia encerrada · até dd/mm/aaaa". Never show a bare "Encerrada · ..." prefix.

## WorkshopRecord e campos de OS sem proprietário
O modelo espelha WorkshopRecord da API (id, vehicle{id,brand,model,year,chassis_masked}, workshop{id,name}, maintenance_date, kilometers, service_category, maintenance_type, items[{name,quantity}], verification_code, attachments{invoices,photos}, owner_status, attachments_status, hidden_from_public, can_accept_attachments). MaintenanceItem lê is_ownerless_record, owner_status e attachments_status (nulos em OS comum) e hidden_from_public.
