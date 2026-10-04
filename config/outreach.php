<?php

return [

    /*
    | Prospecção por e-mail de oficinas (ver .ai/rules/outreach.md). Desligada por padrão: nada é
    | enviado até OUTREACH_ENABLED=true. O envio nunca usa o mailer padrão (Resend).
    */
    'enabled' => (bool) env('OUTREACH_ENABLED', false),

    'mailer' => env('OUTREACH_MAILER', 'log'),

    'from' => [
        'address' => env('OUTREACH_FROM_ADDRESS'),
        'name' => env('OUTREACH_FROM_NAME', 'Felipe, do RevisaLog'),
    ],

    // Vazio: as respostas voltam para from.address.
    'reply_to' => env('OUTREACH_REPLY_TO'),

    'sender_signature_name' => env('OUTREACH_SENDER_NAME', 'Felipe Gonçalves'),

    'daily_limit' => (int) env('OUTREACH_DAILY_LIMIT', 10),

    'follow_up_after_business_days' => 5,

    'window' => [
        'start' => '09:00',
        'end' => '17:00',
        'timezone' => 'America/Sao_Paulo',
    ],

    // CNAE principal: 4520-0/01 (mecânica), 4520-0/02 (funilaria e pintura), 4520-0/03 (elétrica),
    // 4520-0/04 (alinhamento e balanceamento) e 4520-0/06 (borracharia). Lava-jato (4520-0/05) fica de fora.
    'default_cnaes' => ['4520001', '4520002', '4520003', '4520004', '4520006'],

    // E-mail usado por tantos estabelecimentos do município costuma ser de contador.
    'shared_email_threshold' => 3,

];
