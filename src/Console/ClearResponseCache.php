<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Console;

use AngelLeger\ResponseCache\Facades\ResponseCache;
use Illuminate\Console\Command;

class ClearResponseCache extends Command
{
    protected $signature = 'response-cache:clear {--route=} {--key=}';

    protected $description = 'Clear cached responses by key or route index';

    public function handle(): int
    {
        $key = $this->option('key');
        $route = $this->option('route');

        if ($key) {
            ResponseCache::forgetByKey((string) $key);
            $this->info('Cleared key: '.$key);

            return self::SUCCESS;
        }

        if ($route) {
            ResponseCache::forgetRoute((string) $route);
            $this->info('Cleared route: '.$route);

            return self::SUCCESS;
        }

        $this->error('Please specify either --key or --route option.');

        return self::FAILURE;
    }
}
