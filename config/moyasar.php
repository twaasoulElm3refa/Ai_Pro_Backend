<?php

return [
    'mode' => env('MOYASAR_MODE', 'test'),

    'api_url' => env('MOYASAR_API_URL', 'https://api.moyasar.com/v1'),

    'test' => [
        'publishable_key' => env('MOYASAR_TEST_PUBLISHABLE_KEY', 'pk_test_fmdBUF1qjjwowRY8wpiupGCBquBmNZmy7STFtsgV'),
        'secret_key' => env('MOYASAR_TEST_SECRET_KEY', 'sk_test_6Wo73pvAp5Umxgf5qHk55eh9CMexsGM4DtZFzJbH'),
    ],

    'live' => [
        'publishable_key' => env('MOYASAR_LIVE_PUBLISHABLE_KEY', ''),
        'secret_key' => env('MOYASAR_LIVE_SECRET_KEY', ''),
    ],

    'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET', '93ddfedd5212ab174cc234ad062f1b0a6774005f4bc454275e32521b99bb227e'),
    'currency' => strtoupper(env('MOYASAR_CURRENCY', 'SAR')),
    'merchant_id' => env('MOYASAR_MERCHANT_ID', ''),
    'merchant_name' => env('MOYASAR_MERCHANT_NAME', env('APP_NAME', 'Laravel')),

    'invoice_expiry_minutes' => (int) env('MOYASAR_INVOICE_EXPIRY_MINUTES', 30),
    'reconciliation_age_minutes' => (int) env('MOYASAR_RECONCILIATION_AGE_MINUTES', 10),
    'reconciliation_max_age_hours' => (int) env('MOYASAR_RECONCILIATION_MAX_AGE_HOURS', 72),
    'points_per_sar' => (int) env('MOYASAR_POINTS_PER_SAR', 1_000_000),

    'timeout' => (int) env('MOYASAR_TIMEOUT', 20),
    'connect_timeout' => (int) env('MOYASAR_CONNECT_TIMEOUT', 5),
    'get_retries' => (int) env('MOYASAR_GET_RETRIES', 2),
];
