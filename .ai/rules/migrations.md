---
paths:
  - 'app/Models/Vehicle.php,app/Support/VehiclePlateSearch.php,database/migrations/*vehicle_plates*'
---

# Migrations

## Chassi como identidade e histórico de placas
vehicles.chassis é único (nullable) e normalizado (uppercase, só alfanuméricos). Placa atual em vehicles.license_plate; trocas via VehiclePlateHistoryService gravam vehicle_plates. Busca pública/API: VehiclePlateSearch::findByIdentifier (chassi → RENAVAM → placa atual → placa histórica). Criação exige chassi (regra Chassis); legado null só até primeiro update.

## Placa e RENAVAM são opcionais
vehicles.license_plate e vehicles.renavam são nullable (únicos, vários NULL convivem). Veículo criado pela oficina (WorkshopVehicleRegistrar) guarda só chassi, marca, modelo e ano. Nunca grave string vazia: use NULL. Toda tela que mostra placa trata null: "Placa não informada" nas telas privadas e nada nas públicas. maintenances ganhou owner_status, attachments_status, hidden_from_public_at, owner_decided_by_user_id e owner_decided_at; maintenance_invites guarda token, hash do e-mail e datas (nunca e-mail nem telefone).
