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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
        'collection_root' => env('FIREBASE_INGESTIONS_COLLECTION', 'user_ingestions'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'temperature' => env('GEMINI_TEMPERATURE', 0.2),
        'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 1024),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'ephemeral_key_api_version' => env('STRIPE_EPHEMERAL_KEY_API_VERSION', '2024-11-20.acacia'),
        'billing_portal_return_url' => env('STRIPE_BILLING_PORTAL_RETURN_URL'),
    ],

    'landing' => [
        'recipient' => env('LANDING_DEMO_RECIPIENT', env('MAIL_FROM_ADDRESS')),
        'recipient_name' => env('LANDING_DEMO_RECIPIENT_NAME', env('MAIL_FROM_NAME', 'MemoDoc Sales')),
    ],

    'contact' => [
        'recipient' => env('CONTACT_FORM_RECIPIENT', env('LANDING_DEMO_RECIPIENT', env('MAIL_FROM_ADDRESS'))),
        'recipient_name' => env('CONTACT_FORM_RECIPIENT_NAME', env('MAIL_FROM_NAME', 'MemoDoc Support')),
    ],

];
