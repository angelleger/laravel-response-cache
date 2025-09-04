<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Http\Middleware;

use AngelLeger\ResponseCache\Facades\ResponseCache as CacheHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseCache
{
    /**
     * Handle an incoming request.
     *
     * Middleware parameters example:
     * ->middleware('resp.cache:ttl=120,auth=true')
     */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $config = config('response_cache');
        [$ttl, $allowAuth] = $this->parseParams($params, $config);

        if (str_contains(strtolower((string) $request->header('Cache-Control')), 'no-store')) {
            return $next($request);
        }

        if (($config['guest_only'] ?? true) && ! $allowAuth && $request->user()) {
            return $next($request);
        }

        $key = CacheHelper::makeKey($request);
        if ($payload = CacheHelper::get($key)) {
            return $payload;
        }

        /** @var Response $response */
        $response = $next($request);

        if (! $this->isCacheable($response, $config)) {
            return $response;
        }

        CacheHelper::store($key, $request, $this->prepareResponse($response, $ttl, $config), $ttl);

        return $response;
    }

    /**
     * Parse middleware parameters.
     *
     * @param  array<int,string>  $params
     * @return array{0:int,1:bool}
     */
    /**
     * @param  array<int,string>  $params
     * @param  array<string,mixed>  $config
     * @return array{0:int,1:bool}
     */
    private function parseParams(array $params, array $config): array
    {
        $ttl = (int) ($config['ttl'] ?? 300);
        $allowAuth = false;

        foreach ($params as $param) {
            if (str_starts_with($param, 'ttl=')) {
                $ttl = max(1, (int) substr($param, 4));
            } elseif (str_starts_with($param, 'auth=')) {
                $allowAuth = filter_var(substr($param, 5), FILTER_VALIDATE_BOOL);
            }
        }

        return [$ttl, $allowAuth];
    }

    /**
     * Determine if response is cacheable according to config.
     *
     * @param  array<string,mixed>  $config
     */
    private function isCacheable(Response $response, array $config): bool
    {
        $statusWhitelist = (array) ($config['status_whitelist'] ?? [200]);
        if (! in_array($response->getStatusCode(), $statusWhitelist, true)) {
            return false;
        }

        $maxKb = $config['max_payload_kb'];
        if ($maxKb !== null) {
            $size = strlen((string) $response->getContent()) / 1024;
            if ($size > $maxKb) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply cache headers and etag if enabled.
     *
     * @param  array<string,mixed>  $config
     */
    private function prepareResponse(Response $response, int $ttl, array $config): Response
    {
        if (($config['etag'] ?? true) && ! $response->headers->has('ETag')) {
            $response->headers->set('ETag', '"'.sha1((string) $response->getContent()).'"');
        }

        $response->headers->set('Cache-Control', sprintf('max-age=%d, public', $ttl));

        return $response;
    }
}
