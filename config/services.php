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

    // terminal.bazardev.com launch handoff — see app/Http/Controllers/TerminalLaunchController.php
    'terminal' => [
        'url' => env('TERMINAL_URL', 'https://terminal.bazardev.com'),
        'shared_secret' => env('TERMINAL_SHARED_SECRET'),
    ],

    // pay.bazardev.com — see app/Services/Payments/PayBazarDevGateway.php.
    // Not usable yet: learn.bazardev.com has no Store registered there,
    // so api_key/api_secret are empty until that happens (plans/paid-access-and-entitlements.md §8).
    'pay_bazardev' => [
        'base_url' => env('PAY_BAZARDEV_URL', 'https://pay.bazardev.com'),
        'api_key' => env('PAY_BAZARDEV_API_KEY'),
        'api_secret' => env('PAY_BAZARDEV_API_SECRET'),
        // Webhook signatures are always HMAC'd with the LIVE secret by pay.bazardev,
        // even for a sandbox transaction - usually the same value as api_secret
        // above once this account is live, but kept separate on purpose.
        'live_api_secret' => env('PAY_BAZARDEV_LIVE_API_SECRET'),
    ],

];
