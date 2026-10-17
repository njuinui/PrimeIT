<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | Used by AdminUserSeeder and `php artisan primeit:admin`. In production the
    | password MUST come from the environment — there is deliberately no default.
    |
    */
    'admin' => [
        'name'     => env('ADMIN_NAME', 'PrimeIT Admin'),
        'email'    => env('ADMIN_EMAIL', 'admin@primeit.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo data
    |--------------------------------------------------------------------------
    |
    | `php artisan db:seed` loads sample projects, people and expenses outside
    | production. Set SEED_DEMO=true to force it (e.g. a staging server).
    |
    */
    'seed_demo' => env('SEED_DEMO'),

];
