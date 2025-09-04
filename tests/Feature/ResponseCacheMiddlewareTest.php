<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use AngelLeger\ResponseCache\Facades\ResponseCache as Resp;
use AngelLeger\ResponseCache\Contracts\KeyResolver;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

it('caches get responses', function () {
    $calls = 0;

    Route::middleware('resp.cache:ttl=10')->get('/foo', function () use (&$calls) {
        $calls++;
        return response('bar')->header('Cache-Control', 'public');
    });

    $first = get('/foo');
    expect($first->getContent())->toBe('bar');
    expect($calls)->toBe(1);

    $second = get('/foo');
    expect($second->getContent())->toBe('bar');
    expect($calls)->toBe(1);
});

it('caches responses without explicit cache-control header', function () {
    $calls = 0;

    Route::middleware('resp.cache:ttl=10')->get('/baz', function () use (&$calls) {
        $calls++;
        return response('baz');
    });

    $first = get('/baz');
    expect($first->headers->get('Cache-Control'))->toBe('max-age=10, public');
    expect($calls)->toBe(1);

    $second = get('/baz');
    expect($second->getContent())->toBe('baz');
    expect($calls)->toBe(1);
});


it('returns 304 when etag matches', function () {
    Route::middleware('resp.cache:ttl=10')->get('/etag', function () {
        return response('etagged')->header('Cache-Control', 'public');
    });

    $first = get('/etag');
    $etag = $first->headers->get('ETag');

    $second = withHeaders(['If-None-Match' => $etag])->get('/etag');

    expect($second->getStatusCode())->toBe(304);
    expect($second->getContent())->toBe('');
});

it('can invalidate cache by tags', function () {
    Cache::tags(['a'])->put('foo', 'bar', 60);
    expect(Cache::tags(['a'])->get('foo'))->toBe('bar');

    Resp::invalidateByTags(['a']);

    expect(Cache::tags(['a'])->get('foo'))->toBeNull();
});

it('can invalidate multiple tags independently', function () {
    Cache::tags(['a'])->put('foo', 'bar', 60);
    Cache::tags(['b'])->put('baz', 'qux', 60);

    Resp::invalidateByTags(['a', 'b']);

    expect(Cache::tags(['a'])->get('foo'))->toBeNull();
    expect(Cache::tags(['b'])->get('baz'))->toBeNull();
});

it('can retrieve cached responses by tag', function () {
    Route::middleware('resp.cache:ttl=10,tag:foo')->get('/bar', function () {
        return response('baz')->header('Cache-Control', 'public');
    });

    withHeaders(['Accept' => 'application/json'])->get('/bar');

    $request = \Illuminate\Http\Request::create('/bar', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    /** @var KeyResolver $resolver */
    $resolver = app(KeyResolver::class);
    [$key] = $resolver->make($request);

    $cached = Resp::getByTags(['foo'], $key);

    expect($cached)->not->toBeNull();
    expect($cached->getContent())->toBe('baz');
});
