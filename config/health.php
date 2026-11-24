<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fila parada (/up)
    |--------------------------------------------------------------------------
    |
    | /up responde 500 quando existe job vencido há mais minutos que isto e que
    | nenhum worker reservou. O valor tem de ficar acima do retry_after da
    | conexão database (DB_QUEUE_RETRY_AFTER, 330 s) e do atraso aleatório de
    | até 600 s que outreach:send usa, senão um job legitimamente em espera
    | derruba o endpoint. Só vale para a conexão de fila com driver database.
    |
    */

    'queue_backlog_minutes' => (int) env('HEALTH_QUEUE_BACKLOG_MINUTES', 15),

];
