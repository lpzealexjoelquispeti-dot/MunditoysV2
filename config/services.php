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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'delivery' => [
        'routing_url' => env('DELIVERY_ROUTING_URL', env('APP_ENV') === 'local' ? 'https://router.project-osrm.org' : null),
    ],

    // Geocodificación inversa (coordenadas → calle y zona). La política de Nominatim exige identificarse y no pasar de 1 consulta por segundo.
    'geocodificacion' => [
        'url' => env('GEOCODIFICACION_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('GEOCODIFICACION_USER_AGENT', 'MundiToys/1.0 (tienda de juguetes, La Paz)'),
    ],

];
