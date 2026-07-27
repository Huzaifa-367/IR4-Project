<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupPublisher;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Lerd stages with current DB credentials; host php8.4 publishes to /data.
 */
final class BackupCommand extends Command
{
    protected $signature = 'ir4:backup
                            {--publish : Move all staged archives to /data and remove local copies}
                            {--keep= : Override archive retention when publishing}';

    protected $description = 'Stage a server+DB backup, or publish staged backups to /data';

    public function handle(BackupService $backups, BackupPublisher $publisher): int
    {
        try {
            $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
            if ($this->option('publish')) {
                return $this->publish($publisher, $keep);
            }
            $result = $backups->run(keep: $keep);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup staged: '.$result['path']);
        $this->line('bytes: '.number_format($result['bytes']));
        $this->line('sha256: '.$result['sha256']);
        $this->comment('Publish on host: /usr/bin/php8.4 artisan ir4:backup --publish');

        return self::SUCCESS;
    }

    private function publish(BackupPublisher $publisher, ?int $keep): int
    {
        $result = $publisher->publishAll($keep);
        if ($result['published'] === []) {
            $this->info('No staged backups to publish.');
        } else {
            foreach ($result['published'] as $path) {
                $this->info('Published: '.$path);
            }
        }
        $this->line('kept: '.$result['kept']);

        return self::SUCCESS;
    }
}
