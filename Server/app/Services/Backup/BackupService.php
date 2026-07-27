<?php

namespace App\Services\Backup;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\AlertService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;
use ZipArchive;

/**
 * Creates the complete archive in shared /data2 staging using current DB credentials.
 * A host-only publisher then moves it to /data and removes the staged copy.
 */
final class BackupService
{
    public function __construct(
        private readonly MysqlDumper $dumper,
        private readonly SettingsService $settings,
        private readonly AlertService $alerts,
        private readonly BackupVolume $volume,
    ) {}

    /**
     * @return array{path: string, bytes: int, sha256: string, keep: int}
     */
    public function run(?int $keep = null): array
    {
        $stagingRoot = $this->volume->stagingRoot();
        $ulid = (string) Str::ulid();
        $filename = 'ir4-backup-'.now()->format('Y-m-d-His')."-{$ulid}.zip";
        $stagedPath = $stagingRoot.'/'.$filename;
        $workdir = storage_path('app/tmp/backup-'.$ulid);
        File::ensureDirectoryExists($workdir, 0700);

        try {
            $dumpPath = $workdir.'/database.sql';
            $this->dumper->dumpTo($dumpPath);

            $zipLocal = $workdir.'/archive.zip';
            $this->buildArchive($zipLocal, $dumpPath, $ulid);

            $sha256 = hash_file('sha256', $zipLocal);
            if ($sha256 === false) {
                throw new RuntimeException('Unable to hash backup archive.');
            }

            if (! File::move($zipLocal, $stagedPath)) {
                throw new RuntimeException("Unable to stage backup: {$stagedPath}");
            }
            $keepCount = max(1, $keep ?? (int) $this->settings->get('backup.keep_count', 30));
            File::put(
                $stagedPath.'.json',
                json_encode([
                    'sha256' => $sha256,
                    'keep' => $keepCount,
                ], JSON_PRETTY_PRINT),
            );
            $bytes = (int) filesize($stagedPath);

            Log::info('ir4.backup.staged', [
                'path' => $stagedPath,
                'bytes' => $bytes,
                'sha256' => $sha256,
            ]);

            return [
                'path' => $stagedPath,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'keep' => $keepCount,
            ];
        } catch (Throwable $e) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Site backup failed',
                payload: ['error' => $e->getMessage()],
                dedupeKey: 'backup:failed',
            );
            throw $e;
        } finally {
            File::deleteDirectory($workdir);
        }
    }

    public function raiseIfBackupMissing(): void
    {
        $hours = (int) config('backup.missing_backup_hours', 36);
        $marker = $this->volume->stagingRoot().'/last-published.json';
        if (! is_file($marker)) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'No site backup found',
                payload: ['threshold_hours' => $hours],
                dedupeKey: 'backup:missing',
            );

            return;
        }

        if (now()->getTimestamp() - filemtime($marker) > ($hours * 3600)) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Site backup missing or stale',
                payload: ['marker' => $marker, 'threshold_hours' => $hours],
                dedupeKey: 'backup:missing',
            );
        }
    }

    private function buildArchive(string $zipPath, string $dumpPath, string $backupId): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        if (! $zip->addFile($dumpPath, 'DB/database.sql')) {
            $zip->close();
            throw new RuntimeException('Unable to add database dump.');
        }

        $appRoot = $this->appRoot();
        $finder = Finder::create()
            ->files()
            ->in($appRoot)
            ->exclude($this->excludeNames())
            ->ignoreDotFiles(false)
            ->ignoreVCS(false);
        foreach ($finder as $file) {
            $zip->addFile($file->getRealPath(), 'server/'.$file->getRelativePathname());
        }

        $manifest = json_encode([
            'format' => 'ir4-site-backup/v1',
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'db_driver' => 'mysql',
            'db_name' => config('database.connections.mysql.database'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! $zip->addFromString('manifest.json', $manifest ?: '{}') || ! $zip->close()) {
            throw new RuntimeException('Unable to finalize backup archive.');
        }
    }

    private function appRoot(): string
    {
        $appRoot = realpath((string) (config('backup.app_root') ?: base_path()));
        if ($appRoot === false || ! is_dir($appRoot)) {
            throw new RuntimeException('Backup app root is not a readable directory.');
        }

        return $appRoot;
    }

    /**
     * @return list<string>
     */
    private function excludeNames(): array
    {
        return array_values(array_filter(
            (array) config('backup.exclude_directories', ['node_modules', '.git']),
            fn (mixed $name): bool => is_string($name) && $name !== '',
        ));
    }
}
