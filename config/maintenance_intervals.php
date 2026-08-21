<?php

return [

    /*
    | Default preventive maintenance interval (km). Used to estimate the next
    | revision on the timeline and for km-based push reminders.
    */
    'default_preventive_kilometers' => (int) env('MAINTENANCE_INTERVAL_KM', 10_000),

    /*
    | Notify when the vehicle is within this many km of the next due revision.
    */
    'notify_before_kilometers' => (int) env('MAINTENANCE_NOTIFY_BEFORE_KM', 2_000),

    'labels' => [
        'preventive' => 'Revisão programada',
        'upcoming' => 'Próxima revisão estimada',
    ],

];
