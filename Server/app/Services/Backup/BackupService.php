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

/**
 * Daily full live snapshot onto a separate volume (DOC-19).
 *
 * Zip layout:
 *   ir4-backup-YYYY-mm-dd-HHMMSS-{ulid}.zip
 *     server/         — application tree (excludes node_modules, .git, …)
 *     DB/database.sql — full DB dump
 *     manifest.json
 */
final class BackupService
{
    public function __construct(
        private readonly DatabaseDumperFactory $dumpers,
        private readonly SettingsService $settings,
        private readonly AlertService $alerts,
    ) {}

    /**
     * @return array{path: string, absolute_path: string, bytes: int, kept: int, sha256: string, disk_root: string}
     */
    public function run(bool $rotate = true, ?int $keep = null): array
    {
        $diskName = (string) config('backup.disk', 'backups');
        $diskRoot = $this->prepareBackupDisk($diskName);
        $ulid = (string) Str::ulid();
        $stamp = now()->format('Y-m-d-His');
        $relative = "daily/ir4-backup-{$stamp}-{$ulid}.zip";
        $absolutePath = $diskRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $partialAbsolute = $absolutePath.'.partial';

        $workdir = storage_path('app/tmp/backup-'.$ulid);
        if (! is_dir($workdir) && ! mkdir($workdir, 0700, true) && ! is_dir($workdir)) {
            throw new RuntimeException("Unable to create {$workdir}");
        }

        try {
            $dumpPath = $workdir.'/database.sql';
            $this->dumpers->forConnection()->dumpTo($dumpPath);

            $zipLocal = $workdir.'/archive.zip';
            $this->buildArchive($zipLocal, $dumpPath, $ulid, $diskRoot);

            $sha256 = hash_file('sha256', $zipLocal);
            if ($sha256 === false) {
                throw new RuntimeException('Unable to hash backup archive.');
            }

            $this->publishArchive($zipLocal, $partialAbsolute, $absolutePath);

            if (! is_file($absolutePath)) {
                throw new RuntimeException("Backup zip missing after publish: {$absolutePath}");
            }

            $bytes = (int) filesize($absolutePath);
            $keptCount = $keep ?? (int) $this->settings->get('backup.keep_count', 30);
            $kept = $rotate ? $this->rotate($keptCount) : $keptCount;

            Log::info('ir4.backup.success', [
                'path' => $relative,
                'absolute_path' => $absolutePath,
                'disk_root' => $diskRoot,
                'bytes' => $bytes,
                'kept' => $kept,
                'sha256' => $sha256,
            ]);

            return [
                'path' => $relative,
                'absolute_path' => $absolutePath,
                'disk_root' => $diskRoot,
                'bytes' => $bytes,
                'kept' => $kept,
                'sha256' => $sha256,
            ];
        } catch (Throwable $e) {
            if (is_file($partialAbsolute)) {
                @unlink($partialAbsolute);
            }
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Site backup failed',
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
        $this->prepareBackupDisk((string) config('backup.disk', 'backups'));
        $disk = Storage::disk((string) config('backup.disk', 'backups'));
        $latest = collect($disk->files('daily'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->sort()
            ->last();

        if ($latest === null) {
            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'No site backup found',
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
                title: 'Site backup missing or stale',
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
        $this->prepareBackupDisk((string) config('backup.disk', 'backups'));
        $disk = Storage::disk((string) config('backup.disk', 'backups'));
        $files = collect($disk->files('daily'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->sort()
            ->values();

        $remove = $files->slice(0, max(0, $files->count() - $keep));
        foreach ($remove as $path) {
            $disk->delete($path);
        }

        return $files->count() - $remove->count();
    }

    /**
     * Bind the backups disk to the DOC-20 volume and return its absolute root.
     */
    private function prepareBackupDisk(string $diskName): string
    {
        if ($this->usingFakeBackupDisk($diskName)) {
            $root = rtrim(Storage::disk($diskName)->path(''), DIRECTORY_SEPARATOR);
            $resolved = realpath($root) ?: $root;
            if ($resolved === '' || ! is_dir($resolved)) {
                throw new RuntimeException("Fake backup disk root is not usable: {$root}");
            }

            return $resolved;
        }

        $root = trim((string) config('backup.disk_root', '/data/ir4-backups'));
        if ($root === '' || $root === '.') {
            $root = '/data/ir4-backups';
        }

        if (! str_starts_with($root, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("BACKUP_DISK_ROOT must be an absolute path, got [{$root}].");
        }

        $appRoot = realpath(base_path()) ?: base_path();
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR);
        if ($normalizedRoot === $appRoot || str_starts_with($normalizedRoot, $appRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                "BACKUP_DISK_ROOT [{$root}] is inside the app tree. "
                .'It must be the separate /data volume (e.g. /data/ir4-backups).'
            );
        }

        $storageBackups = realpath(storage_path('app/backups')) ?: storage_path('app/backups');
        if ($normalizedRoot === rtrim($storageBackups, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                'BACKUP_DISK_ROOT must not be storage/app/backups on the app volume. '
                .'Use /data/ir4-backups on the R360.'
            );
        }

        if (! is_dir($root) && ! mkdir($root, 0750, true) && ! is_dir($root)) {
            throw new RuntimeException(
                "Unable to create backup disk root [{$root}]. "
                .'Ensure /data is mounted and the app user can write there.'
            );
        }

        if (! is_writable($root)) {
            throw new RuntimeException(
                "Backup disk root is not writable: {$root}. "
                .'Fix ownership, e.g. chown the PHP/app user on /data/ir4-backups.'
            );
        }

        $resolved = realpath($root);
        if ($resolved === false) {
            throw new RuntimeException("Unable to resolve backup disk root: {$root}");
        }

        config(["filesystems.disks.{$diskName}.root" => $resolved]);
        Storage::forgetDisk($diskName);

        $live = rtrim(Storage::disk($diskName)->path(''), DIRECTORY_SEPARATOR);
        $liveResolved = realpath($live) ?: $live;
        if ($liveResolved !== $resolved) {
            throw new RuntimeException(
                "Backup disk rebound mismatch: config={$resolved} live={$liveResolved}"
            );
        }

        return $resolved;
    }

    private function usingFakeBackupDisk(string $diskName): bool
    {
        if (! app()->environment('testing')) {
            return false;
        }

        $path = str_replace('\\', '/', Storage::disk($diskName)->path(''));

        return str_contains($path, '/framework/testing/disks/');
    }

    private function publishArchive(string $zipLocal, string $partialAbsolute, string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create backup directory: {$directory}");
        }

        $from = fopen($zipLocal, 'rb');
        if ($from === false) {
            throw new RuntimeException('Unable to open backup archive for reading.');
        }

        $to = fopen($partialAbsolute, 'wb');
        if ($to === false) {
            fclose($from);
            throw new RuntimeException("Unable to open {$partialAbsolute} for writing.");
        }

        try {
            $copied = stream_copy_to_stream($from, $to);
            if ($copied === false) {
                throw new RuntimeException('Failed streaming backup archive to the backup volume.');
            }
        } finally {
            fclose($from);
            fclose($to);
        }

        if (! rename($partialAbsolute, $absolutePath)) {
            @unlink($partialAbsolute);
            throw new RuntimeException("Unable to finalize backup archive at {$absolutePath}");
        }
    }

    private function buildArchive(string $zipPath, string $dumpPath, string $backupId, string $backupRoot): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip archive.');
        }

        if (! $zip->addFile($dumpPath, 'DB/database.sql')) {
            $zip->close();
            throw new RuntimeException('Unable to add database dump to archive.');
        }

        $appRoot = realpath((string) (config('backup.app_root') ?: base_path()));
        if ($appRoot === false || ! is_dir($appRoot)) {
            $zip->close();
            throw new RuntimeException('Backup app root is not a readable directory.');
        }

        $excludeNames = array_values(array_filter(
            (array) config('backup.exclude_directories', ['node_modules', '.git']),
            fn (mixed $name): bool => is_string($name) && $name !== '',
        ));

        $this->addDirectoryToZip($zip, $appRoot, 'server', $excludeNames, $backupRoot);

        $default = (string) config('database.default');
        $manifest = [
            'format' => 'ir4-site-backup/v1',
            'kind' => 'backup',
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
            'app_name' => (string) config('app.name'),
            'app_url' => (string) config('app.url'),
            'php_version' => PHP_VERSION,
            'hostname' => gethostname() ?: 'unknown',
            'db_driver' => (string) config("database.connections.{$default}.driver"),
            'db_name' => (string) config("database.connections.{$default}.database"),
            'backup_disk_root' => $backupRoot,
            'exclude_directories' => $excludeNames,
        ];
        $manifestPath = dirname($dumpPath).'/manifest.json';
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
        $zip->addFile($manifestPath, 'manifest.json');

        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize zip archive.');
        }
    }

    /**
     * @param  list<string>  $excludeNames
     */
    private function addDirectoryToZip(
        ZipArchive $zip,
        string $sourceRoot,
        string $zipPrefix,
        array $excludeNames,
        ?string $backupRoot,
    ): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) use ($excludeNames, $backupRoot): bool {
                    if ($current->isLink()) {
                        return false;
                    }
                    $name = $current->getFilename();
                    if (in_array($name, $excludeNames, true)) {
                        return false;
                    }
                    if ($backupRoot !== null) {
                        $path = $current->getPathname();
                        if ($path === $backupRoot || str_starts_with($path, $backupRoot.DIRECTORY_SEPARATOR)) {
                            return false;
                        }
                    }

                    return true;
                },
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink() || ! $file->isReadable()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($sourceRoot, '', $absolute), DIRECTORY_SEPARATOR);
            $entry = $zipPrefix.'/'.str_replace('\\', '/', $relative);

            if (! $zip->addFile($absolute, $entry)) {
                throw new RuntimeException("Unable to add {$relative} to backup archive.");
            }
        }
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
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
