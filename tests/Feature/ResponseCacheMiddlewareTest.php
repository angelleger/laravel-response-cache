<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use AngelLeger\ResponseCache\Support\ResponseCache as Resp;

it('caches get responses', function () {
    $calls = 0;

    Route::middleware('resp.cache:ttl=10')->get('/foo', function () use (&$calls) {
        $calls++;
        return response('bar')->header('Cache-Control', 'public');
    });

    $first = $this->get('/foo');
    expect($first->getContent())->toBe('bar');
    expect($calls)->toBe(1);

    $second = $this->get('/foo');
    expect($second->getContent())->toBe('bar');
    expect($calls)->toBe(1);
});

it('returns 304 when etag matches', function () {
    Route::middleware('resp.cache:ttl=10')->get('/etag', function () {
        return response('etagged')->header('Cache-Control', 'public');
    });

    $first = $this->get('/etag');
    $etag = $first->headers->get('ETag');

    $second = $this->withHeaders(['If-None-Match' => $etag])->get('/etag');

    expect($second->getStatusCode())->toBe(304);
    expect($second->getContent())->toBe('');
});

it('can invalidate cache by tags', function () {
    Cache::tags(['a'])->put('foo', 'bar', 60);
    expect(Cache::tags(['a'])->get('foo'))->toBe('bar');

    Resp::invalidateByTags(['a']);

    expect(Cache::tags(['a'])->get('foo'))->toBeNull();
});
