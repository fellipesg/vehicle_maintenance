---
paths:
  - 'app/Models/Vehicle.php,app/Support/VehiclePlateSearch.php,database/migrations/*vehicle_plates*'
---

# Migrations

## Chassi como identidade e histórico de placas
vehicles.chassis é único (nullable) e normalizado (uppercase, só alfanuméricos). Placa atual em vehicles.license_plate; trocas via VehiclePlateHistoryService gravam vehicle_plates. Busca pública/API: VehiclePlateSearch::findByIdentifier (chassi → RENAVAM → placa atual → placa histórica). Criação exige chassi (regra Chassis); legado null só até primeiro update.
