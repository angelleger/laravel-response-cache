<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Http\Request;
use AngelLeger\ResponseCache\Contracts\KeyResolver;

final class DefaultKeyResolver implements KeyResolver
{
    /**
     * @param string[] $varyHeaders
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $varyHeaders = [],
    ) {}

    /**
     * Generate cache key and context
     *
     * @return array{0:string,1:array<string,string>}
     */
    public function make(Request $request): array
    {
        $parts = [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
        ];

        // Include IP if configured
        if (config('response_cache.include_ip', false)) {
            $parts['ip'] = (string) $request->ip();
        }

        // Include vary headers
        foreach ($this->varyHeaders as $header) {
            $normalizedHeader = strtolower($header);
            $value = (string) $request->headers->get($header, '');
            if ($value !== '') {
                $parts['header:' . $normalizedHeader] = $value;
            }
        }

        // Include user ID if authenticated
        if ($request->user()) {
            $parts['user_id'] = (string) $request->user()->getAuthIdentifier();
        }

        // Generate deterministic key
        $raw = json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = sha1($raw);
        $key = $this->prefix . $hash;

        return [$key, $parts];
    }
}
