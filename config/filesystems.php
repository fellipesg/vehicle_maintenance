<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', env('AWS_REGION')),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT', env('AWS_ENDPOINT_URL_S3')),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
            'requestChecksumCalculation' => 'when_required',
            'responseChecksumValidation' => 'when_required',
            'http' => [
                'connect_timeout' => 20,
                'timeout' => 120,
                // Prefer IPv4 when dual-stack DNS is present (avoids long hangs).
                'force_ip_resolve' => 'v4',
            ],
            'retries' => [
                'mode' => 'standard',
                'max_attempts' => 3,
            ],
        ],

        // Previous bucket, kept only so `storage:migrate-legacy` can copy files
        // out of it. Unset in environments that already use the current bucket.
        'legacy_s3' => [
            'driver' => 's3',
            'key' => env('LEGACY_AWS_ACCESS_KEY_ID'),
            'secret' => env('LEGACY_AWS_SECRET_ACCESS_KEY'),
            'region' => env('LEGACY_AWS_DEFAULT_REGION', 'us-east-2'),
            'bucket' => env('LEGACY_AWS_BUCKET'),
            'endpoint' => env('LEGACY_AWS_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
            'requestChecksumCalculation' => 'when_required',
            'responseChecksumValidation' => 'when_required',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
