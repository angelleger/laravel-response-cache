<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\travel;

it('honours ttl for cached responses', function () {
    Route::middleware('resp.cache:ttl=1')->get('/ttl', fn () => response('ok'));
    get('/ttl');
    travel(2)->seconds();
    $second = get('/ttl');
    expect($second->headers->get('X-Cache'))->toBeNull();
});
