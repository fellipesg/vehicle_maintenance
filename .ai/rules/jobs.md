---
paths:
  - 'app/Jobs/**'
  - 'app/Console/Commands/PurgePendingMaintenanceAttachments.php'
---

# Jobs

## Cloud PDF worker must use database connection
PDF exports dispatch to QUEUE_CONNECTION=database (Neon jobs table). The managed-queue worker runs `queue:work cloud` and never sees those jobs. Production needs a background process on the App instance: `queue:work database --timeout=300 --tries=2` (process-a2bec05f). The Cloud CLI lives in the Composer sandbox cache, not PATH.

## Retenção de anexos pendentes
maintenance:purge-pending-attachments roda todo dia às 03:30 e apaga notas e fotos de OS sem dono sem aceite há mais de maintenance.pending_attachments_retention_days (90); a OS e os itens ficam e attachments_status vira declined. Anexo aceito nunca é tocado.
