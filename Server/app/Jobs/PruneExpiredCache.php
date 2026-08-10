<?php

namespace App\Jobs;

use App\Services\RetentionService;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Synchronous prune (no ShouldQueue) so Schedule::job / artisan both run inline.
 * Hostinger often has no queue:work — queued prunes never execute.
 */
final class PruneExpiredCache
{
    use Queueable;

    public function handle(RetentionService $retention): void
    {
        $retention->pruneExpiredDatabaseCache();
    }
}
