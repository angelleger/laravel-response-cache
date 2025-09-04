<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ResponseCache
{
    /**
     * Invalidate cache entries by tags
     *
     * @param string[] $tags
     */
    public function invalidateByTags(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $store = Cache::store(config('response_cache.store'));

        foreach ($tags as $tag) {
            try {
                $store->tags([$tag])->flush();

                if (config('response_cache.debug', false)) {
                    Log::debug('ResponseCache: Invalidated tag', ['tag' => $tag]);
                }
            } catch (\BadMethodCallException $e) {
                Log::warning('ResponseCache: Store does not support tags for invalidation', [
                    'store' => get_class($store),
                    'tag' => $tag,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Retrieve a cached response by tags and key
     *
     * @param string[] $tags
     */
    public function getByTags(array $tags, string $key): ?Response
    {
        $store = Cache::store(config('response_cache.store'));

        try {
            $repo = $store->tags($tags);
            /** @var array{status?:int,headers?:array<string,array<string>>,content?:string}|null $payload */
            $payload = $repo->get($key);

            if ($payload === null) {
                return null;
            }

            $response = new Response(
                $payload['content'] ?? '',
                $payload['status'] ?? 200
            );

            foreach ($payload['headers'] ?? [] as $name => $values) {
                foreach ((array) $values as $value) {
                    $response->headers->set($name, $value, false);
                }
            }

            $response->headers->set('X-Cache', 'HIT');

            if (config('response_cache.debug', false)) {
                Log::debug('ResponseCache: Retrieved cached response', ['key' => $key, 'tags' => $tags]);
            }

            return $response;
        } catch (\BadMethodCallException $e) {
            Log::warning('ResponseCache: Store does not support tags for retrieval', [
                'store' => get_class($store),
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Clear all cached responses
     */
    public function clearAll(): void
    {
        $store = Cache::store(config('response_cache.store'));

        // This is a nuclear option - use with caution
        // For Redis, you might want to use SCAN to find keys with prefix
        if (method_exists($store->getStore(), 'flush')) {
            Log::warning('ResponseCache: Clearing entire cache store');
            $store->getStore()->flush();
        }
    }

    /**
     * Get cache statistics (if available)
     *
     * @return array<string,mixed>
     */
    public function stats(): array
    {
        $store = Cache::store(config('response_cache.store'));

        // This would need implementation based on your cache driver
        // For Redis, you could use INFO command
        return [
            'driver' => get_class($store->getStore()),
            'supports_tags' => $this->supportsTags(),
        ];
    }

    /**
     * Check if current cache store supports tags
     */
    public function supportsTags(): bool
    {
        $store = Cache::store(config('response_cache.store'));

        try {
            $store->tags(['test']);
            return true;
        } catch (\BadMethodCallException $e) {
            return false;
        }
    }
}
