<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use AngelLeger\ResponseCache\Contracts\KeyResolver;

class ResponseCache
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('resp.cache:ttl=120,tag:posts,auth=false')
     *
     * @param string ...$params Middleware parameters
     */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $cfg = config('response_cache');
        [$ttl, $tags, $allowAuth] = $this->parseParams($params, $cfg);

        // Check guest-only configuration
        if (($cfg['guest_only'] ?? true) && !$allowAuth && $request->user()) {
            $this->debug('Skipping cache for authenticated user', ['user_id' => $request->user()->getAuthIdentifier()]);
            return $next($request);
        }

        // Respect client's no-store directive
        if ($this->hasNoStore($request)) {
            $this->debug('Client requested no-store');
            return $next($request);
        }

        /** @var KeyResolver $resolver */
        $resolver = app(KeyResolver::class);
        [$key, $context] = $resolver->make($request);

        $store = $this->getCacheStore();
        $repo = $this->getCacheRepository($store, $tags);

        // Try to get from cache
        if ($payload = $repo->get($key)) {
            $this->debug('Cache HIT', ['key' => $key, 'tags' => $tags]);
            $response = $this->buildCachedResponse($payload, $ttl);

            // Handle conditional requests (304 Not Modified)
            if ($cfg['etag'] ?? true) {
                if ($this->shouldReturn304($request, $response)) {
                    $this->debug('Returning 304 Not Modified');
                    return $this->build304Response($response);
                }
            }

            return $response;
        }

        // Cache MISS - process request
        $this->debug('Cache MISS', ['key' => $key, 'tags' => $tags]);

        /** @var Response $response */
        $response = $next($request);

        if (!$this->isCacheable($response)) {
            $this->debug('Response not cacheable', ['status' => $response->getStatusCode()]);
            return $response;
        }

        // Generate ETag if needed
        if (($cfg['etag'] ?? true) && !$response->headers->has('ETag')) {
            $response->headers->set('ETag', $this->generateETag($response));
        }

        // Set proper Cache-Control headers
        $this->setCacheHeaders($response, $ttl);

        // Store in cache
        $repo->put($key, $this->packResponse($response), $ttl);
        $this->debug('Response cached', ['key' => $key, 'ttl' => $ttl]);

        // Handle conditional requests for fresh responses too
        if (($cfg['etag'] ?? true) && $this->shouldReturn304($request, $response)) {
            return $this->build304Response($response);
        }

        return $response;
    }

    /**
     * Parse middleware parameters
     *
     * @param string[] $params
     * @param array<string,mixed> $config
     * @return array{0:int,1:array<int,string>,2:bool}
     */
    private function parseParams(array $params, array $config): array
    {
        $ttl = (int) ($config['ttl'] ?? 300);
        $tags = [];
        $allowAuth = false;

        foreach ($params as $param) {
            if (str_starts_with($param, 'ttl=')) {
                $ttl = max(1, (int) substr($param, 4));
            } elseif (str_starts_with($param, 'tag:')) {
                $tag = substr($param, 4);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            } elseif (str_starts_with($param, 'auth=')) {
                $allowAuth = filter_var(substr($param, 5), FILTER_VALIDATE_BOOL);
            }
        }

        return [$ttl, array_unique($tags), $allowAuth];
    }

    /**
     * Check if request has no-store directive
     */
    private function hasNoStore(Request $request): bool
    {
        $cacheControl = strtolower((string) $request->headers->get('Cache-Control', ''));
        return str_contains($cacheControl, 'no-store');
    }

    /**
     * Get the configured cache store
     */
    private function getCacheStore(): CacheRepository
    {
        return Cache::store(config('response_cache.store'));
    }

    /**
     * Get cache repository with optional tag support
     *
     * @param mixed $store
     * @param array<string> $tags
     * @return CacheRepository
     */
    private function getCacheRepository($store, array $tags): CacheRepository
    {
        if (empty($tags)) {
            return $store;
        }

        // Check if store supports tags
        try {
            return $store->tags($tags);
        } catch (\BadMethodCallException $e) {
            Log::warning('ResponseCache: Store does not support tags', [
                'store' => get_class($store),
                'tags' => $tags
            ]);
            return $store;
        }
    }

    /**
     * Check if response is cacheable
     */
    private function isCacheable(Response $response): bool
    {
        // Only cache 200 OK responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Check Content-Type
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        $allowedTypes = ['application/json', 'text/html', 'application/xml', 'text/xml'];

        $typeAllowed = false;
        foreach ($allowedTypes as $type) {
            if (str_contains($contentType, $type)) {
                $typeAllowed = true;
                break;
            }
        }

        if (!$typeAllowed) {
            return false;
        }

        // Check Cache-Control directives. Laravel responses include a default
        // "no-cache, private" header which we want to override when the
        // response cache middleware is applied. We therefore only honour
        // explicit developer supplied directives.
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control', ''));
        if ($cacheControl !== '' && $cacheControl !== 'no-cache, private') {
            if (str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'no-cache')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pack response for storage
     *
     * @return array{status:int,headers:array<string,array<string>>,content:string}
     */
    private function packResponse(Response $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $this->filterHeaders($response),
            'content' => (string) $response->getContent(),
        ];
    }

    /**
     * Build response from cached data
     *
     * @param array{status?:int,headers?:array<string,array<string>>,content?:string} $payload
     */
    private function buildCachedResponse(array $payload, int $ttl): Response
    {
        $response = new Response(
            $payload['content'] ?? '',
            $payload['status'] ?? 200
        );

        // Restore headers
        foreach ($payload['headers'] ?? [] as $name => $values) {
            foreach ((array) $values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        // Ensure proper cache headers
        $this->setCacheHeaders($response, $ttl);

        // Add cache hit indicator
        $response->headers->set('X-Cache', 'HIT');

        return $response;
    }

    /**
     * Filter headers for caching
     *
     * @return array<string,array<string>>
     */
    private function filterHeaders(Response $response): array
    {
        $exclude = [
            'Set-Cookie',
            'Transfer-Encoding',
            'Content-Length',
            'Connection',
            'Keep-Alive',
            'Proxy-Authenticate',
            'Proxy-Authorization',
            'TE',
            'Trailers',
            'Upgrade',
            'phpdebugbar-id',
            'X-Debug-Token',
            'X-Debug-Token-Link',
        ];

        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $normalizedName = str_replace('_', '-', ucwords(strtolower(str_replace('-', '_', $name)), '_'));

            if (in_array($normalizedName, $exclude, true)) {
                continue;
            }

            $headers[$normalizedName] = (array) $values;
        }

        return $headers;
    }

    /**
     * Set cache control headers
     */
    private function setCacheHeaders(Response $response, int $ttl): void
    {
        // Remove conflicting headers
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');

        // Set clean Cache-Control. Order matters for some caches and our tests
        // expect the "max-age" directive first.
        $response->headers->set('Cache-Control', sprintf('max-age=%d, public', $ttl));
    }

    /**
     * Generate ETag for response
     */
    private function generateETag(Response $response): string
    {
        $content = (string) $response->getContent();
        return '"' . sha1($content) . '"';
    }

    /**
     * Check if should return 304 Not Modified
     */
    private function shouldReturn304(Request $request, Response $response): bool
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if (!$ifNoneMatch) {
            return false;
        }

        $etag = $response->headers->get('ETag');
        if (!$etag) {
            return false;
        }

        return $ifNoneMatch === $etag;
    }

    /**
     * Build 304 Not Modified response
     */
    private function build304Response(Response $response): Response
    {
        $notModified = new Response('', 304);

        // Copy relevant headers
        $headersToKeep = ['Cache-Control', 'ETag', 'Vary', 'Date', 'Last-Modified'];
        foreach ($headersToKeep as $header) {
            if ($response->headers->has($header)) {
                $notModified->headers->set($header, $response->headers->get($header));
            }
        }

        return $notModified;
    }

    /**
     * Debug logging
     *
     * @param array<string,mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        if (config('response_cache.debug', false)) {
            Log::debug('ResponseCache: ' . $message, $context);
        }
    }
}
