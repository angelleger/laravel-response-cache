<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Contracts;

use Illuminate\Http\Request;

interface KeyResolver
{
    /**
     * Generate cache key and context data for the request
     *
     * @return array{0:string,1:array<string,string>}
     */
    public function make(Request $request): array;
}
