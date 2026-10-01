<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensagens de autenticação
    |--------------------------------------------------------------------------
    |
    | Mensagens exibidas no login e nos limites de tentativas de acesso.
    | "too_many_attempts" é usada com trans_choice() pelos limitadores
    | auth e auth-web (AppServiceProvider), com o Retry-After em segundos.
    |
    */

    'failed' => 'E-mail ou senha incorretos.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de acesso. Tente de novo em :seconds segundos.',
    'too_many_attempts' => '{1} Muitas tentativas. Aguarde 1 segundo e tente de novo.|[2,*] Muitas tentativas. Aguarde :seconds segundos e tente de novo.',

];
