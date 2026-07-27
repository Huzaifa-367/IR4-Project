<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Host-only transfer from shared /data2 staging to the mounted /data volume. */
final class BackupPublisher
{
    public function __construct(
        private readonly BackupVolume $volume,
    ) {}

    /**
     * @return array{published: list<string>, kept: int}
     */
    public function publishAll(?int $keep = null): array
    {
        $stagingRoot = $this->volume->stagingRoot();
        $dailyRoot = $this->volume->prepareVolume().'/daily';
        File::ensureDirectoryExists($dailyRoot, 0750);

        $published = [];
        $recordedKeep = null;
        foreach (glob($stagingRoot.'/ir4-backup-*.zip') ?: [] as $stagedPath) {
            $meta = $this->readMetadata($stagedPath.'.json');
            if (isset($meta['keep']) && is_numeric($meta['keep'])) {
                $recordedKeep = (int) $meta['keep'];
            }
            $destination = $dailyRoot.'/'.basename($stagedPath);
            $this->copyAtomically($stagedPath, $destination);
            File::delete([$stagedPath.'.json', $stagedPath]);
            $published[] = $destination;
        }

        $kept = $this->rotate(
            $dailyRoot,
            $keep ?? $recordedKeep ?? (int) config('backup.keep_count', 30),
        );
        if ($published !== []) {
            File::put(
                $stagingRoot.'/last-published.json',
                json_encode([
                    'published_at' => now()->toIso8601String(),
                    'archives' => $published,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        }

        return ['published' => $published, 'kept' => $kept];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function copyAtomically(string $source, string $destination): void
    {
        $partial = $destination.'.partial';
        if (! File::copy($source, $partial)) {
            throw new RuntimeException("Unable to copy staged backup to {$partial}");
        }

        $sourceHash = hash_file('sha256', $source);
        $targetHash = hash_file('sha256', $partial);
        if ($sourceHash === false || ! hash_equals($sourceHash, (string) $targetHash)) {
            File::delete($partial);
            throw new RuntimeException('Backup checksum mismatch during publish.');
        }

        if (! File::move($partial, $destination)) {
            File::delete($partial);
            throw new RuntimeException("Unable to publish backup at {$destination}");
        }
    }

    private function rotate(string $dailyRoot, int $keep): int
    {
        $files = glob($dailyRoot.'/ir4-backup-*.zip') ?: [];
        sort($files);
        $keep = max(1, $keep);

        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $path) {
            File::delete($path);
        }

        return min(count($files), $keep);
    }
}
