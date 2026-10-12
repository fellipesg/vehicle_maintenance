<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-verify maintenances linked to a registered workshop
    |--------------------------------------------------------------------------
    |
    | When enabled, owner/garage revisions that select a workshop_id receive the
    | workshop seal (registered_by_type=workshop, verified_at, verification code)
    | as if the workshop had registered them. For local/staging QA only.
    |
    */

    'auto_verify_linked_workshop' => (bool) env('MAINTENANCE_AUTO_VERIFY_LINKED_WORKSHOP', false),

    /*
    |--------------------------------------------------------------------------
    | Convite ao cliente pela OS (carro sem proprietário)
    |--------------------------------------------------------------------------
    |
    | Máximo de e-mails de convite que uma oficina envia nas últimas 24 horas.
    | Cada OS aceita um único e-mail de convite.
    |
    */

    'invite_email_daily_limit' => (int) env('MAINTENANCE_INVITE_EMAIL_DAILY_LIMIT', 30),

    /*
    |--------------------------------------------------------------------------
    | Anexos pendentes (LGPD)
    |--------------------------------------------------------------------------
    |
    | Notas fiscais e fotos de uma OS sem proprietário que ele não aceitou em
    | tantos dias são apagadas pelo comando maintenance:purge-pending-attachments.
    |
    */

    'pending_attachments_retention_days' => (int) env('MAINTENANCE_PENDING_ATTACHMENTS_RETENTION_DAYS', 90),

];
