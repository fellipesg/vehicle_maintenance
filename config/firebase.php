<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials Path
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON file relative to storage/app/
    | Example: 'firebase-service-account.json'
    |
    */
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', 'firebase-service-account.json'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials (Laravel Cloud)
    |--------------------------------------------------------------------------
    |
    | On Laravel Cloud the filesystem is ephemeral — prefer env vars:
    | FIREBASE_CREDENTIALS_BASE64 (recommended) or FIREBASE_CREDENTIALS_JSON.
    |
    */
    'credentials_base64' => env('FIREBASE_CREDENTIALS_BASE64'),
    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
];
