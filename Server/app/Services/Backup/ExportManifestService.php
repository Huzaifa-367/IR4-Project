<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ExportManifestService
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function write(string $exportId, array $meta): array
    {
        $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
        $path = "final/markers/{$exportId}.json";
        $marker = array_merge([
            'export_id' => $exportId,
            'format' => 'ir4-export-marker/v1',
            'created_at' => now()->toIso8601String(),
            'status' => 'completed',
            'verified_at' => null,
        ], $meta);

        $encoded = json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode export marker.');
        }
        $disk->put($path, $encoded);

        return $marker;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
        if (! $disk->exists('final/markers')) {
            return null;
        }

        $path = collect($disk->files('final/markers'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.json'))
            ->sort()
            ->last();

        if ($path === null) {
            return null;
        }

        return $this->read($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $relativePath): array
    {
        $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
        $raw = $disk->get($relativePath);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid export marker at {$relativePath}");
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function requireVerified(?string $exportId = null): array
    {
        $marker = $exportId !== null
            ? $this->read("final/markers/{$exportId}.json")
            : $this->latest();

        if ($marker === null) {
            throw new RuntimeException('No export marker found. Run ir4:export-all first.');
        }

        if (($marker['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Export marker is not completed.');
        }

        $archivePath = (string) ($marker['archive_path'] ?? '');
        $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
        if ($archivePath === '' || ! $disk->exists($archivePath)) {
            throw new RuntimeException('Export archive referenced by marker is missing.');
        }

        $sha = hash_file('sha256', $disk->path($archivePath));
        if ($sha === false || $sha !== ($marker['archive_sha256'] ?? null)) {
            throw new RuntimeException('Export archive checksum does not match marker.');
        }

        return $marker;
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    public function writeWipeReceipt(array $marker, string $receiptPath): void
    {
        $disk = Storage::disk((string) config('backup.exports_disk', 'exports'));
        $encoded = json_encode([
            'format' => 'ir4-wipe-receipt/v1',
            'export_id' => $marker['export_id'] ?? null,
            'wiped_at' => now()->toIso8601String(),
            'archive_path' => $marker['archive_path'] ?? null,
            'archive_sha256' => $marker['archive_sha256'] ?? null,
            'mode' => config('backup.wipe_mode', 'crypto_erase'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode wipe receipt.');
        }
        $disk->put($receiptPath, $encoded);
    }
}
