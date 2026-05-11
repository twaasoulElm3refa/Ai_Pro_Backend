<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'qdrant' => [
        'url' => env('QDRANT_URL'),
        'vector_size' => env('QDRANT_VECTOR_SIZE', 1536),
    ],
    'aiarabic' => [
        'url' => env('AIARABIC_WRITER_URL', 'https://api.aiarabic.com/tasks/writer'),
        'key' => env('AIARABIC_INTERNAL_API_KEY', 'L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy'),
        'inject_qdrant_context' => env('AIARABIC_INJECT_QDRANT_CONTEXT', false),
        'conversation_token_limit' => env('AI_CONVERSATION_TOKEN_LIMIT', 7000),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

];
