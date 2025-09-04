<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use AngelLeger\ResponseCache\ResponseCacheServiceProvider;
use AngelLeger\ResponseCache\Facades\ResponseCache;

class ResponseCacheTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ResponseCacheServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ResponseCache' => ResponseCache::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('response_cache.ttl', 60);
        $app['config']->set('response_cache.debug', true);
    }

    protected function defineRoutes($router): void
    {
        Route::get('/test', function () {
            return response()->json([
                'time' => now()->toISOString(),
                'random' => rand(1000, 9999),
            ]);
        })->middleware('resp.cache:ttl=60,tag:test');
    }

    /** @test */
    public function it_caches_get_requests(): void
    {
        // First request - should miss cache
        $response1 = $this->get('/test');
        $response1->assertOk();
        $data1 = $response1->json();

        // Second request - should hit cache
        $response2 = $this->get('/test');
        $response2->assertOk();
        $data2 = $response2->json();

        // Should return same data
        $this->assertEquals($data1, $data2);
    }

    /** @test */
    public function it_respects_etag_headers(): void
    {
        // First request to get ETag
        $response1 = $this->get('/test');
        $response1->assertOk();
        $etag = $response1->headers->get('ETag');

        $this->assertNotNull($etag);

        // Request with If-None-Match
        $response2 = $this->get('/test', ['If-None-Match' => $etag]);
        $response2->assertStatus(304);
    }

    /** @test */
    public function it_does_not_cache_post_requests(): void
    {
        Route::post('/test-post', function () {
            return response()->json(['time' => now()->toISOString()]);
        })->middleware('resp.cache');

        $response1 = $this->post('/test-post');
        $response1->assertOk();
        $data1 = $response1->json();

        $response2 = $this->post('/test-post');
        $response2->assertOk();
        $data2 = $response2->json();

        // Should NOT be cached
        $this->assertNotEquals($data1['time'], $data2['time']);
    }

    /** @test */
    public function it_invalidates_by_tags(): void
    {
        if (!ResponseCache::supportsTags()) {
            $this->markTestSkipped('Cache store does not support tags');
        }

        // Cache a response
        $response1 = $this->get('/test');
        $data1 = $response1->json();

        // Invalidate
        ResponseCache::invalidateByTags(['test']);

        // Should get fresh response
        $response2 = $this->get('/test');
        $data2 = $response2->json();

        $this->assertNotEquals($data1['time'], $data2['time']);
    }
}
