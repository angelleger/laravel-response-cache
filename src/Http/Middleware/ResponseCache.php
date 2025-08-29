<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use AngelLeger\ResponseCache\Contracts\KeyResolver;

class ResponseCache
{
    /**
     * Uso: ->middleware('resp.cache:ttl=120,tag:posts,auth=false')
     *
     * Parámetros:
     *  - ttl=SEGUNDOS (int)
     *  - tag:foo      (múltiples)
     *  - auth=true|false (permite cachear autenticados; por defecto false si guest_only=true)
     */
    public function handle(Request $request, Closure $next, ...$params): Response
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $cfg = config('response_cache');
        [$ttl, $tags, $allowAuth] = $this->parse($params, $cfg);

        if (($cfg['guest_only'] ?? true) && !$allowAuth && $request->user()) {
            return $next($request);
        }

        // Respeta no-store del cliente
        if (str_contains(strtolower((string) $request->headers->get('Cache-Control')), 'no-store')) {
            return $next($request);
        }

        /** @var KeyResolver $resolver */
        $resolver = app(KeyResolver::class);
        [$key] = $resolver->make($request);

        $store = Cache::store(config('cache.default'));
        $supportsTags = method_exists($store->getStore(), 'tags');

        /** @var CacheRepository $repo */
        $repo = ($supportsTags && $tags) ? $store->tags($tags) : $store;

        // HIT
        if ($payload = $repo->get($key)) {
            $hit = $this->unpack($payload);

            if ($cfg['etag'] ?? true) {
                $ifNoneMatch = $request->headers->get('If-None-Match');
                $etag        = $hit->headers->get('ETag');
                if ($ifNoneMatch && $etag && $ifNoneMatch === $etag) {
                    return new Response('', 304, $this->headers($hit));
                }
            }

            return $hit;
        }

        // MISS
        /** @var Response $response */
        $response = $next($request);

        if (!$this->cacheable($response)) {
            return $response;
        }

        // ETag
        if (($cfg['etag'] ?? true) && !$response->headers->has('ETag')) {
            $response->headers->set('ETag', '"' . sha1($response->getContent()) . '"');
        }

        // Cache-Control razonable si no viene
        if (!$response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'public, max-age=' . $ttl);
        }

        $repo->put($key, $this->pack($response), $ttl);

        // Respuesta condicional post-guardado
        if ($cfg['etag'] ?? true) {
            $ifNoneMatch = $request->headers->get('If-None-Match');
            $etag        = $response->headers->get('ETag');
            if ($ifNoneMatch && $etag && $ifNoneMatch === $etag) {
                return new Response('', 304, $this->headers($response));
            }
        }

        return $response;
    }

    /** @return array{0:int,1:array<int,string>,2:bool} */
    private function parse(array $params, array $cfg): array
    {
        $ttl = (int) ($cfg['ttl'] ?? 300);
        $tags = [];
        $allowAuth = false;

        foreach ($params as $p) {
            if (str_starts_with($p, 'ttl=')) {
                $ttl = max(1, (int) substr($p, 4));
            } elseif (str_starts_with($p, 'tag:')) {
                $tags[] = substr($p, 4);
            } elseif (str_starts_with($p, 'auth=')) {
                $allowAuth = filter_var(substr($p, 5), FILTER_VALIDATE_BOOL);
            }
        }

        return [$ttl, array_values(array_filter($tags)), $allowAuth];
    }

    private function cacheable(Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        if (!str_contains($type, 'application/json') && !str_contains($type, 'text/html')) {
            return false;
        }

        $cc = strtolower((string) $response->headers->get('Cache-Control', ''));
        if (str_contains($cc, 'no-store') || str_contains($cc, 'private')) {
            return false;
        }

        return true;
    }

    private function pack(Response $response): array
    {
        return [
            'status'  => $response->getStatusCode(),
            'headers' => $this->headers($response),
            'body'    => $response->getContent(),
        ];
    }

    private function unpack(array $payload): Response
    {
        $resp = new Response($payload['body'] ?? '', $payload['status'] ?? 200);
        foreach ($payload['headers'] ?? [] as $k => $vals) {
            foreach ((array) $vals as $v) {
                $resp->headers->set($k, $v, false);
            }
        }
        return $resp;
    }

    /** Filtra headers inseguros para reutilización */
    private function headers(Response $response): array
    {
        $exclude = ['Set-Cookie', 'Transfer-Encoding', 'Content-Length'];
        $headers = [];
        foreach ($response->headers->allPreserveCase() as $k => $vals) {
            if (in_array($k, $exclude, true)) {
                continue;
            }
            $headers[$k] = is_array($vals) ? $vals : [$vals];
        }
        return $headers;
    }
}
