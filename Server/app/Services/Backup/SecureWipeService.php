<?php

namespace App\Services\Backup;

use App\Enums\AuditEvent;
use App\Services\AuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SecureWipeService
{
    public const CONFIRM_PHRASE = 'WIPE-IR4-PROJECT-DATA';

    public function __construct(
        private readonly ExportManifestService $markers,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return array{receipt: string, export_id: string, mode: string}
     */
    public function wipe(
        string $confirm,
        ?string $exportId = null,
        bool $includeBackups = true,
        bool $dryRun = false,
    ): array {
        if ($confirm !== self::CONFIRM_PHRASE) {
            throw new RuntimeException('Confirmation phrase mismatch. Pass --confirm='.self::CONFIRM_PHRASE);
        }

        $marker = $this->markers->requireVerified($exportId);
        $mode = (string) config('backup.wipe_mode', 'crypto_erase');
        $receiptRelative = 'final/wipe-receipt-'.($marker['export_id'] ?? 'unknown').'.json';

        if ($dryRun) {
            return [
                'receipt' => $receiptRelative,
                'export_id' => (string) ($marker['export_id'] ?? ''),
                'mode' => $mode,
            ];
        }

        // Immutable handover archive is never mutated. Write a separate wipe receipt.
        $this->markers->writeWipeReceipt($marker, $receiptRelative);

        $this->audit->record(
            AuditEvent::Wiped,
            description: 'Secure wipe executed after verified export.',
            newValues: [
                'export_id' => $marker['export_id'] ?? null,
                'archive_sha256' => $marker['archive_sha256'] ?? null,
                'receipt' => $receiptRelative,
                'mode' => $mode,
            ],
        );

        $this->destroyPrivateStorage($mode);
        if ($includeBackups) {
            $this->destroyDisk((string) config('backup.disk', 'backups'), $mode, keepPrefixes: []);
        }

        // Preserve handover archive + wipe receipt on exports disk.
        $this->destroyDisk(
            (string) config('backup.exports_disk', 'exports'),
            $mode,
            keepPrefixes: [
                (string) ($marker['archive_path'] ?? ''),
                $receiptRelative,
                'final/markers/'.($marker['export_id'] ?? '').'.json',
            ],
        );

        $this->destroyDatabase();

        return [
            'receipt' => $receiptRelative,
            'export_id' => (string) ($marker['export_id'] ?? ''),
            'mode' => $mode,
        ];
    }

    private function destroyPrivateStorage(string $mode): void
    {
        $disk = Storage::disk('private');
        foreach ($disk->allFiles() as $path) {
            if (str_starts_with($path, 'exports/')) {
                continue;
            }
            $absolute = $disk->path($path);
            if ($mode === 'overwrite' && is_file($absolute)) {
                $this->overwriteFile($absolute);
            }
            $disk->delete($path);
        }
    }

    /**
     * @param  list<string>  $keepPrefixes
     */
    private function destroyDisk(string $diskName, string $mode, array $keepPrefixes): void
    {
        $disk = Storage::disk($diskName);
        foreach ($disk->allFiles() as $path) {
            foreach ($keepPrefixes as $keep) {
                if ($keep !== '' && ($path === $keep || str_starts_with($path, rtrim($keep, '/')))) {
                    continue 2;
                }
            }
            $absolute = $disk->path($path);
            if ($mode === 'overwrite' && is_file($absolute)) {
                $this->overwriteFile($absolute);
            }
            $disk->delete($path);
        }
    }

    private function destroyDatabase(): void
    {
        $connection = (string) config('backup.wipe_connection', 'ir4_wipe');
        $db = DB::connection($connection);
        $skip = ['migrations'];

        Schema::connection($connection)->disableForeignKeyConstraints();
        foreach ($this->tableNames($connection) as $table) {
            if (in_array($table, $skip, true)) {
                continue;
            }
            $db->table($table)->delete();
        }
        Schema::connection($connection)->enableForeignKeyConstraints();

        // Re-seed baseline permissions/settings so the install is empty but bootable.
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\SettingsSeeder',
            '--force' => true,
        ]);
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolePermissionSeeder',
            '--force' => true,
        ]);
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\GasThresholdSeeder',
            '--force' => true,
        ]);
    }

    /**
     * @return list<string>
     */
    private function tableNames(string $connection): array
    {
        $db = DB::connection($connection);

        if ($db->getDriverName() === 'sqlite') {
            /** @var list<string> $names */
            $names = collect($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->map(fn (mixed $name): string => (string) $name)
                ->values()
                ->all();

            return $names;
        }

        /** @var list<string> $names */
        $names = collect(Schema::connection($connection)->getTables())
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return $names;
    }

    private function overwriteFile(string $path): void
    {
        $size = filesize($path);
        if ($size === false || $size === 0) {
            return;
        }
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return;
        }
        $chunk = str_repeat("\0", 8192);
        $remaining = $size;
        while ($remaining > 0) {
            $write = min(8192, $remaining);
            fwrite($handle, substr($chunk, 0, $write));
            $remaining -= $write;
        }
        fclose($handle);
    }
}
