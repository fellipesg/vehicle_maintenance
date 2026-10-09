---
paths:
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php,app/Services/Maintenance/**,app/Support/Maintenance/MaintenanceRedactor.php'
---

# Models

## Procedência só via MaintenanceVerificationStamper
registered_by_type, verified_at, verified_workshop_id e verification_code não são fillable. Selo (workshop) só quando o ator é oficina e workshop_id da manutenção é o da oficina. Código RVL-XXXX-XX gerado com retry. Manutenção verificada não é editável/apagável por não-oficina.

## OS sem proprietário: tenant nulo, estado na própria manutenção
Uma OS que a oficina registra num veículo sem dono atual nasce com tenant_id nulo (VehicleTenantResolver não decide mais isso; OwnerlessMaintenanceService::tenantIdFor) e owner_status pending. As colunas owner_status (pending|linked|declined), attachments_status (none|pending|accepted|declined|revoked), hidden_from_public_at e owner_decided_* não são fillable: só OwnerlessMaintenanceService e MaintenanceOwnerDecisionService gravam. owner_status nulo = OS comum. Invoice e MaintenancePhoto criados voltam attachments_status a pending (nunca depois de accepted).

## Forma mínima é cópia em memória
MaintenanceRedactor::redact/redactAll devolve um clone sem descrição, valores, garantias, notas e fotos (pending/declined esconde detalhes; qualquer status diferente de accepted esconde anexos) e tira as ocultas. Use em toda superfície de histórico (VehicleTimelineBuilder, x-vehicle.detail, maintenances/_detail, MaintenanceResource, PublicVehicleSearchResource, PDF). Nunca salve o clone. Só a oficina autora (workshop_id) vê tudo; quem ocultou (owner_decided_by_user_id) ainda vê a oculta.
