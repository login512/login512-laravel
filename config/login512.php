<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Login512 API Keys
    |--------------------------------------------------------------------------
    |
    | Obtain your publishable and secret keys from the Login512 Developer Portal.
    |
    */

    'publishable_key' => env('LOGIN512_PUBLISHABLE_KEY', ''),
    'secret_key' => env('LOGIN512_SECRET_KEY', ''),
    'endpoint' => env('LOGIN512_ENDPOINT', 'https://api.login512.com'),
];

