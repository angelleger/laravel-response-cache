<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Contracts;

use Illuminate\Http\Request;

interface KeyResolver
{
    /**
     * Devuelve [cacheKey, contextArray] para diagnóstico/observabilidad.
     *
     * @return array{0:string,1:array<string,string>}
     */
    public function make(Request $request): array;
}
