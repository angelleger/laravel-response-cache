<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Support\Facades\Cache;

final class ResponseCache
{
    /**
     * Invalida todas las entradas cacheadas bajo los tags dados.
     * Requiere Redis u otro store con soporte de tags.
     */
    public static function invalidateByTags(array $tags): void
    {
        $store = Cache::store(config('cache.default'));
        if (method_exists($store->getStore(), 'tags')) {
            $store->tags($tags)->flush();
        }
    }
}
