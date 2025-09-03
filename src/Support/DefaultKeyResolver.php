<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Support;

use Illuminate\Http\Request;
use AngelLeger\ResponseCache\Contracts\KeyResolver;

final class DefaultKeyResolver implements KeyResolver
{
    public function __construct(
        private readonly string $prefix,
        /** @var string[] */
        private readonly array $varyHeaders = [],
    ) {}

    /**
     * Returns [cacheKey, contextArray] for diagnostics/observability.
     * @return array{0:string,1:array<string,string>}
     */
    public function make(Request $request): array
    {
        $includeIp = (bool) config('response_cache.include_ip', false);

        $parts = [
            'm'  => $request->getMethod(),
            'u'  => $request->fullUrl(),
        ];

        if ($includeIp) {
            $parts['ip'] = $request->ip();
        }

        foreach ($this->varyHeaders as $h) {
            $parts['h:' . strtolower($h)] = (string) $request->headers->get($h);
        }

        if ($request->user()) {
            $parts['uid'] = (string) $request->user()->getAuthIdentifier();
        }

        $raw  = json_encode($parts, JSON_UNESCAPED_SLASHES);
        $hash = sha1($raw);

        return [$this->prefix . $hash, $parts];
    }
}
