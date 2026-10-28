<?php

use App\Support\CorsOrigins;

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | As origens vêm de App\Support\CorsOrigins. Nada de lógica declarada aqui dentro:
    | sob `config:cache` este arquivo não é carregado (ver .ai/rules/config.md).
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => CorsOrigins::allowed(),

    'allowed_origins_patterns' => CorsOrigins::patterns(),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => CorsOrigins::supportsCredentials(),
];
