<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Paths shared by Lerd (/data2) and host PHP (/data + /data2). */
final class BackupVolume
{
    public const DEFAULT_ROOT = '/data/ir4-backups';

    public function stagingRoot(): string
    {
        return $this->ensureDirectory((string) config('backup.staging_root'));
    }

    public function restoreInbox(): string
    {
        return $this->ensureDirectory((string) config('backup.restore_inbox'));
    }

    public function volumeRoot(): string
    {
        $root = trim((string) config('backup.disk_root', self::DEFAULT_ROOT));

        return ($root === '' || $root === '.') ? self::DEFAULT_ROOT : $root;
    }

    public function prepareVolume(): string
    {
        $root = $this->volumeRoot();
        if (! str_starts_with($root, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("BACKUP_DISK_ROOT must be an absolute path, got [{$root}].");
        }
        if (app()->environment('testing')) {
            return $this->ensureDirectory($root);
        }
        if (! File::isDirectory('/data')) {
            throw new RuntimeException(
                '/data is not visible to this PHP process. Run --publish/--prepare with host php8.4.'
            );
        }
        if ($this->deviceId('/data') === $this->deviceId('/')) {
            throw new RuntimeException('/data is not a separate mounted filesystem.');
        }

        return $this->ensureDirectory($root);
    }

    private function ensureDirectory(string $root): string
    {
        if ($root === '') {
            throw new RuntimeException('Backup directory is not configured.');
        }
        File::ensureDirectoryExists($root, 0750);
        if (! File::isWritable($root)) {
            throw new RuntimeException("Directory is not writable: {$root}");
        }
        $resolved = realpath($root) ?: $root;

        return $resolved;
    }

    private function deviceId(string $path): int|string
    {
        $stat = @stat($path);

        return $stat === false ? '' : (string) ($stat['dev'] ?? '');
    }
}
