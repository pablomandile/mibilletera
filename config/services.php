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

    // Cotización del dólar por tipo y fecha (API de bambudev). La clave es de
    // MiBilletera y solo la usa el servidor: nunca viaja al navegador.
    'bambudev' => [
        'url' => env('BAMBUDEV_API_URL', 'https://bambudev.com.ar/api/v1'),
        'key' => env('BAMBUDEV_API_KEY'),
    ],

    // Inflación mensual del INDEC para Herramientas → Rendimiento de tu cuenta
    // (API pública de CalculAR, sin clave).
    'calcular' => [
        'url' => env('CALCULAR_API_URL', 'https://calcular.bambudev.com.ar'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

];
