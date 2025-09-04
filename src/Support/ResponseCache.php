<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight key based response cache helper.
 */
class ResponseCache
{
    /** @phpstan-var Repository&LockProvider */
    private Repository $store;

    public function __construct(?Repository $store = null)
    {
        /** @phpstan-var Repository&LockProvider $repository */
        $repository = $store ?? Cache::store(config('response_cache.store'));
        $this->store = $repository;
    }

    /**
     * Build a cache key for the given request.
     *
     * @param  array<string,string>  $overrides
     */
    public function makeKey(Request $request, array $overrides = []): string
    {
        $method = strtoupper($overrides['method'] ?? $request->getMethod());
        $path = $overrides['path'] ?? ($request->route()?->getName() ?? $request->path());
        $query = $overrides['normalized_query'] ?? $this->normalizedQuery($request);
        $locale = $overrides['locale'] ?? $request->getLocale();
        $guard = $overrides['auth_guard_or_guest'] ?? $this->guard();

        $parts = [$method, $path, $query, $locale, $guard];

        return config('response_cache.key_prefix').implode(':', $parts);
    }

    /**
     * Remember a response for the current request.
     */
    public function rememberResponse(Request $request, Closure $callback, DateTimeInterface|int $ttl): Response
    {
        $key = $this->makeKey($request);
        if ($payload = $this->store->get($key)) {
            return $this->buildResponse($payload);
        }

        $lockSeconds = (int) config('response_cache.lock_seconds', 0);
        if ($lockSeconds > 0) {
            $wait = (int) config('response_cache.lock_wait', 10);

            /** @var \Illuminate\Contracts\Cache\Lock $lock */
            $lock = $this->store->lock($key.':lock', $lockSeconds);

            /** @var Response $response */
            $response = $lock->block($wait, function () use ($key, $request, $callback, $ttl) {
                if ($payload = $this->store->get($key)) {
                    return $this->buildResponse($payload);
                }

                /** @var Response $generated */
                $generated = $callback($request);
                $this->store($key, $request, $generated, $ttl);

                return $generated;
            });

            return $response;
        }

        /** @var Response $response */
        $response = $callback($request);
        $this->store($key, $request, $response, $ttl);

        return $response;
    }

    /**
     * Retrieve cached response by key.
     */
    public function get(string $key): ?Response
    {
        $payload = $this->store->get($key);

        return $payload ? $this->buildResponse($payload) : null;
    }

    /**
     * Store response by key.
     */
    public function store(string $key, Request $request, Response $response, DateTimeInterface|int $ttl): void
    {
        $this->store->put($key, $this->packResponse($response), $ttl);
        $this->indexKey($request, $key, $ttl);
    }

    /**
     * Forget a cached response by key.
     */
    public function forgetByKey(string $key): void
    {
        $this->store->forget($key);
    }

    /**
     * Forget all responses associated with the given route name.
     */
    public function forgetRoute(string $routeName): void
    {
        $index = $this->indexName($routeName);
        $keys = $this->store->pull($index) ?? [];
        foreach (array_keys($keys) as $key) {
            $this->store->forget($key);
        }
    }

    /**
     * Basic cache statistics.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'driver' => get_class($this->store->getStore()),
        ];
    }

    /**
     * -----------------------------------------------------------------
     * Internal helpers
     * -----------------------------------------------------------------
     */

    /**
     * Build normalized query string including vary headers and cookies.
     */
    private function normalizedQuery(Request $request): string
    {
        $params = $request->query();

        $include = (array) config('response_cache.include_query_params', []);
        if ($include) {
            $params = array_intersect_key($params, array_flip($include));
        }

        $ignore = (array) config('response_cache.ignore_query_params', []);
        foreach ($ignore as $key) {
            if (str_ends_with($key, '*')) {
                $prefix = substr($key, 0, -1);
                foreach (array_keys($params) as $k) {
                    if (str_starts_with($k, $prefix)) {
                        unset($params[$k]);
                    }
                }
            } else {
                unset($params[$key]);
            }
        }

        foreach ((array) config('response_cache.vary_on_headers', []) as $header) {
            $value = $request->headers->get($header);
            if ($value !== null && $value !== '') {
                $params['h_'.strtolower($header)] = $value;
            }
        }

        foreach ((array) config('response_cache.vary_on_cookies', []) as $cookie) {
            $value = $request->cookies->get($cookie);
            if ($value !== null && $value !== '') {
                $params['c_'.$cookie] = $value;
            }
        }

        ksort($params);

        return http_build_query($params);
    }

    /**
     * Determine authenticated guard or guest.
     */
    private function guard(): string
    {
        $guards = array_keys(config('auth.guards', []));
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return 'guest';
    }

    /**
     * Pack a response for storage.
     *
     * @return array{status:int,headers:array<string,array<string>>,content:string}
     */
    private function packResponse(Response $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => (string) $response->getContent(),
        ];
    }

    /**
     * Rebuild a response from stored payload.
     *
     * @param  array{status:int,headers:array<string,array<string>>,content:string}  $payload
     */
    private function buildResponse(array $payload): Response
    {
        $response = new Response($payload['content'], $payload['status']);
        foreach ($payload['headers'] as $name => $values) {
            foreach ((array) $values as $value) {
                $response->headers->set($name, $value, false);
            }
        }
        $response->headers->set('X-Cache', 'HIT');

        return $response;
    }

    /**
     * Store key in route index for easier invalidation.
     */
    private function indexKey(Request $request, string $key, DateTimeInterface|int $ttl): void
    {
        $route = $request->route()?->getName();
        if (! $route) {
            return;
        }
        $index = $this->indexName($route);
        $keys = $this->store->get($index, []);
        $keys[$key] = true;

        $limit = (int) config('response_cache.index_limit', 1000);
        if (count($keys) > $limit) {
            $keys = array_slice($keys, -$limit, null, true);
        }

        $this->store->put($index, $keys, $ttl);
    }

    private function indexName(string $route): string
    {
        return config('response_cache.key_prefix').'index:'.$route;
    }
}
