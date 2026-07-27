<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Host imports from /data into shared /data2 inbox; Lerd verifies/restores there.
 */
final class RestoreService
{
    public function __construct(
        private readonly MysqlDumper $dumper,
        private readonly BackupVolume $volume,
    ) {}

    public function prepare(string $archive): string
    {
        $volumeRoot = $this->volume->prepareVolume();
        $source = str_starts_with($archive, DIRECTORY_SEPARATOR)
            ? $archive
            : $volumeRoot.'/'.ltrim($archive, '/');
        if (! File::exists($source)) {
            throw new RuntimeException("Archive not found: {$source}");
        }

        $this->assertValidArchive($source);
        $destination = $this->volume->restoreInbox().'/'.basename($source);
        $partial = $destination.'.partial';
        if (! File::copy($source, $partial)) {
            throw new RuntimeException("Unable to copy archive into restore inbox: {$partial}");
        }

        $sourceHash = hash_file('sha256', $source);
        $targetHash = hash_file('sha256', $partial);
        if ($sourceHash === false || ! hash_equals($sourceHash, (string) $targetHash)) {
            File::delete($partial);
            throw new RuntimeException('Restore archive checksum mismatch during import.');
        }
        if (! File::move($partial, $destination)) {
            File::delete($partial);
            throw new RuntimeException("Unable to finalize restore inbox file: {$destination}");
        }

        return $destination;
    }

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
        string $database,
        bool $forceLive = false,
    ): array {
        $liveDatabase = (string) config('database.connections.mysql.database');
        if ($database === $liveDatabase && ! $forceLive) {
            throw new RuntimeException('Refusing to restore into the live database without --force-live.');
        }

        $localArchive = $this->resolveLocalArchive($archive);
        $result = $this->unpack($localArchive, restore: true, database: $database);
        $this->removePreparedArchive($localArchive);

        return $result;
    }

    /**
     * @return array{meta: array<string, mixed>, files: list<string>}
     */
    private function unpack(
        string $archive,
        bool $restore,
        ?string $database = null,
    ): array {
        $absolute = $this->resolveLocalArchive($archive);
        $zip = $this->openArchive($absolute);
        $files = $this->validatedEntries($zip);
        $meta = $this->manifest($zip);

        if (! $restore) {
            $zip->close();

            return ['meta' => $meta, 'files' => $files];
        }

        $workdir = storage_path('app/tmp/restore-'.uniqid());
        File::ensureDirectoryExists($workdir, 0700);

        try {
            $dump = $workdir.'/database.sql';
            $this->extractDatabase($zip, $dump);
            $this->dumper->restoreFrom($dump, (string) $database);

            return ['meta' => $meta, 'files' => $files];
        } finally {
            $zip->close();
            File::deleteDirectory($workdir);
        }
    }

    private function resolveLocalArchive(string $archive): string
    {
        if (File::exists($archive)) {
            return realpath($archive) ?: $archive;
        }

        $inboxPath = $this->volume->restoreInbox().'/'.basename($archive);
        if (File::exists($inboxPath)) {
            return $inboxPath;
        }

        throw new RuntimeException(
            "Local archive not found: {$archive}. First run on host: "
            ."/usr/bin/php8.4 artisan ir4:restore {$archive} --prepare"
        );
    }

    private function removePreparedArchive(string $archive): void
    {
        $inbox = realpath($this->volume->restoreInbox()) ?: $this->volume->restoreInbox();
        $parent = realpath(dirname($archive)) ?: dirname($archive);
        if ($parent === $inbox) {
            File::delete($archive);
        }
    }

    private function assertValidArchive(string $archive): void
    {
        $zip = $this->openArchive($archive);
        $this->validatedEntries($zip);
        $this->manifest($zip);
        $zip->close();
    }

    /**
     * @return list<string>
     */
    private function validatedEntries(ZipArchive $zip): array
    {
        $files = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            $segments = explode('/', $entry);
            if ($entry === '' || str_starts_with($entry, '/') || in_array('..', $segments, true)) {
                throw new RuntimeException("Unsafe archive entry: {$entry}");
            }
            $files[] = $entry;
        }

        if (! in_array('DB/database.sql', $files, true) || ! in_array('manifest.json', $files, true)) {
            throw new RuntimeException('Invalid IR4 backup: DB/database.sql or manifest.json is missing.');
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(ZipArchive $zip): array
    {
        $json = $zip->getFromName('manifest.json');
        $meta = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($meta) || ($meta['format'] ?? null) !== 'ir4-site-backup/v1') {
            throw new RuntimeException('Invalid or unsupported IR4 backup manifest.');
        }

        return $meta;
    }

    private function extractDatabase(ZipArchive $zip, string $destination): void
    {
        $source = $zip->getStream('DB/database.sql');
        if ($source === false) {
            throw new RuntimeException('Unable to read DB/database.sql from archive.');
        }
        $target = fopen($destination, 'wb');
        if ($target === false) {
            fclose($source);
            throw new RuntimeException("Unable to create restore dump: {$destination}");
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('Unable to extract database dump.');
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    private function openArchive(string $archive): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException("Unable to open backup archive: {$archive}");
        }

        return $zip;
    }
}
