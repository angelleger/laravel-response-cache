<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use AngelLeger\ResponseCache\Contracts\KeyResolver;
use AngelLeger\ResponseCache\Support\DefaultKeyResolver;

class ResponseCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/response_cache.php', 'response_cache');

        $this->app->singleton(KeyResolver::class, function ($app) {
            return new DefaultKeyResolver(
                prefix: (string) config('response_cache.prefix', 'resp_cache:'),
                varyHeaders: (array) config('response_cache.vary_headers', [])
            );
        });
    }

    public function boot(Router $router, HttpKernel $kernel): void
    {
        $this->publishes([
            __DIR__ . '/../config/response_cache.php' => config_path('response_cache.php'),
        ], 'response-cache-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\FlushResponseCache::class,
            ]);
        }

        $router->aliasMiddleware('resp.cache', Http\Middleware\ResponseCache::class);
    }
}
