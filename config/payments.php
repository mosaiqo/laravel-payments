<?php

return [
    'path' => env('PAYMENTS_PATH', 'payments'),

    'provider' => env('PAYMENTS_PROVIDER'),

    'webhook_path' => env('PAYMENTS_WEBHOOK_PATH', 'webhooks'),

    'providers' => [
        'lemon-squeezy' => [
            'redirect_url' => null,
            'api_key' => env('LEMON_SQUEEZY_API_KEY'),
            'store' => env('LEMON_SQUEEZY_STORE'),
            'currency_locale' => env('LEMON_SQUEEZY_CURRENCY_LOCALE', 'en'),
            'signing_secret' => env('PAYMENTS_SIGNING_SECRET_LEMON_SQUEEZY'),
        ],
        'stripe' => [
            'api_key' => env('STRIPE_API_KEY'),
            'currency_locale' => env('STRIPE_CURRENCY_LOCALE', 'en'),
            'signing_secret' => env('PAYMENTS_SIGNING_SECRET_STRIPE'),
        ],
    ],
];
