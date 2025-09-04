<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
