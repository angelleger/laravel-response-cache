<?php

declare(strict_types=1);

use AngelLeger\ResponseCache\Support\ResponseCache as Helper;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;

it('uses locks when configured', function () {
    $request = Request::create('/lock', 'GET');

    config(['response_cache.lock_seconds' => 10, 'response_cache.lock_wait' => 0]);

    $lock = Mockery::mock(Lock::class);
    /** @var Mockery\MockInterface&Repository&LockProvider $repository */
    $repository = Mockery::mock(Repository::class, LockProvider::class);

    $repository->shouldReceive('get')->andReturn(null, null);
    $repository->shouldReceive('put');
    $repository->shouldReceive('lock')->withArgs(function ($key, $seconds) {
        return $seconds === 10;
    })->andReturn($lock);
    $lock->shouldReceive('block')->withArgs(function ($wait, $closure) {
        return $wait === 0 && $closure instanceof Closure;
    })->andReturnUsing(function ($wait, $closure) {
        return $closure();
    });

    $helper = new Helper($repository);
    $response = $helper->rememberResponse($request, fn () => response('locked'), 10);

    expect($response->getContent())->toBe('locked');
});
