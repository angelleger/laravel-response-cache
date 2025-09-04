<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache;

use AngelLeger\ResponseCache\Support\ResponseCache;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ResponseCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/response_cache.php', 'response_cache');

        $this->app->singleton(ResponseCache::class, fn () => new ResponseCache);
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../config/response_cache.php' => config_path('response_cache.php'),
        ], 'response-cache-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearResponseCache::class,
                Console\ResponseCacheStats::class,
            ]);
        }

        $router->aliasMiddleware('resp.cache', Http\Middleware\ResponseCache::class);
    }
}
