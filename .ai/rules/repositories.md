---
paths:
  - frontend/lib/repositories/vehicle_repository.dart
---

# Repositories

> Flutter app (`vehicle_maintenance_frontend`), not this repository. Paths are relative to a sibling `frontend/` checkout. The API side is `GET /api/v1/my-vehicles` with the `etag.vehicle_list` middleware.

## App: snapshot + ETag em /my-vehicles
A lista de veículos no app deve usar `VehicleRepository` (snapshot em SharedPreferences + revalidação com `If-None-Match`). Não refazer fetch completo com spinner de tela inteira quando já há cache.
