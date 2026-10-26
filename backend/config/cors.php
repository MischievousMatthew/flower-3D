<?php

// Only BloomCraft's own frontends may call the API from a browser.
// More sites (e.g. a preview link) go in CORS_EXTRA_ORIGINS, comma-separated:
//   CORS_EXTRA_ORIGINS=https://bloomcraft-app-git-main-myteam.vercel.app
// Never a wildcard like *.vercel.app: anyone can create a site there.
$extra = array_filter(array_map('trim', explode(',', (string) env('CORS_EXTRA_ORIGINS', ''))));

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'storage/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter([
        'http://localhost:5173',
        'https://bloomcraft-app.vercel.app',
        rtrim((string) env('FRONTEND_URL', ''), '/'),
        ...$extra,
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
