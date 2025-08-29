<?php

declare(strict_types=1);

return [
    'ttl' => 300,

    'guest_only' => true,

    'vary_headers' => [
        'Accept',
        'Accept-Language',
        'X-Locale',
    ],

    'prefix' => 'resp_cache:',

    'etag' => true,

    // Set to true only if you need it (behind proxies this may fragment cache unduly)
    'include_ip' => false,
];
