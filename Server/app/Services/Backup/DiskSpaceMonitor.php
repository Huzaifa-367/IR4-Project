<?php

namespace App\Services\Backup;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\AlertService;

final class DiskSpaceMonitor
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly BackupVolume $volume,
    ) {}

    public function check(): void
    {
        $threshold = max(1, (int) config('backup.disk_space_warn_pct', 15));

        $targets = [
            'private' => $this->configuredRoot('private'),
            (string) config('backup.disk', 'backups') => $this->volume->volumeRoot(),
        ];

        foreach ($targets as $diskName => $root) {
            if ($root === null || ! is_dir($root)) {
                continue;
            }

            $total = @disk_total_space($root);
            $free = @disk_free_space($root);
            if ($total === false || $free === false || $total <= 0) {
                continue;
            }

            $freePct = (int) round(($free / $total) * 100);
            if ($freePct > $threshold) {
                continue;
            }

            $this->alerts->raise(
                type: AlertType::System,
                severity: AlertSeverity::Warning,
                title: 'Disk space low',
                payload: [
                    'disk' => $diskName,
                    'root' => $root,
                    'free_pct' => $freePct,
                    'threshold_pct' => $threshold,
                ],
                dedupeKey: 'disk_space_low:'.$diskName,
            );
        }
    }

    private function configuredRoot(string $diskName): ?string
    {
        $configured = config("filesystems.disks.{$diskName}.root");
        if (! is_string($configured) || $configured === '' || $configured === '.') {
            return null;
        }

        $resolved = realpath($configured);

        return $resolved !== false ? $resolved : $configured;
    }
}
