<?php

namespace App\Console\Commands;

use App\Services\Backup\SecureWipeService;
use Illuminate\Console\Command;
use Throwable;

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
