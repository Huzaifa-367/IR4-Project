<?php

namespace App\Services\Backup;

use App\Enums\AuditEvent;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class ExportAllService
{
    private const MASKED_COLUMNS = [
        'password',
        'remember_token',
        'api_token',
        'api_token_hash',
        'token',
        'token_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function __construct(
        private readonly DatabaseDumperFactory $dumpers,
        private readonly ArchiveEncryptor $encryptor,
        private readonly ExportManifestService $markers,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return array{archive_path: string, export_id: string, key: string, bytes: int}
     */
    public function run(?string $clientKey = null): array
    {
        $exportId = (string) Str::ulid();
        $key = $clientKey ?: base64_encode(random_bytes(32));
        $workdir = storage_path('app/tmp/export-'.$exportId);
        $this->ensureDirectory($workdir);

        try {
            $this->ensureDirectory($workdir.'/database/tables');
            $this->dumpers->forConnection()->dumpTo($workdir.'/database/dump.sql');
            $this->exportTableCsvs($workdir.'/database/tables');

            $this->ensureDirectory($workdir.'/audit');
            $this->exportAuditCsv($workdir.'/audit/audit_logs.csv');

            $this->copyPrivateTree($workdir.'/storage');

            $meta = [
                'format' => 'ir4-archive/v1',
                'kind' => 'export',
                'created_at' => now()->toIso8601String(),
                'app_name' => (string) config('app.name'),
                'db_driver' => (string) config('database.connections.'.config('database.default').'.driver'),
                'export_id' => $exportId,
                'key_fingerprint' => $this->encryptor->fingerprint($key),
            ];
            file_put_contents($workdir.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

            $manifest = $this->buildManifest($workdir);
            file_put_contents($workdir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            file_put_contents(
                $workdir.'/CHECKSUMS.sha256',
                collect($manifest)
                    ->map(fn (array $row): string => "{$row['sha256']}  {$row['path']}")
                    ->implode("\n")."\n",
            );

            $zipPath = $workdir.'/handover.zip';
            $this->zipDirectory($workdir, $zipPath, ['handover.zip', 'archive.ir4exp']);

            $encryptedLocal = $workdir.'/archive.ir4exp';
            $this->encryptor->encryptFile($zipPath, $encryptedLocal, $key, ArchiveEncryptor::MAGIC_EXPORT);

            $relative = 'final/ir4-handover-'.now()->format('YmdHis')."-{$exportId}.ir4exp";
            $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
            $disk->put($relative, (string) file_get_contents($encryptedLocal));
            $bytes = (int) $disk->size($relative);
            $sha = hash_file('sha256', $disk->path($relative)) ?: '';

            $marker = $this->markers->write($exportId, [
                'archive_path' => $relative,
                'archive_sha256' => $sha,
                'archive_bytes' => $bytes,
                'key_fingerprint' => $this->encryptor->fingerprint($key),
                'db_driver' => $meta['db_driver'],
                'status' => 'completed',
            ]);

            file_put_contents(
                $workdir.'/export-marker.json',
                json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            $this->audit->record(
                AuditEvent::Exported,
                description: 'Full project export generated.',
                newValues: [
                    'export_id' => $exportId,
                    'archive_path' => $relative,
                    'archive_sha256' => $sha,
                    'key_fingerprint' => $marker['key_fingerprint'],
                ],
            );

            return [
                'archive_path' => $relative,
                'export_id' => $exportId,
                'key' => $key,
                'bytes' => $bytes,
            ];
        } finally {
            $this->removeDirectory($workdir);
        }
    }

    private function exportTableCsvs(string $directory): void
    {
        $tables = $this->tableNames();
        foreach ($tables as $table) {
            $path = "{$directory}/{$table}.csv";
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Unable to open {$path}");
            }

            $first = true;
            DB::table($table)->orderBy($this->primaryKey($table))->chunk(500, function ($rows) use ($handle, &$first): void {
                foreach ($rows as $row) {
                    $array = (array) $row;
                    foreach (self::MASKED_COLUMNS as $column) {
                        if (array_key_exists($column, $array) && $array[$column] !== null) {
                            $array[$column] = '••••';
                        }
                    }
                    if ($first) {
                        fputcsv($handle, array_keys($array));
                        $first = false;
                    }
                    fputcsv($handle, array_values($array));
                }
            });
            fclose($handle);
        }
    }

    private function exportAuditCsv(string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path}");
        }

        $first = true;
        DB::table('audit_logs')->orderBy('id')->chunk(500, function ($rows) use ($handle, &$first): void {
            foreach ($rows as $row) {
                $array = (array) $row;
                if ($first) {
                    fputcsv($handle, array_keys($array));
                    $first = false;
                }
                fputcsv($handle, array_values($array));
            }
        });
        fclose($handle);
    }

    private function copyPrivateTree(string $destination): void
    {
        $this->ensureDirectory($destination);
        $disk = Storage::disk('private');
        foreach ($disk->allFiles() as $path) {
            if (str_starts_with($path, 'exports/')) {
                continue;
            }
            $target = $destination.'/'.$path;
            $this->ensureDirectory(dirname($target));
            copy($disk->path($path), $target);
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            /** @var list<string> $names */
            $names = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->map(fn (mixed $name): string => (string) $name)
                ->sort()
                ->values()
                ->all();

            return $names;
        }

        /** @var list<string> $names */
        $names = collect(Schema::getTables())
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return $names;
    }

    private function primaryKey(string $table): string
    {
        return Schema::hasColumn($table, 'id') ? 'id' : (Schema::getColumnListing($table)[0] ?? 'rowid');
    }

    /**
     * @return list<array{path: string, bytes: int, sha256: string}>
     */
    private function buildManifest(string $directory): array
    {
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($directory, '', $absolute), DIRECTORY_SEPARATOR);
            if (in_array(basename($relative), ['handover.zip', 'archive.ir4exp'], true)) {
                continue;
            }
            $entries[] = [
                'path' => str_replace('\\', '/', $relative),
                'bytes' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $absolute) ?: '',
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
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($directory, '', $absolute), DIRECTORY_SEPARATOR);
            if (in_array(basename($relative), $exclude, true)) {
                continue;
            }
            $zip->addFile($absolute, str_replace('\\', '/', $relative));
        }
        $zip->close();
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create {$directory}");
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
