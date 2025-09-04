# Laravel Response Cache

Production-grade response caching for Laravel 10/11 with advanced features:

- 🚀 Full-response caching for GET/HEAD requests
- ⏱️ Per-route TTL configuration
- 🏷️ Redis tags for granular invalidation
- 🔐 Safe authentication variation (guest-only by default)
- 📦 ETag generation and 304 Not Modified support
- 🎯 Vary by configurable headers
- 🛠️ Easy middleware alias: `resp.cache`

## Installation

```bash
composer require angelleger/laravel-response-cache
```

Publish configuration (optional):

```bash
php artisan vendor:publish --tag=response-cache-config
```

## Configuration

Ensure your cache store supports tags (e.g., Redis):

```env
CACHE_STORE=redis
REDIS_CLIENT=phpredis
RESPONSE_CACHE_STORE=redis # Optional, defaults to CACHE_STORE
RESPONSE_CACHE_DEBUG=true  # Enable debug logging
```

## Usage

### Basic Usage

```php
// Cache for default TTL (300 seconds)
Route::get('/posts', [PostController::class, 'index'])
    ->middleware('resp.cache');

// Cache with custom TTL
Route::get('/posts/{post}', [PostController::class, 'show'])
    ->middleware('resp.cache:ttl=120');
```

### With Tags for Invalidation

```php
// Tag-based caching
Route::get('/posts', [PostController::class, 'index'])
    ->middleware('resp.cache:ttl=300,tag:posts');

Route::get('/posts/{post}', [PostController::class, 'show'])
    ->middleware('resp.cache:ttl=120,tag:posts,tag:post:{post}');

// Invalidate on mutations
use AngelLeger\ResponseCache\Facades\ResponseCache;

class PostController extends Controller
{
    public function update(Request $request, Post $post)
    {
        $post->update($request->validated());
        
        // Invalidate related caches
        ResponseCache::invalidateByTags([
            'posts',
            "post:{$post->id}"
        ]);
        
        return response()->json($post);
    }
}
```

### Allow Caching for Authenticated Users

```php
// By default, only guests are cached
// To cache authenticated users on specific routes:
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth', 'resp.cache:ttl=60,auth=true');
```

### Console Commands

```bash
# Flush by tags
php artisan response-cache:flush --tags=posts,post:42

# Clear entire cache (use with caution!)
php artisan response-cache:flush --all
```

## Configuration Options

```php
// config/response_cache.php

return [
    'ttl' => 300,                    // Default TTL in seconds
    'store' => null,                 // Cache store (null = default)
    'guest_only' => true,            // Only cache for guests
    'vary_headers' => [              // Headers that vary the cache
        'Accept',
        'Accept-Language',
        'X-Locale',
    ],
    'prefix' => 'resp_cache:',       // Cache key prefix
    'etag' => true,                  // Enable ETag/304 support
    'include_ip' => false,           // Include IP in cache key
    'debug' => false,                // Enable debug logging
];
```

## Testing with Laravel Sail

### 1. Setup Redis in Sail

```yaml
# docker-compose.yml
services:
    laravel.test:
        # ...
    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
```

### 2. Test Script

Create `routes/test.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use AngelLeger\ResponseCache\Facades\ResponseCache;

Route::get('/test-cache', function () {
    return response()->json([
        'time' => now()->toISOString(),
        'random' => rand(1000, 9999),
        'cache_support' => ResponseCache::supportsTags()
    ]);
})->middleware('resp.cache:ttl=60,tag:test');
```

### 3. Verify with cURL

```bash
# First request (MISS)
curl -i http://localhost/test-cache

# Second request (HIT - same data)
curl -i http://localhost/test-cache

# Check for X-Cache header
curl -i http://localhost/test-cache | grep X-Cache
# Should show: X-Cache: HIT

# Test 304 Not Modified
ETAG=$(curl -s -I http://localhost/test-cache | grep ETag | cut -d' ' -f2)
curl -i -H "If-None-Match: $ETAG" http://localhost/test-cache
# Should return 304

# Invalidate cache
sail artisan response-cache:flush --tags=test

# Next request should be fresh
curl -i http://localhost/test-cache
```

### 4. Monitor Redis

```bash
# Watch Redis keys in real-time
sail redis-cli MONITOR

# Check keys
sail redis-cli KEYS "resp_cache:*"

# Check tag entries
sail redis-cli SMEMBERS "tag:test:entries"
```

## Advanced Usage

### Custom Key Resolver

```php
use AngelLeger\ResponseCache\Contracts\KeyResolver;
use Illuminate\Http\Request;

class CustomKeyResolver implements KeyResolver
{
    public function make(Request $request): array
    {
        // Custom logic for cache key generation
        $key = 'custom:' . sha1($request->fullUrl());
        $context = ['url' => $request->fullUrl()];
        
        return [$key, $context];
    }
}

// Register in AppServiceProvider
$this->app->bind(KeyResolver::class, CustomKeyResolver::class);
```

