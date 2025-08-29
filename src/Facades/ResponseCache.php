<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void invalidateByTags(array $tags)
 */
class ResponseCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AngelLeger\ResponseCache\Support\ResponseCache::class;
    }
}
