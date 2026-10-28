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

## ProcessMaintenancePhoto e o backfill de variantes
`ProcessMaintenancePhoto` (fila `database`, afterCommit, 3 tentativas) gera thumb e display via
`MaintenancePhotoVariantGenerator`; imagem indecodável falha na hora (sem retry) e arquivo ausente tenta
de novo. Despache pelo `MaintenancePhotoService::store`, não pelo evento `created` do model: factories
criam foto sem arquivo. `php artisan photos:generate-variants [--sync] [--dry-run] [--limit=N] [--chunk=100]` enfileira (ou
processa) as fotos com `processed_at` nulo, é seguro rodar de novo e nunca apaga arquivo (o original
antigo fica em `legacy_path`); `--dry-run` só conta; o worker `queue:work database` de
cima precisa estar de pé. O backfill de produção é manual e não faz parte do deploy.
