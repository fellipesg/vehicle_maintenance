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

];
