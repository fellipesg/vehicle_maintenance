<?php

/**
 * @return list<string>
 */
if (! function_exists('cors_allowed_origins')) {
    function cors_allowed_origins(): array
    {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        if (env('APP_ENV') === 'production') {
            $appUrl = rtrim((string) env('APP_URL', ''), '/');

            return $appUrl !== '' ? [$appUrl] : [];
        }

        return ['*'];
    }
}

/**
 * @return list<string>
 */
if (! function_exists('cors_allowed_origin_patterns')) {
    function cors_allowed_origin_patterns(): array
    {
        $raw = env('CORS_ALLOWED_ORIGIN_PATTERNS');

        // No wildcard default: credentialed CORS must only be granted to origins
        // explicitly listed in CORS_ALLOWED_ORIGINS / CORS_ALLOWED_ORIGIN_PATTERNS.
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}

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
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => cors_allowed_origins(),

    'allowed_origins_patterns' => cors_allowed_origin_patterns(),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => ! in_array('*', cors_allowed_origins(), true),
];
