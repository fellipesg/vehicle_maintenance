<?php

return [

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ],

    /*
     * Content-Security-Policy das páginas HTML. As diretivas ficam em
     * App\Support\Security\ContentSecurityPolicy — arquivo de config não é lugar de código
     * (.ai/rules/config.md) —; aqui ficam só os interruptores.
     */
    'csp' => [

        // Desligar é saída de emergência: página quebrada em produção sem tempo de investigar.
        'enabled' => env('CSP_ENABLED', true),

        // false → Content-Security-Policy-Report-Only: o navegador relata e não bloqueia nada.
        // true  → Content-Security-Policy: o navegador bloqueia.
        // Virar a chave é esta linha (CSP_ENFORCE=true no ambiente), não uma refatoração.
        'enforce' => env('CSP_ENFORCE', false),

        // Onde o navegador entrega as violações. Vazio = rota local `csp-report`, que grava no log.
        // Para o Sentry, use o endpoint de segurança do projeto (ele agrupa e aplica cota):
        // https://oXXXXXX.ingest.sentry.io/api/<project_id>/security/?sentry_key=<public key>
        'report_uri' => env('CSP_REPORT_URI'),

        // Canal de log da rota local; vazio usa o canal padrão.
        'report_log_channel' => env('CSP_REPORT_LOG_CHANNEL'),

        // Hosts extras por diretiva, para liberar uma origem nova sem mexer em código.
        // Exemplo: 'img-src' => ['https://exemplo.com'].
        'extra_hosts' => [],

    ],

];
