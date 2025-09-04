<?php

declare(strict_types=1);

namespace AngelLeger\ResponseCache\Console;

use Illuminate\Console\Command;
use AngelLeger\ResponseCache\Facades\ResponseCache;

class FlushResponseCache extends Command
{
    protected $signature = 'response-cache:flush 
                            {--tags= : Comma-separated list of tags to flush}
                            {--all : Flush entire cache (use with caution)}';

    protected $description = 'Flush response cache by tags or entirely';

    public function handle(): int
    {
        if ($this->option('all')) {
            if (!$this->confirm('This will clear the ENTIRE cache store. Are you sure?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }

            ResponseCache::clearAll();
            $this->info('Entire cache cleared.');
            return self::SUCCESS;
        }

        $tagsInput = $this->option('tags');
        if (!$tagsInput) {
            $this->error('Please provide --tags=tag1,tag2 or use --all flag');
            return self::FAILURE;
        }

        if (!ResponseCache::supportsTags()) {
            $this->error('The configured cache store does not support tags.');
            $this->line('Consider using Redis or another tag-supporting driver.');
            return self::FAILURE;
        }

        $tags = array_filter(array_map('trim', explode(',', (string) $tagsInput)));

        ResponseCache::invalidateByTags($tags);
        $this->info('Flushed tags: ' . implode(', ', $tags));

        return self::SUCCESS;
    }
}
