<?php

namespace App\Services\Backup;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Services\AlertService;

final class DiskSpaceMonitor
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {}

    public function check(): void
    {
        $threshold = max(1, (int) config('backup.disk_space_warn_pct', 15));

        foreach (['private', (string) config('backup.disk', 'backups')] as $diskName) {
            $probe = $this->probePath($diskName);
            if ($probe === null) {
                continue;
            }

            $total = @disk_total_space($probe);
            $free = @disk_free_space($probe);
            if ($total === false || $free === false || $total <= 0) {
                continue;
            }

            $freePct = (int) round(($free / $total) * 100);
            if ($freePct < $threshold) {
                $this->alerts->raise(
                    type: AlertType::System,
                    severity: AlertSeverity::Warning,
                    title: 'Disk space low',
                    payload: [
                        'disk' => $diskName,
                        'free_pct' => $freePct,
                        'threshold_pct' => $threshold,
                        'path' => $probe,
                    ],
                    dedupeKey: "disk:low:{$diskName}",
                );
            }
        }
    }

    /**
     * Resolve a real directory to probe without touching Flysystem (empty roots
     * like BACKUP_DISK_ROOT= previously made Storage::path('') create ".").
     */
    private function probePath(string $diskName): ?string
    {
        $root = rtrim((string) config("filesystems.disks.{$diskName}.root", ''), DIRECTORY_SEPARATOR);
        if ($root === '' || $root === '.' || $root === DIRECTORY_SEPARATOR) {
            return null;
        }

        if (is_dir($root)) {
            return $root;
        }

        $parent = dirname($root);

        return is_dir($parent) ? $parent : null;
    }
}
