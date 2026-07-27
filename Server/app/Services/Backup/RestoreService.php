<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Restore DB/database.sql from a site backup zip into a staging connection.
 */
final class RestoreService
{
    public function __construct(
        private readonly DatabaseDumperFactory $dumpers,
    ) {}

    /**
     * @return array{meta: array<string, mixed>, files: list<string>}
     */
    public function verify(string $archive): array
    {
        return $this->unpack($archive, restore: false);
    }

    /**
     * @return array{meta: array<string, mixed>, files: list<string>}
     */
    public function restore(
        string $archive,
        string $connection,
        bool $forceLive = false,
    ): array {
        $default = (string) config('database.default');
        if ($connection === $default && ! $forceLive) {
            throw new RuntimeException('Refusing to restore into the default connection without --force-live.');
        }

        return $this->unpack($archive, restore: true, connection: $connection);
    }

    /**
     * @return array{meta: array<string, mixed>, files: list<string>}
     */
    private function unpack(
        string $archive,
        bool $restore,
        ?string $connection = null,
    ): array {
        $absolute = $this->resolveArchivePath($archive);
        $workdir = storage_path('app/tmp/restore-'.uniqid());
        if (! mkdir($workdir, 0700, true) && ! is_dir($workdir)) {
            throw new RuntimeException("Unable to create {$workdir}");
        }

        try {
            $contentsDir = $workdir.'/contents';
            if (! mkdir($contentsDir, 0700, true) && ! is_dir($contentsDir)) {
                throw new RuntimeException("Unable to create {$contentsDir}");
            }

            $zip = new ZipArchive;
            if ($zip->open($absolute) !== true) {
                throw new RuntimeException('Unable to open backup archive.');
            }
            $zip->extractTo($contentsDir);
            $zip->close();

            $metaPath = $contentsDir.'/manifest.json';
            $meta = is_file($metaPath)
                ? (json_decode((string) file_get_contents($metaPath), true) ?: [])
                : [];

            $files = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contentsDir, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = ltrim(str_replace($contentsDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                }
            }

            if ($restore) {
                $dump = $contentsDir.'/DB/database.sql';
                if (! is_file($dump)) {
                    throw new RuntimeException('Archive does not contain DB/database.sql.');
                }
                $this->dumpers->forConnection($connection)->restoreFrom($dump, (string) $connection);
            }

            return ['meta' => $meta, 'files' => $files];
        } finally {
            $this->removeDirectory($workdir);
        }
    }

    private function resolveArchivePath(string $archive): string
    {
        if (is_file($archive)) {
            return $archive;
        }

        $disk = Storage::disk((string) config('backup.disk', 'backups'));
        if ($disk->exists($archive)) {
            return $disk->path($archive);
        }

        throw new RuntimeException("Archive not found: {$archive}");
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
