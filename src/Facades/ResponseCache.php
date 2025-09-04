<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string makeKey(\Illuminate\Http\Request $request, array $overrides = [])
 * @method static \Symfony\Component\HttpFoundation\Response rememberResponse(\Illuminate\Http\Request $request, \Closure $callback, DateTimeInterface|int $ttl)
 * @method static \Symfony\Component\HttpFoundation\Response|null get(string $key)
 * @method static void store(string $key, \Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response, DateTimeInterface|int $ttl)
 * @method static void forgetByKey(string $key)
 * @method static void forgetRoute(string $routeName)
 * @method static array stats()
 */
class ResponseCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AngelLeger\ResponseCache\Support\ResponseCache::class;
    }
}
