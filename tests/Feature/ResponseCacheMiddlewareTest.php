<?php

declare(strict_types=1);

use AngelLeger\ResponseCache\Facades\ResponseCache;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

it('caches get responses', function () {
    $calls = 0;
    Route::middleware('resp.cache:ttl=10')->get('/foo', function () use (&$calls) {
        $calls++;

        return response('bar');
    });

    $first = get('/foo');
    expect($first->headers->get('X-Cache'))->toBeNull();

    $second = get('/foo');
    expect($second->headers->get('X-Cache'))->toBe('HIT');
    expect($calls)->toBe(1);
});

it('uses normalized key for sorted query and vary headers', function () {
    Route::middleware('resp.cache:ttl=10')->get('/sorted', fn () => response('ok'));

    $a = get('/sorted?b=1&a=2');
    $b = get('/sorted?a=2&b=1');
    expect($b->headers->get('X-Cache'))->toBe('HIT');

    $c = withHeaders(['Accept-Language' => 'fr'])->get('/sorted?a=2&b=1');
    expect($c->headers->get('X-Cache'))->toBeNull();
});

it('skips caching for authenticated users when guest_only', function () {
    Route::middleware('resp.cache:ttl=10')->get('/auth', fn () => response('secret'));

    $user = new class extends User
    {
        protected $table = 'users';

        public $timestamps = false;
    };
    $user->id = 1;
    actingAs($user);

    $first = get('/auth');
    $second = get('/auth');
    expect($second->headers->get('X-Cache'))->toBeNull();
});

it('respects status whitelist', function () {
    $calls = 0;
    Route::middleware('resp.cache:ttl=10')->get('/status', function () use (&$calls) {
        $calls++;

        return response('nope', 500);
    });
    get('/status');
    get('/status');
    expect($calls)->toBe(2);
});

it('can forget cached response by key', function () {
    Route::middleware('resp.cache:ttl=10')->get('/forget', fn () => response('baz'));
    get('/forget');
    $key = ResponseCache::makeKey(Request::create('/forget', 'GET'));
    expect(ResponseCache::get($key))->not->toBeNull();

    ResponseCache::forgetByKey($key);

    $second = get('/forget');
    expect($second->headers->get('X-Cache'))->toBeNull();

    $third = get('/forget');
    expect($third->headers->get('X-Cache'))->toBe('HIT');
});

it('clears cache via artisan command', function () {
    Route::middleware('resp.cache:ttl=10')->get('/cli', fn () => response('ok'));
    get('/cli');
    $key = ResponseCache::makeKey(Request::create('/cli', 'GET'));

    artisan('response-cache:clear', ['--key' => $key])->assertExitCode(0);

    $again = get('/cli');
    expect($again->headers->get('X-Cache'))->toBeNull();
});
