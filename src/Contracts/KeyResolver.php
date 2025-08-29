<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Contracts;

use Illuminate\Http\Request;

interface KeyResolver
{
    /**
     * Devuelve [cacheKey, contextArray] para diagnóstico/observabilidad.
     */
    public function make(Request $request): array;
}
