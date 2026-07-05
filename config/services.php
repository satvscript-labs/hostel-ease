<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_url' => env('WHATSAPP_API_URL'),
        'token' => env('WHATSAPP_API_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
    ],

    'razorpay' => [
        'enabled' => env('RAZORPAY_ENABLED', false),
        // Accept either the short names or Razorpay's own KEY_ID/KEY_SECRET names.
        'key' => env('RAZORPAY_KEY', env('RAZORPAY_KEY_ID')),
        'secret' => env('RAZORPAY_SECRET', env('RAZORPAY_KEY_SECRET')),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'payu' => [
        'enabled' => env('PAYU_ENABLED', false),
        'merchant_key' => env('PAYU_MERCHANT_KEY'),
        'merchant_salt' => env('PAYU_MERCHANT_SALT'),
    ],

];
