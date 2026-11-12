<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | DigitHub License Manager
    |--------------------------------------------------------------------------
    |
    | Read-only API that tells whether a portal may use the software and with
    | which plan (App\Services\LicenseService). The key and the software code
    | live in the environment only. `enforcement` is `off` (no automatic calls,
    | nothing changes), `observe` (checks and logs what it would block) or `on`.
    |
    */

    'license' => [
        'url' => env('LICENSE_API_URL'),
        'key' => env('LICENSE_API_KEY'),
        'software' => env('LICENSE_SOFTWARE_CODE'),
        'enforcement' => env('LICENSE_ENFORCEMENT', 'off'),
        'timeout' => (int) env('LICENSE_TIMEOUT_SECONDS', 5),
        'cache_ttl' => (int) env('LICENSE_CACHE_TTL_SECONDS', 300),
        'not_found_ttl' => (int) env('LICENSE_NOT_FOUND_TTL_SECONDS', 60),
        'offline_grace_hours' => (int) env('LICENSE_OFFLINE_GRACE_HOURS', 72),
        'support_email' => env('LICENSE_SUPPORT_EMAIL'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
