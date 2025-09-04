# Laravel Response Cache

Production-grade response caching for Laravel 10/11:

- Full-response caching for GET/HEAD
- Per-route TTL
- Redis tags invalidation
- Safe auth variation (guest-only by default)
- ETag + 304 support
- Vary by configurable headers
- Middleware alias: `resp.cache`

## Install

```bash
composer require angelleger/laravel-response-cache
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=response-cache-config
```

Ensure your cache store supports tags (e.g., Redis). By default the package uses your default cache store, but you may specify a different store via `RESPONSE_CACHE_STORE`:

`.env`
```
CACHE_STORE=redis
REDIS_CLIENT=phpredis
# optional: RESPONSE_CACHE_STORE=redis
```

## Usage

Add middleware to routes:

```php
Route::get('/posts', [PostController::class, 'index'])
  ->middleware('resp.cache:ttl=300,tag:posts');

Route::get('/posts/{post}', [PostController::class, 'show'])
  ->middleware('resp.cache:ttl=120,tag:posts,tag:post:{post}');
```

Invalidate on mutations:

```php
use ResponseCache; // facade

ResponseCache::invalidateByTags(['posts', "post:$id"]);
```

Command:

```bash
php artisan response-cache:flush --tags=posts,post:42
```

Config keys in `config/response_cache.php`:
- `ttl`, `store`, `guest_only`, `vary_headers`, `prefix`, `etag`, `include_ip`.

## Testing locally

```bash
composer update
composer test
composer phpstan
```
