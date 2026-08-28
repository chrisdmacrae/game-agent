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

    /*
    | Grinding Gear Games' Path of Exile OAuth 2.1 API, used to link a user's
    | GGG account and read their PoE2 characters. GGG is not accepting new
    | developer applications right now, so the whole feature stays dark unless
    | a client id and secret are configured (see GggOAuth::enabled()).
    */
    'poe' => [
        'client_id' => env('POE_OAUTH_CLIENT_ID'),
        'client_secret' => env('POE_OAUTH_CLIENT_SECRET'),

        // GGG requires the contact address inside the User-Agent header.
        'contact' => env('POE_OAUTH_CONTACT'),

        'oauth_base_url' => env('POE_OAUTH_BASE_URL', 'https://www.pathofexile.com'),
        'api_base_url' => env('POE_API_BASE_URL', 'https://api.pathofexile.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
