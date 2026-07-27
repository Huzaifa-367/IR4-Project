<?php

namespace App\Console\Commands;

use App\Services\Backup\SecureWipeService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Privileged data destruction after a verified ir4:export-all (DOC-19).
 *
 * Requires --confirm=WIPE-IR4-PROJECT-DATA and a matching export marker.
 * Prefer --dry-run first. Uses the ir4_wipe DB account for audit_logs DELETE.
 *
 * Usage:
 *   php artisan ir4:secure-wipe --dry-run --confirm=WIPE-IR4-PROJECT-DATA
 *   php artisan ir4:secure-wipe --confirm=WIPE-IR4-PROJECT-DATA --export-id=…
 */
final class SecureWipeCommand extends Command
{
    protected $signature = 'ir4:secure-wipe
                            {--confirm= : Exact phrase WIPE-IR4-PROJECT-DATA}
                            {--export-id= : Marker id to verify (default: latest)}
                            {--include-backups=1 : Also wipe backups disk}
                            {--dry-run : List targets only}';

    protected $description = 'Guarded destruction after a verified ir4:export-all (DOC-19)';

    public function handle(SecureWipeService $wipe): int
    {
        try {
            $result = $wipe->wipe(
                confirm: (string) $this->option('confirm'),
                exportId: $this->option('export-id') ?: null,
                includeBackups: (bool) $this->option('include-backups'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (Throwable $e) {
            // Guard failures (wrong phrase, missing export) surface as exceptions.
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run only — no data destroyed.');
        } else {
            $this->info('Secure wipe completed.');
        }

        $this->line("Export id: {$result['export_id']}");
        $this->line("Receipt: {$result['receipt']}");
        $this->line("Mode: {$result['mode']}");

        return self::SUCCESS;
    }
}
