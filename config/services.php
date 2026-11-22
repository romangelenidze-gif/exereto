<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this file to locate the various service credentials.
    |
    */
    'telegram' => [
        'username' => env('TELEGRAM_SUPPORT_USERNAME', 'buyer_greece_bot'),
    ],

    'whatsapp' => [
        'phone' => env('WHATSAPP_SUPPORT_PHONE', '306900000000'),
    ],

    'messenger' => [
        'page' => env('FACEBOOK_SUPPORT_PAGE', 'buyer.greece'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'fallback_models' => env('GROQ_FALLBACK_MODELS', ''),
        'model_ka' => env('GROQ_MODEL_KA'),
        'model_translate' => env('GROQ_MODEL_TRANSLATE'),
    ],

    'bestprice' => [
        'max_queries' => env('SEARCH_MAX_QUERIES', 1),
        'cache_ttl' => env('BESTPRICE_CACHE_TTL', 10800),
        'cooldown' => env('BESTPRICE_COOLDOWN', 20),
    ],

    'currency_rates' => ['USD' => 0.92, 'RUB' => 0.010, 'GEL' => 0.34],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'cache_ttl' => env('GEMINI_CACHE_TTL', 10800),
        'vision_enabled' => filter_var(env('PHOTO_SEARCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'vision_api_key' => env('GEMINI_VISION_API_KEY') ?: env('GEMINI_API_KEY'),
        'vision_model' => env('GEMINI_VISION_MODEL') ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'vision_fallback_models' => env('GEMINI_VISION_FALLBACK_MODELS', 'gemini-3.1-flash-lite,gemini-2.5-flash-lite,gemini-2.5-flash'),
        'vision_daily_limit' => (int) env('GEMINI_VISION_DAILY_LIMIT', 250),
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