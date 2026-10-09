---
paths:
  - frontend/lib/repositories/vehicle_repository.dart
---

# Repositories

> Flutter app (`vehicle_maintenance_frontend`), not this repository. Paths are relative to a sibling `frontend/` checkout. The API side is `GET /api/v1/my-vehicles` with the `etag.vehicle_list` middleware.

## App: snapshot + ETag em /my-vehicles
A lista de veículos no app deve usar `VehicleRepository` (snapshot em SharedPreferences + revalidação com `If-None-Match`). Não refazer fetch completo com spinner de tela inteira quando já há cache.

## Repositório de registros de oficinas
Chamadas: GET /me/workshop-records, POST /maintenances/{id}/owner-decision, GET /workshop/vehicles/lookup, POST /workshop/vehicles, POST /maintenances/{id}/invites/email|whatsapp. /me traz pending_workshop_records_count. 422 e 409 chegam com message em pt-BR para mostrar na tela.
