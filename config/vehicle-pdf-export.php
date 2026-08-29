<?php

return [

    'storage_path_prefix' => 'exports/vehicle-pdfs',

    'file_ttl_hours' => (int) env('VEHICLE_PDF_EXPORT_TTL_HOURS', 24),

    'download_url_expiry_minutes' => (int) env('VEHICLE_PDF_EXPORT_URL_EXPIRY_MINUTES', 60),

    'poll_interval_seconds' => 2,

];
