<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Console;

use AngelLeger\ResponseCache\Facades\ResponseCache;
use Illuminate\Console\Command;

class ResponseCacheStats extends Command
{
    protected $signature = 'response-cache:stats';

    protected $description = 'Show basic response cache statistics';

    public function handle(): int
    {
        $stats = ResponseCache::stats();
        foreach ($stats as $key => $value) {
            $this->line($key.': '.(is_scalar($value) ? (string) $value : json_encode($value)));
        }

        return self::SUCCESS;
    }
}
