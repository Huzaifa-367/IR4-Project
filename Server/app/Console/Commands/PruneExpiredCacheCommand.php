<?php

namespace App\Console\Commands;

use App\Services\Platform\RetentionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Delete expired Laravel database-cache rows (Hostinger-safe, runs synchronously).
 *
 * Prefer scheduling this via cron `schedule:run` — do not rely on a queue worker.
 */
final class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'ir4:prune-expired-cache';

    protected $description = 'Delete expired rows from cache / cache_locks tables';

    public function handle(RetentionService $retention): int
    {
        try {
            $removed = $retention->pruneExpiredDatabaseCache();
        } catch (Throwable $exception) {
            $this->error('Cache prune failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Removed {$removed} expired cache/lock row(s).");

        return self::SUCCESS;
    }
}
