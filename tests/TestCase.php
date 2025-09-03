<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Tests;

use AngelLeger\ResponseCache\ResponseCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ResponseCacheServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Use array cache store during tests
        $app['config']->set('cache.default', 'array');
    }
}
