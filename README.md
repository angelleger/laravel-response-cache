# Laravel Response Cache

Key-based full response caching for Laravel 10+ applications. The package stores complete GET/HEAD responses in the configured cache store using deterministic keys. It offers a small helper API, middleware, and artisan commands for managing cached responses without relying on cache tags.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x

## Installation

```bash
composer require angelleger/laravel-response-cache
php artisan vendor:publish --tag=response-cache-config
```

## Configuration

`config/response_cache.php`

```php
return [
    'ttl' => 300, // default lifetime in seconds
    'store' => env('RESPONSE_CACHE_STORE'), // cache store to use
    'key_prefix' => 'resp_cache:', // prefix for all keys and indexes
    'guest_only' => true, // only cache guests unless auth=true is passed to middleware
    'vary_on_headers' => ['Accept', 'Accept-Language'], // headers included in the key
    'vary_on_cookies' => [], // cookies included in the key
    'include_query_params' => [], // when set, only these query parameters are used
    'ignore_query_params' => ['_', 'utm_*'], // query parameters to ignore (supports * suffix)
    'status_whitelist' => [200], // only cache these response codes
    'max_payload_kb' => null, // skip responses larger than this (in kilobytes)
    'etag' => true, // automatically add ETag header to cached responses
    'debug' => env('RESPONSE_CACHE_DEBUG', false), // enable debug helpers
];
```

### Configuration Notes

- **ttl**: default time-to-live for cached responses when no explicit value is supplied.
- **store**: cache store to use; `null` uses the application's default store.
- **key_prefix**: string prepended to every cache key and route index entry.
- **guest_only**: when `true`, authenticated users are skipped unless the middleware parameter `auth=true` is provided.
- **vary_on_headers / vary_on_cookies**: header and cookie names that should contribute to the cache key.
- **include_query_params**: if non-empty, only these query parameters are considered when building the key.
- **ignore_query_params**: query parameters to discard; supports a trailing `*` wildcard.
- **status_whitelist**: response status codes eligible for caching.
- **max_payload_kb**: maximum size of the response body; larger responses are bypassed.
- **etag**: toggle automatic generation of an `ETag` header when caching.
- **debug**: when enabled, exposes additional debug headers.

## Cache Key Strategy

Keys follow the pattern:

```
{method}:{path-or-route}:{normalized-query}:{locale}:{guard-or-guest}
```

The query segment is created by sorting parameters, removing ignored keys, and injecting `vary_on_headers`/`vary_on_cookies` values as pseudo parameters (`h_header` / `c_cookie`). The active authentication guard name (or `guest`) and the request locale are appended to avoid collisions.

## Middleware

Apply the `resp.cache` middleware to any GET/HEAD route that should be cached.

```php
Route::get('/posts', fn () => Post::all())
    ->middleware('resp.cache:ttl=120');

// Allow authenticated users to be cached
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware('auth', 'resp.cache:ttl=60,auth=true');
```

### Middleware Parameters

- `ttl=<seconds>` – override the default TTL for this route.
- `auth=true` – cache authenticated responses even if `guest_only` is enabled.

Responses are cached only when:

- The HTTP method is GET or HEAD.
- The response status code appears in `status_whitelist`.
- The response size does not exceed `max_payload_kb` (when set).
- The request does not contain `Cache-Control: no-store`.

Cached responses include an `X-Cache: HIT` header for debugging. You can disable caching on a per-request basis by sending `Cache-Control: no-store` from the client or controller.

## Helper API

```php
use AngelLeger\ResponseCache\Facades\ResponseCache;

$key = ResponseCache::makeKey(request());
$response = ResponseCache::rememberResponse(request(), fn () => response('ok'), 60);
ResponseCache::forgetByKey($key);
ResponseCache::forgetRoute('posts.index');
```

- **makeKey(Request $request, array $overrides = [])** – build the cache key used for the request.
- **rememberResponse(Request $request, Closure $callback, DateTimeInterface|int $ttl)** – return the cached response or execute the callback and store the result.
- **forgetByKey(string $key)** – remove a cached response.
- **forgetRoute(string $routeName)** – remove all cached responses for the route (uses a bounded key index, max 1000 entries).

## Artisan Commands

```bash
php artisan response-cache:clear --key="get:posts::en:guest"
php artisan response-cache:clear --route=posts.index
php artisan response-cache:stats
```

- **response-cache:clear** – clear by exact key or by route name (uses the internal index).
- **response-cache:stats** – show basic cache information such as the underlying driver.

## Caveats

- The package uses key-based invalidation only; cache `flush()` is intentionally avoided because it clears unrelated data.
- Streamed responses or those not meeting cache rules (status, size, etc.) are skipped automatically.
- Route indexes are capped to the most recent 1000 keys to remain memory safe.
- Use caution when changing configuration in production; mismatched prefixes or stores can orphan keys.

## License

MIT
