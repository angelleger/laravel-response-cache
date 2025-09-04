<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\artisan;

it('flushes cache entries by tags via command', function () {
    Cache::tags(['a'])->put('foo', 'bar', 60);
    expect(Cache::tags(['a'])->get('foo'))->toBe('bar');

    artisan('response-cache:flush', ['--tags' => 'a']);

    expect(Cache::tags(['a'])->get('foo'))->toBeNull();
});
