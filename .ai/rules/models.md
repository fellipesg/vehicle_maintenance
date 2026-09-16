---
paths:
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php'
---

# Models

## Procedência só via MaintenanceVerificationStamper
registered_by_type, verified_at, verified_workshop_id e verification_code não são fillable. Selo (workshop) só quando o ator é oficina e workshop_id da manutenção é o da oficina. Código RVL-XXXX-XX gerado com retry. Manutenção verificada não é editável/apagável por não-oficina.
