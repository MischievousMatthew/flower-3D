<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'storage/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'https://bloomcraft-app.vercel.app',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#' // Allow Vercel preview domains dynamically
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];