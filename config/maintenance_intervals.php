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

    /*
    | Average kilometers driven per year. Used to estimate current mileage for
    | workshop follow-up messages only (not owner km reminders).
    */
    'average_kilometers_per_year' => (int) env('MAINTENANCE_AVERAGE_KM_PER_YEAR', 13_000),

    'labels' => [
        'preventive' => 'Revisão programada',
        'upcoming' => 'Próxima revisão estimada',
    ],

];
