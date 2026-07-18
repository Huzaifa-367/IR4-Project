<?php

namespace App\Services\Backup;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\AlertService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupService
{
    public function __construct(
        private readonly DatabaseDumperFactory $dumpers,
        private readonly ArchiveEncryptor $encryptor,
        private readonly SettingsService $settings,
        private readonly AlertService $alerts,
    ) {}

    /**
     * @return array{path: string, bytes: int, kept: int}
     */
    public function run(bool $rotate = true, ?int $keep = null): array
    {
        $diskName = (string) config('backup.disk', 'backups');
        $disk = Storage::disk($diskName);
        $ulid = (string) Str::ulid();
        $stamp = now()->format('Y-m-d-His');
        $relative = "daily/ir4-backup-{$stamp}-{$ulid}.ir4bak";
        $partial = "{$relative}.partial";

        $workdir = storage_path('app/tmp/backup-'.$ulid);
        if (! is_dir($workdir) && ! mkdir($workdir, 0700, true) && ! is_dir($workdir)) {
            throw new RuntimeException("Unable to create {$workdir}");
        }

        try {
            $dumpPath = $workdir.'/database.sql';
            $this->dumpers->forConnection()->dumpTo($dumpPath);

            $manifest = $this->storageManifest();
            file_put_contents($workdir.'/storage-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            $meta = [
                'format' => 'ir4-archive/v1',
                'kind' => 'backup',
                'created_at' => now()->toIso8601String(),
                'app_name' => (string) config('app.name'),
                'app_version' => (string) config('app.version', '0.0.0'),
                'db_driver' => (string) config('database.connections.'.config('database.default').'.driver'),
                'db_name' => (string) config('database.connections.'.config('database.default').'.database'),
                'php_version' => PHP_VERSION,
                'hostname' => gethostname() ?: 'unknown',
                'backup_id' => $ulid,
            ];
            file_put_contents($workdir.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

            $zipPath = $workdir.'/archive.zip';
            $this->zipDirectory($workdir, $zipPath, ['archive.zip']);

            $key = $this->encryptor->resolveKey();
            $encryptedLocal = $workdir.'/archive.ir4bak';
            $this->encryptor->encryptFile($zipPath, $encryptedLocal, $key, ArchiveEncryptor::MAGIC_BACKUP);

            $disk->put($partial, (string) file_get_contents($encryptedLocal));
            $disk->move($partial, $relative);

            $bytes = (int) $disk->size($relative);
            $keptCount = $keep ?? (int) $this->settings->get('backup.keep_count', 30);
            $kept = $rotate ? $this->rotate($keptCount) : $keptCount;

            Log::info('ir4.backup.success', [
                'path' => $relative,
                'bytes' => $bytes,
                'kept' => $kept,
            ]);

            return ['path' => $relative, 'bytes' => $bytes, 'kept' => $kept];
        } catch (Throwable $e) {
            if ($disk->exists($partial)) {
                $disk->delete($partial);
            }
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Database backup failed',
                payload: ['error' => $e->getMessage()],
                dedupeKey: 'backup:failed',
            );
            throw $e;
        } finally {
            $this->removeDirectory($workdir);
        }
    }

    public function raiseIfBackupMissing(): void
    {
        $hours = (int) config('backup.missing_backup_hours', 36);
        $disk = Storage::disk((string) config('backup.disk', 'backups'));
        $latest = collect($disk->files('daily'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.ir4bak'))
            ->sort()
            ->last();

        if ($latest === null) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'No database backup found',
                payload: ['threshold_hours' => $hours],
                dedupeKey: 'backup:missing',
            );

            return;
        }

        $modified = $disk->lastModified($latest);
        if (now()->getTimestamp() - $modified > ($hours * 3600)) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Database backup missing or stale',
                payload: [
                    'latest' => $latest,
                    'threshold_hours' => $hours,
                ],
                dedupeKey: 'backup:missing',
            );
        }
    }

    public function rotate(int $keep): int
    {
        $keep = max(1, $keep);
        $disk = Storage::disk((string) config('backup.disk', 'backups'));
        $files = collect($disk->files('daily'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.ir4bak'))
            ->sort()
            ->values();

        $remove = $files->slice(0, max(0, $files->count() - $keep));
        foreach ($remove as $path) {
            $disk->delete($path);
        }

        return $files->count() - $remove->count();
    }

    /**
     * @return list<array{path: string, bytes: int, sha256: string}>
     */
    private function storageManifest(): array
    {
        $disk = Storage::disk('private');
        $entries = [];
        foreach ($disk->allFiles() as $path) {
            $entries[] = [
                'path' => $path,
                'bytes' => (int) $disk->size($path),
                'sha256' => hash_file('sha256', $disk->path($path)) ?: '',
            ];
        }

        return $entries;
    }

    /**
     * @param  list<string>  $exclude
     */
    private function zipDirectory(string $directory, string $zipPath, array $exclude = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip archive.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($directory, '', $absolute), DIRECTORY_SEPARATOR);
            if (in_array(basename($relative), $exclude, true) || in_array($relative, $exclude, true)) {
                continue;
            }
            $zip->addFile($absolute, str_replace('\\', '/', $relative));
        }

        $zip->close();
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $file->isDir() ? rmdir($path) : unlink($path);
        }

        rmdir($directory);
    }
}
