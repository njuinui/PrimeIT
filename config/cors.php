<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'auth/*', 'login', 'logout', 'register', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'https://prime-i-tfrontend.vercel.app',   // stable production URL
    ]),

    'allowed_origins_patterns' => [
        // all Vercel preview deploys for this project
        '#^https://prime-i-tfrontend-[a-z0-9-]+\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
