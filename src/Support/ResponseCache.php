<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Support\Facades\Cache;

final class ResponseCache
{
    /**
     * Invalidate cache entries by tags.
     * If the cache store does not support tags, this is a no-op.
     *
     * @param string[] $tags
     */
    public static function invalidateByTags(array $tags): void
    {
        $store = Cache::store(config('response_cache.store'));
        if (method_exists($store, 'supportsTags') ? $store->supportsTags() : method_exists($store->getStore(), 'tags')) {
            $store->tags($tags)->flush();
        }
    }
}
