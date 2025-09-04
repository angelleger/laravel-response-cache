<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Time To Live
    |--------------------------------------------------------------------------
    | Lifetime in seconds for cached responses when no explicit ttl is given.
    */
    'ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | Which cache store to use. Null uses the default application cache store.
    */
    'store' => env('RESPONSE_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'key_prefix' => 'resp_cache:',

    /*
    |--------------------------------------------------------------------------
    | Only cache responses for guests by default
    |--------------------------------------------------------------------------
    */
    'guest_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Headers and cookies that should be included in the cache key
    |--------------------------------------------------------------------------
    */
    'vary_on_headers' => [
        'Accept',
        'Accept-Language',
    ],

    'vary_on_cookies' => [],

    /*
    |--------------------------------------------------------------------------
    | Query string handling
    |--------------------------------------------------------------------------
    | include_query_params - when not empty only these parameters are used
    | ignore_query_params  - parameters to ignore (supports * suffix)
    */
    'include_query_params' => [],
    'ignore_query_params' => ['_', 'utm_*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed response status codes for caching
    |--------------------------------------------------------------------------
    */
    'status_whitelist' => [200],

    /*
    |--------------------------------------------------------------------------
    | Maximum payload size in kilobytes. Null means unlimited.
    |--------------------------------------------------------------------------
    */
    'max_payload_kb' => null,

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => env('RESPONSE_CACHE_DEBUG', false),
];
