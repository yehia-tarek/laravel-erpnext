<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ERPNext Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your ERPNext / Frappe instance.
    | Example: https://mycompany.erpnext.com
    |
    */
    'base_url' => env('ERPNEXT_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Choose your authentication method:
    |   - 'token'    : API Key + API Secret (recommended)
    |   - 'password' : Username + Password (uses cookies/sessions)
    |   - 'oauth'    : Bearer token (OAuth 2.0)
    |
    */
    'auth' => [
        'method' => env('ERPNEXT_AUTH_METHOD', 'token'),

        // Token-based (method = 'token')
        'api_key'    => env('ERPNEXT_API_KEY', ''),
        'api_secret' => env('ERPNEXT_API_SECRET', ''),

        // Password-based (method = 'password')
        'username' => env('ERPNEXT_USERNAME', ''),
        'password' => env('ERPNEXT_PASSWORD', ''),

        // OAuth 2.0 (method = 'oauth')
        'access_token' => env('ERPNEXT_ACCESS_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | Options passed directly to the underlying Guzzle HTTP client.
    |
    */
    'http' => [
        'timeout'         => env('ERPNEXT_TIMEOUT', 30),
        'connect_timeout' => env('ERPNEXT_CONNECT_TIMEOUT', 10),
        'verify'          => env('ERPNEXT_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Query Options
    |--------------------------------------------------------------------------
    |
    | Default values used when listing documents.
    |
    */
    'defaults' => [
        'limit'    => 20,
        'as_dict'  => true,
    ],
];
