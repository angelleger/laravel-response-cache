<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default TTL
    |--------------------------------------------------------------------------
    | Time to live in seconds for cached responses
    */
    'ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | Which cache store to use. null uses the default store.
    | Must support tags for invalidation features.
    */
    'store' => env('RESPONSE_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Guest Only
    |--------------------------------------------------------------------------
    | Whether to only cache responses for guests (not authenticated users)
    */
    'guest_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Vary Headers
    |--------------------------------------------------------------------------
    | Headers that should vary the cache key
    */
    'vary_headers' => [
        'Accept',
        'Accept-Language',
        'X-Locale',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => 'resp_cache:',

    /*
    |--------------------------------------------------------------------------
    | ETag Support
    |--------------------------------------------------------------------------
    | Enable ETag generation and 304 Not Modified responses
    */
    'etag' => true,

    /*
    |--------------------------------------------------------------------------
    | Include IP Address
    |--------------------------------------------------------------------------
    | Include client IP in cache key (warning: can fragment cache significantly)
    */
    'include_ip' => false,

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | Enable debug logging for troubleshooting
    */
    'debug' => env('RESPONSE_CACHE_DEBUG', false),
];
