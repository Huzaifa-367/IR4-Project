<?php

namespace App\Console\Commands;

use App\Services\Backup\RestoreService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Host prepares /data archive into /data2 inbox; Lerd restores with lerd-mysql.
 */
final class RestoreCommand extends Command
{
    protected $signature = 'ir4:restore
                            {archive : /data archive, inbox filename, or local path}
                            {--prepare : Host-only copy from /data to shared restore inbox}
                            {--database= : Target MySQL database (default: IR4_RESTORE_DATABASE)}
                            {--force-live : Allow restoring into the live MySQL database}
                            {--confirm= : Exact phrase RESTORE-INTO-LIVE when --force-live}
                            {--verify-only : Open archive and list contents only}';

    protected $description = 'Restore or verify a site backup zip (DB into staging by default)';

    public function handle(RestoreService $restore): int
    {
        $archive = (string) $this->argument('archive');
        $database = (string) ($this->option('database') ?: config('backup.restore_database', 'ir4_restore'));
        $forceLive = (bool) $this->option('force-live');

        if ($forceLive && $this->option('confirm') !== 'RESTORE-INTO-LIVE') {
            $this->error('Live restore requires --confirm=RESTORE-INTO-LIVE');

            return self::FAILURE;
        }

        try {
            if ($this->option('prepare')) {
                $prepared = $restore->prepare($archive);
                $this->info('Restore archive prepared: '.$prepared);
                $this->comment('Restore with Lerd: php artisan ir4:restore '.basename($prepared));

                return self::SUCCESS;
            }

            if ($this->option('verify-only')) {
                $result = $restore->verify($archive);
                $this->info('Archive verified.');
                $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');
                $this->line('Entries: '.count($result['files']));

                return self::SUCCESS;
            }

            $result = $restore->restore($archive, $database, $forceLive);
            $this->info("Restored into MySQL database [{$database}].");
            $this->line(json_encode($result['meta'], JSON_PRETTY_PRINT) ?: '{}');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
