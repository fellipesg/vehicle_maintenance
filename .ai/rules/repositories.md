---
paths:
  - frontend/lib/repositories/vehicle_repository.dart
---

# Repositories

## App: snapshot + ETag em /my-vehicles
A lista de veículos no app deve usar `VehicleRepository` (snapshot em SharedPreferences + revalidação com `If-None-Match`). Não refazer fetch completo com spinner de tela inteira quando já há cache.
