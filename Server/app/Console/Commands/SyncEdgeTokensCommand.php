<?php

namespace App\Console\Commands;

use App\Support\EdgeDeviceCredentials;
use Illuminate\Console\Command;

/**
 * Align SCC device UUID + token hashes with the default credentials seeder.
 *
 * Usage:
 *   php artisan ir4:sync-edge-tokens
 *   php artisan ir4:sync-edge-tokens --dry-run
 */
final class SyncEdgeTokensCommand extends Command
{
    protected $signature = 'ir4:sync-edge-tokens
                            {--dry-run : Print planned updates without writing}';

    protected $description = 'Align device UUIDs and token hashes with default device credentials';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = EdgeDeviceCredentials::applyToDevices($dryRun);

        if ($result['aligned'] > 0) {
            $this->line("{$result['aligned']} already aligned");
        }
        if ($result['updated'] > 0) {
            $this->info($dryRun
                ? "{$result['updated']} would change"
                : "{$result['updated']} updated");
        }
        if ($result['missing'] > 0) {
            $this->warn("{$result['missing']} reference(s) missing — seed the site registry first.");
        }

        $this->line($dryRun
            ? 'Dry-run complete.'
            : 'Done. Edge poles: ir4-edge secrets --pole NN');

        return $result['missing'] > 0 && $result['updated'] === 0 && $result['aligned'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
