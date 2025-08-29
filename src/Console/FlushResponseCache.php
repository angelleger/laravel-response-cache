<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Console;

use Illuminate\Console\Command;
use AngelLeger\ResponseCache\Support\ResponseCache as Resp;

class FlushResponseCache extends Command
{
    protected $signature = 'response-cache:flush {--tags= : Comma-separated list of tags to flush}';
    protected $description = 'Flush response cache by tags (requires a cache store with tag support).';

    public function handle(): int
    {
        $tags = array_filter(array_map('trim', explode(',', (string) $this->option('tags'))));
        if (!$tags) {
            $this->error('Please provide --tags=tag1,tag2');
            return self::FAILURE;
        }

        Resp::invalidateByTags($tags);
        $this->info('Flushed tags: ' . implode(', ', $tags));

        return self::SUCCESS;
    }
}
