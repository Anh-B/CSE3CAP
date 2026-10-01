<?php

// This file didn't exist before Sprint 5 - there was no CORS config at
// all, so a deployed frontend on its own domain would've had every
// request silently blocked by the browser.

return [

    /*
    |--------------------------------------------------------------------------
    | Which URLs this config applies to
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Which frontend URLs are allowed to call this API
    |--------------------------------------------------------------------------
    | FRONTEND_URL should be set in .env to the deployed frontend's real
    | URL (e.g. https://reflection-diary.example.com). The localhost ones
    | are for running the frontend locally against this API during dev.
    */
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Allow cookies/auth headers cross-origin
    |--------------------------------------------------------------------------
    | Needed for Sanctum. Note this can't be combined with a '*' wildcard
    | in allowed_origins above - browsers reject that combination, which
    | is why real origins are listed explicitly instead.
    */
    'supports_credentials' => true,

];
