<?php

namespace App\Console\Commands;

use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;

final class RestoreCommand extends Command
{
    protected $signature = 'ir4:restore
                            {archive : Absolute path or backups-disk relative path}
                            {--connection= : Target DB connection (default: ir4_restore)}
                            {--key= : Override decrypt key}
                            {--force-live : Allow restoring into the default connection}
                            {--confirm= : Exact phrase RESTORE-INTO-LIVE when --force-live}
                            {--verify-only : Decrypt and list contents only}';

    protected $description = 'Restore or verify an encrypted IR4 backup archive (DOC-19)';

    public function handle(RestoreService $restore): int
    {
        $archive = (string) $this->argument('archive');
        $connection = (string) ($this->option('connection') ?: config('backup.restore_connection', 'ir4_restore'));
        $forceLive = (bool) $this->option('force-live');

        if ($forceLive && $this->option('confirm') !== 'RESTORE-INTO-LIVE') {
            $this->error('Live restore requires --confirm=RESTORE-INTO-LIVE');

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            $result = $restore->verify($archive, $this->option('key') ?: null);
            $this->info('Archive verified.');
            $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');

            return self::SUCCESS;
        }

        $result = $restore->restore(
            $archive,
            $connection,
            $this->option('key') ?: null,
            $forceLive,
        );

        $this->info("Restored into connection [{$connection}].");
        $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');

        return self::SUCCESS;
    }
}
