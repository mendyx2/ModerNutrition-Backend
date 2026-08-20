<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Local development environments
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',

        // Production subdomains (DRC Top-Level Domain)
        'https://modernutrition.cd',
        'https://www.modernutrition.cd',
        'https://app.modernutrition.cd',
        'https://admin.modernutrition.cd',
    ],

    'allowed_origins_patterns' => [
        // Production custom domains
        '#^https?://([a-zA-Z0-9-]+\.)?modernutrition\.cd(:\d+)?$#',

        // Deployed frontend hosting platforms
        '#^https://[a-zA-Z0-9-]+\.netlify\.app$#',
        '#^https://[a-zA-Z0-9-]+\.vercel\.app$#',
        '#^https://[a-zA-Z0-9-]+\.up\.railway\.app$#',
        '#^https://[a-zA-Z0-9-]+\.onrender\.com$#',

        // Local development
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-Total-Count', 'X-Plan-Version'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