### Programmatic Invalidation

```php
use AngelLeger\ResponseCache\Facades\ResponseCache;

// In your controllers or events
ResponseCache::invalidateByTags(['posts']);

// Check if tags are supported
if (ResponseCache::supportsTags()) {
    ResponseCache::invalidateByTags(['products', 'category:1']);
}

// Get cache statistics
$stats = ResponseCache::stats();
```
### Retrieve Cached Response by Tags

```php
use AngelLeger\ResponseCache\Facades\ResponseCache;
use AngelLeger\ResponseCache\Contracts\KeyResolver;

// Build cache key using the configured resolver
[$key] = app(KeyResolver::class)->make(request());

if ($response = ResponseCache::getByTags(['posts'], $key)) {
    return $response; // Symfony Response with restored headers
}
```

### API Resource Example

```php
// routes/api.php
Route::apiResource('posts', PostController::class);

// PostController.php
class PostController extends Controller
{
    public function __construct()
    {
        // Cache index and show actions
        $this->middleware('resp.cache:ttl=300,tag:posts')
            ->only(['index']);
            
        $this->middleware('resp.cache:ttl=600,tag:posts,tag:post:{post}')
            ->only(['show']);
    }
    
    public function store(Request $request)
    {
        $post = Post::create($request->validated());
        ResponseCache::invalidateByTags(['posts']);
        return new PostResource($post);
    }
    
    public function update(Request $request, Post $post)
    {
        $post->update($request->validated());
        ResponseCache::invalidateByTags(['posts', "post:{$post->id}"]);
        return new PostResource($post);
    }
    
    public function destroy(Post $post)
    {
        $post->delete();
        ResponseCache::invalidateByTags(['posts', "post:{$post->id}"]);
        return response()->noContent();
    }
}
```

## Debugging

Enable debug mode to see cache operations:

```env
RESPONSE_CACHE_DEBUG=true
```

Then check your logs:

```bash
tail -f storage/logs/laravel.log | grep ResponseCache
```

Or with Sail:

```bash
sail artisan tail --filter="ResponseCache"
```

## Performance Tips

1. **Use specific tags**: More granular tags allow for precise invalidation
2. **Set appropriate TTLs**: Balance freshness with performance
3. **Monitor hit rates**: Use Redis INFO stats to track cache effectiveness
4. **Avoid caching personalized content**: Unless using auth=true carefully
5. **Use CDN for static assets**: This package is for dynamic content

## Cache Headers Explained

The middleware automatically manages these headers:

- **Cache-Control**: Set to `public, max-age={ttl}` for cached responses
- **ETag**: Generated from response content for validation
- **X-Cache**: Added to indicate cache hits (`HIT` or `MISS`)
- **Vary**: Preserved to indicate cache variations

## Common Use Cases

### E-commerce Product Listings

```php
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('resp.cache:ttl=300,tag:products');

Route::get('/categories/{category}/products', [ProductController::class, 'byCategory'])
    ->middleware('resp.cache:ttl=300,tag:products,tag:category:{category}');

// Invalidate when products change
Event::listen(ProductUpdated::class, function ($event) {
    ResponseCache::invalidateByTags([
        'products',
        'category:' . $event->product->category_id
    ]);
});
```

### Blog with Comments

```php
Route::get('/posts/{post}', [PostController::class, 'show'])
    ->middleware('resp.cache:ttl=600,tag:post:{post}');

// When a comment is added
public function storeComment(Request $request, Post $post)
{
    $comment = $post->comments()->create($request->validated());
    ResponseCache::invalidateByTags(["post:{$post->id}"]);
    return response()->json($comment);
}
```

### API Rate Limiting with Cache

```php
Route::middleware(['throttle:api', 'resp.cache:ttl=60'])->group(function () {
    Route::get('/api/search', [SearchController::class, 'search']);
});
```

## Troubleshooting

### Cache not working?

1. Verify Redis connection:
```bash
sail redis-cli ping
```

2. Check if store supports tags:
```php
php artisan tinker
>>> ResponseCache::supportsTags()
```

3. Enable debug mode and check logs

### Headers issues?

- The middleware removes problematic headers like `Set-Cookie` from cached responses
- Check for other middleware that might be setting conflicting headers

### Performance issues?

- Use Redis with persistence disabled for cache-only instances
- Consider using separate Redis databases for cache and sessions
- Monitor memory usage with `redis-cli INFO memory`

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- Redis (or other tag-supporting cache driver) for invalidation features

## Testing

```bash
composer test
composer phpstan
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests locally (`composer test`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE](LICENSE) file for more information.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/angelleger/laravel-response-cache/issues).

## Credits

- [Angel Leger](https://github.com/angelleger)(https://www.linkedin.com/in/angelleger/)
- [All Contributors](../../contributors)

---

Made with ❤️ for the Laravel community
