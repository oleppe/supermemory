<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cognee Server Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the Cognee server that this application communicates with.
    |
    */

    'base_url' => env('COGNEE_BASE_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Cognee API Prefix
    |--------------------------------------------------------------------------
    |
    | The API version prefix used by the Cognee server.
    |
    */

    'api_prefix' => env('COGNEE_API_PREFIX', '/api/v1'),

];
