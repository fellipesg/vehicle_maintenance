<?php

use App\Http\Middleware\EnsureApiDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

$apiDescription = <<<'MD'
## Authentication

1. Call `POST /login` (or `POST /register`) with email and password.
2. Copy the `token` from the response `data` object.
3. Click **Authorize** in this UI and enter: `Bearer {token}` (include the word `Bearer` and a space before the token).

All protected routes require the header: `Authorization: Bearer {token}`.

Mobile tokens are issued via Sanctum (30-day expiry, named `mobile`). Token abilities exist in the API (`profile:read`, `vehicles:write`, etc.) but are **not enforced** on routes — all mobile tokens receive the full ability set.

## Two-factor authentication (2FA)

When 2FA is enabled on an account, `POST /login` and `POST /register` return a pending challenge instead of a token (`two_factor_required: true` and a `challenge_token`). Complete sign-in with `POST /two-factor/challenge` using the `challenge_token` and a TOTP `code` or `recovery_code`.

## Demo account (QA)

Use the shared demo credentials provided by the team (email: `fgoncalves2008@gmail.com`, password: `password123`) to explore authenticated endpoints in production.

## Rate limiting

Auth endpoints (`/login`, `/register`, OAuth callback) are limited to **5 requests per minute** per IP/email (`throttle:auth`).

## Response envelope

All JSON responses follow this shape:

```json
{
  "success": true,
  "data": {},
  "message": "Optional human-readable message",
  "errors": {}
}
```

- `success` — boolean indicating outcome
- `data` — payload on success (optional)
- `message` — summary message (optional)
- `errors` — validation or field errors (optional, usually on 422)
MD;

return [
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0'),
        'description' => $apiDescription,
    ],

    'ui' => [
        'title' => 'Vehicle Maintenance API',
    ],

    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => [
        'API' => rtrim((string) config('app.url'), '/').'/api/v1',
    ],

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        EnsureApiDocsAccess::class,
    ],

    'extensions' => [],

    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth:sanctum'],
            'scheme' => SecurityScheme::http('bearer', 'JWT'),
        ],
    ],
];
